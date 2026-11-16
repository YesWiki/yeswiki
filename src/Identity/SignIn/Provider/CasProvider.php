<?php

namespace YesWiki\Identity\SignIn\Provider;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Identity\SignIn\CaBundle;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\Handshake;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\Contract\ProviderIdentity;
use YesWiki\Identity\SignIn\Contract\RedirectProvider;
use YesWiki\Identity\SignIn\ServerDown;
use YesWiki\Kernel\Service\SessionRequirement;

/**
 * CAS 2.0 or 3.0 through phpCAS, which was written for one request per process and keeps its state in `$_SESSION`. It is confined (spec section 6):
 *
 * - a CAS_Client is made for each call, never the static one of phpCAS, and with changeSessionID false;
 * - the wiki's own session is started for it, without a cookie, emptied of phpCAS's key first, and destroyed when the call ends, success or failure;
 * - phpCAS throws instead of exiting, which would end the worker, never redirects by itself, and prints into a buffer that is thrown away;
 * - the ticket comes from the Request, and `$_GET`, where phpCAS would look for one, is hidden from it while the client is made;
 * - the certificates are always checked, against ca_cert or the system's authorities;
 * - the service is the handshake's callback, at the login and at the validation alike.
 *
 * phpCAS builds https addresses from a host and a port: the login and validation addresses are given whole from server_url instead.
 * What a failure says is the CAS error code and message, with any ticket taken out; never the ticket, the session or what the server sent.
 */
final class CasProvider implements RedirectProvider
{
    private const TIMEOUT = 10;
    private const VERSIONS = ['2.0' => '/serviceValidate', '3.0' => '/p3/serviceValidate'];
    private const DEFAULT_VERSION = '3.0';

    /** What phpCAS writes in `$_SESSION`, under this key only. */
    private const SESSION_KEY = 'phpCAS';

    /** Values the server returns about the ticket, not about the person. */
    private const NOT_ATTRIBUTES = ['proxyGrantingTicket', 'proxies'];

    /**
     * @param string                                      $baseUrl      the wiki's address, which phpCAS needs to be made, though the service is always given whole
     * @param class-string<\CAS_Request_RequestInterface> $requestClass how phpCAS reaches the server: curl, or a test's canned answers
     */
    public function __construct(
        private readonly ProviderConfig $config,
        private readonly SessionRequirement $sessions,
        private readonly string $baseUrl,
        private readonly string $requestClass = \CAS_Request_CurlRequest::class,
    ) {
    }

    public function id(): string
    {
        return $this->config->id;
    }

    public function label(): string
    {
        return $this->config->label;
    }

    public function type(): string
    {
        return $this->config->type;
    }

    public function begin(Handshake $handshake): Response
    {
        $server = $this->server();
        $url = $this->confined($server, $handshake->callbackUrl, fn (\CAS_Client $client): string => $client->getServerLoginURL());

        return new RedirectResponse($url);
    }

    public function complete(Request $request, Handshake $handshake): ProviderIdentity
    {
        $ticket = $request->query->all()['ticket'] ?? null;
        if (!is_string($ticket) || $ticket === '') {
            throw new ProviderFailure(FailureReason::InvalidResponse, 'the CAS server sent the person back with no ticket');
        }
        if (!preg_match('/^[SP]T-[\x21-\x7e]{1,2048}$/', $ticket)) {
            throw new ProviderFailure(FailureReason::InvalidResponse, 'the ticket sent back is not a CAS service ticket');
        }
        $server = $this->server();

        return $this->confined($server, $handshake->callbackUrl, function (\CAS_Client $client) use ($ticket): ProviderIdentity {
            $client->setTicket($ticket);
            if (!$client->isAuthenticated()) {
                throw new ProviderFailure(FailureReason::InvalidResponse, 'phpCAS validated no ticket');
            }
            // an attributes element with no attribute in it (LemonLDAP::NG's, when it releases none) comes back from phpCAS as its text
            $attributes = $client->getAttributes();
            $attributes = array_diff_key(is_array($attributes) ? $attributes : [], array_flip(self::NOT_ATTRIBUTES));

            return new ProviderIdentity(['user' => $client->getUser()] + $attributes);
        }, $ticket);
    }

    /**
     * The server's addresses, the certificate authorities and the version, all checked before phpCAS is involved.
     *
     * @return array{base: string, host: string, port: int, path: string, version: string, login: string, validate: string, ca: string}
     */
    private function server(): array
    {
        $url = rtrim(trim((string)$this->config->connection('server_url', '')), '/');
        $parts = $url === '' ? false : parse_url($url);
        $scheme = is_array($parts) ? strtolower($parts['scheme'] ?? '') : '';
        if (!is_array($parts) || !in_array($scheme, ['http', 'https'], true) || ($parts['host'] ?? '') === '' || isset($parts['query']) || isset($parts['fragment'])) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, $url === '' ? 'no server_url: set the CAS server\'s address, like https://cas.example.org/cas' : "server_url \"$url\" is not a CAS server's address: write it like https://cas.example.org/cas");
        }

        $version = $this->config->connection('version', self::DEFAULT_VERSION);
        if (is_int($version) || is_float($version)) {
            $version = number_format((float)$version, 1, '.', '');
        }
        $version = is_string($version) && trim($version) !== '' ? trim($version) : self::DEFAULT_VERSION;
        if (!isset(self::VERSIONS[$version])) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, "version \"$version\" is not a CAS version this wiki speaks: write 2.0 or 3.0");
        }

        $configured = $this->config->connection('ca_cert');
        $ca = CaBundle::path(is_string($configured) ? $configured : null);
        if (!CaBundle::isReadable($ca)) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, "ca_cert \"$ca\" cannot be read: it must be the PEM file of the authority that signed the CAS server's certificate");
        }

        return [
            'base' => $url,
            'host' => (string)$parts['host'],
            'port' => (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80)),
            'path' => (string)($parts['path'] ?? '/'),
            'version' => $version,
            'login' => $url . '/login',
            'validate' => $url . self::VERSIONS[$version],
            'ca' => $ca,
        ];
    }

    /**
     * A CAS_Client for one call, in a session of its own that does not outlive the call, printing nowhere.
     *
     * @template T
     *
     * @param array{base: string, host: string, port: int, path: string, version: string, login: string, validate: string, ca: string} $server
     * @param callable(\CAS_Client): T                                                                                                 $use
     *
     * @return T
     */
    private function confined(array $server, string $service, callable $use, string $ticket = ''): mixed
    {
        \CAS_GracefullTerminationException::throwInsteadOfExiting();
        // a session another part of the wiki opened is left to it, emptied of phpCAS's key only
        $foreign = session_status() === PHP_SESSION_ACTIVE;
        // one started here is known by no browser: no cookie is read, so an extension's session is never taken, and none is sent
        $cookies = ini_get('session.use_cookies');
        if (!$foreign) {
            ini_set('session.use_cookies', '0');
        }
        $opened = false;
        $buffers = ob_get_level();
        ob_start();
        $libxml = libxml_use_internal_errors(true);
        // only this call's parse errors are read when the answer is not well-formed (notACasResponse)
        libxml_clear_errors();
        try {
            $opened = $this->sessions->start() && !$foreign;
            if (session_status() !== PHP_SESSION_ACTIVE) {
                throw new ProviderFailure(FailureReason::InternalError, 'phpCAS needs a session, and none could be started: the page had already begun');
            }
            unset($_SESSION[self::SESSION_KEY]);

            return $use($this->client($server, $service));
        } catch (ProviderFailure $failure) {
            throw $failure;
        } catch (\CAS_AuthenticationException $refused) {
            throw $this->refusal($refused, $server, $ticket);
        } catch (\Throwable $error) {
            // phpCAS takes an empty answer for a bad one, but DOMDocument::loadXML() throws on it since PHP 8, before phpCAS can say so
            $thrower = $error->getTrace()[0] ?? [];
            if ($error instanceof \ValueError && ($thrower['class'] ?? '') === \DOMDocument::class && $thrower['function'] === 'loadXML') {
                throw $this->notACasResponse($server);
            }
            // whatever else stopped phpCAS, said with the ticket taken out
            throw new ProviderFailure(FailureReason::InternalError, 'phpCAS stopped: ' . $error::class . ': ' . self::scrub($error->getMessage(), $ticket));
        } finally {
            if ($opened) {
                $_SESSION = [];
                session_destroy();
            } elseif ($foreign) {
                unset($_SESSION[self::SESSION_KEY]);
            }
            if (!$foreign) {
                ini_set('session.use_cookies', $cookies);
            }
            while (ob_get_level() > $buffers) {
                ob_end_clean();
            }
            libxml_clear_errors();
            libxml_use_internal_errors($libxml);
        }
    }

    /** @param array{base: string, host: string, port: int, path: string, version: string, login: string, validate: string, ca: string} $server */
    private function client(array $server, string $service): \CAS_Client
    {
        // phpCAS takes a ticket from $_GET when it is made, and stops on one it finds ill-formed: the ticket is the Request's, given below
        $get = $_GET;
        $post = $_POST;
        $_GET = [];
        $_POST = [];
        try {
            $client = new \CAS_Client($server['version'], false, $server['host'], $server['port'], $server['path'], $this->baseUrl, false);
        } finally {
            $_GET = $get;
            $_POST = $post;
        }
        $client->setRequestImplementation($this->requestClass);
        $client->setCasServerCACert($server['ca'], true);
        $client->setNoClearTicketsFromUrl();
        foreach (self::curlOptions() as $option => $value) {
            $client->setExtraCurlOption($option, $value);
        }
        $client->setServerLoginURL($server['login'] . '?service=' . urlencode($service));
        $client->setServerServiceValidateURL($server['validate']);
        $client->setURL($service);

        return $client;
    }

    /**
     * What an administrator reads of a validation that failed: the CAS code and message, or what kept the server from answering.
     * phpCAS's own message quotes the address it asked, ticket included, and the whole response: neither is kept.
     *
     * @param array{base: string, host: string, port: int, path: string, version: string, login: string, validate: string, ca: string} $server
     */
    private function refusal(\CAS_AuthenticationException $refused, array $server, string $ticket): ProviderFailure
    {
        $message = $refused->getMessage();
        if (str_contains($message, "\nReason: no response from the CAS server")) {
            return new ProviderFailure(FailureReason::ProviderUnreachable, "the CAS server at {$server['base']} could not be reached to validate the ticket: " . $this->whyUnreachable($server));
        }
        if (preg_match('/\nReason: \[(.*?)\] CAS error: (.*?)\nCAS response: /s', $message, $error)) {
            $code = preg_replace('/[^A-Za-z0-9_.-]/', '', $error[1]) ?? '';
            $text = self::scrub(trim(preg_replace('/\s+/', ' ', $error[2]) ?? ''), $ticket);

            return new ProviderFailure(FailureReason::ProviderRejected, 'the CAS server refused the ticket: ' . ($code !== '' ? "$code: " : '') . mb_substr($text, 0, 300));
        }
        if (str_contains($message, "\nAuthentication failure: Proxy not allowed")) {
            return new ProviderFailure(FailureReason::InvalidResponse, 'the ticket came through CAS proxies, which this wiki does not accept');
        }

        return $this->notACasResponse($server, self::looksLikeCas($message));
    }

    /** Whether the answer phpCAS quotes after "CAS response: " is meant as a CAS answer: it has a serviceResponse element. */
    private static function looksLikeCas(string $message): bool
    {
        $at = strpos($message, "\nCAS response: ");

        return $at !== false && preg_match('/<(?:[\w.-]+:)?serviceResponse\b/', substr($message, $at)) === 1;
    }

    /**
     * An answer phpCAS could not read. phpCAS keeps the HTTP status to itself, so the validation address is asked again, without
     * the ticket: a reverse proxy answering 502, 503 or 504 for a server that is down is not a wrong server_url or version. An
     * answer that is not well-formed XML, but meant as a CAS answer, says so, with the parser's error: that is the server's fault,
     * not the settings'.
     *
     * @param array{base: string, host: string, port: int, path: string, version: string, login: string, validate: string, ca: string} $server
     * @param bool $casLooking the answer has a serviceResponse element (an HTML page at a wrong address has none)
     */
    private function notACasResponse(array $server, bool $casLooking = false): ProviderFailure
    {
        $status = $this->askAgain($server)['status'];
        if ($status !== null && ServerDown::is($status)) {
            return new ProviderFailure(FailureReason::ProviderUnreachable, "the CAS server's ticket validation at {$server['validate']} answered HTTP $status: the provider's server is down or overloaded, or its proxy cannot reach it");
        }

        // the parser's first error, where it is (a warning before it, about the XML version for instance, is not why it failed):
        // never the text, which is what the server sent
        $error = null;
        foreach (libxml_get_errors() as $entry) {
            if ($entry->level >= LIBXML_ERR_ERROR) {
                $error = $entry;
                break;
            }
        }
        // only a CAS answer (a serviceResponse element) from an address that answers 200, or 400, its refusal of the ticketless request
        // (SimpleSAMLphp's casserver), is the validation endpoint's: another page that is no XML (a 404, a login page, an application
        // that refuses the bare request) is still a wrong server_url
        if ($casLooking && ($status === 200 || $status === 400) && $error instanceof \LibXMLError) {
            return new ProviderFailure(FailureReason::InvalidResponse, "the CAS server's answer to the ticket validation at {$server['validate']} is not well-formed XML: " . trim($error->message) . " (line {$error->line}, column {$error->column}); an attribute value the server did not escape, like a \"&\", is a frequent cause");
        }

        return new ProviderFailure(FailureReason::InvalidResponse, "the CAS server's answer to the ticket validation at {$server['validate']} is not a CAS {$server['version']} response: check server_url and version");
    }

    /**
     * phpCAS keeps curl's error to itself: the validation address is asked again, without the ticket, to say what failed (a refused connection, an untrusted certificate...).
     *
     * @param array{base: string, host: string, port: int, path: string, version: string, login: string, validate: string, ca: string} $server
     */
    private function whyUnreachable(array $server): string
    {
        $error = $this->askAgain($server)['error'];

        return $error !== '' ? $error : 'it answered when asked again, so it may have been down for a moment';
    }

    /**
     * The validation address asked again, without the ticket and without its body: the HTTP status it answers, or the error that kept it from answering.
     *
     * @param array{base: string, host: string, port: int, path: string, version: string, login: string, validate: string, ca: string} $server
     *
     * @return array{status: ?int, error: string}
     */
    private function askAgain(array $server): array
    {
        $probe = new ($this->requestClass)();
        if (method_exists($probe, 'setCurlOptions')) {
            $probe->setCurlOptions([CURLOPT_NOBODY => true] + self::curlOptions());
        }
        $probe->setUrl($server['validate']);
        $probe->setSslCaCert($server['ca'], true);
        try {
            $answered = $probe->send();
        } catch (\CAS_Exception) {
            $answered = false;
        }
        if (!$answered) {
            return ['status' => null, 'error' => trim((string)$probe->getErrorMessage())];
        }
        try {
            return ['status' => $probe->getResponseStatusCode(), 'error' => ''];
        } catch (\CAS_Exception) {
            return ['status' => null, 'error' => ''];
        }
    }

    /** @return array<int, int|bool> */
    private static function curlOptions(): array
    {
        return [
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ];
    }

    /** A message with every CAS ticket taken out, the one of this sign-in first. */
    private static function scrub(string $message, string $ticket): string
    {
        if ($ticket !== '') {
            $message = str_replace($ticket, '[ticket]', $message);
        }

        return preg_replace('/\b(?:ST|PT|PGT|PGTIOU|TGT|TGC)-[^\s\'"<>&]+/', '[ticket]', $message) ?? '';
    }
}
