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

/** CAS 2.0 or 3.0 through phpCAS, which was written for one request per process and keeps its state in `$_SESSION`. */
final class CasProvider implements RedirectProvider
{
    private const TIMEOUT = 10;
    private const VERSIONS = ['2.0' => '/serviceValidate', '3.0' => '/p3/serviceValidate'];
    private const DEFAULT_VERSION = '3.0';

    /** What phpCAS writes in `$_SESSION`, under this key only. */
    private const SESSION_KEY = 'phpCAS';

    /** Values the server returns about the ticket, not about the person. */
    private const NOT_ATTRIBUTES = ['proxyGrantingTicket', 'proxies'];

    /** Where the handshake keeps the sign-in's state, and the query parameter that carries it in the service. */
    private const STATE_KEY = 'cas_state';
    private const STATE_PARAMETER = 'sso_state';

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
        $handshake->set(self::STATE_KEY, bin2hex(random_bytes(16)));
        $url = $this->confined($server, self::service($handshake), fn (\CAS_Client $client): string => $client->getServerLoginURL());

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
        $state = $request->query->all()[self::STATE_PARAMETER] ?? null;
        $expected = (string)$handshake->get(self::STATE_KEY);
        if ($expected === '') {
            throw new ProviderFailure(FailureReason::InvalidResponse, 'this sign-in started before the wiki put an sso_state in the CAS service: start it again');
        }
        if (!is_string($state) || $state === '') {
            throw new ProviderFailure(FailureReason::InvalidResponse, 'the person came back without the sso_state the wiki put in the CAS service: the server, or a proxy, dropped it from the address');
        }
        if (!hash_equals($expected, $state)) {
            throw new ProviderFailure(FailureReason::InvalidResponse, 'the sso_state sent back is not the one this sign-in started with: the ticket was issued for another sign-in');
        }
        $server = $this->server();

        return $this->confined($server, self::service($handshake), function (\CAS_Client $client) use ($ticket): ProviderIdentity {
            $client->setTicket($ticket);
            if (!$client->isAuthenticated()) {
                throw new ProviderFailure(FailureReason::InvalidResponse, 'phpCAS validated no ticket');
            }
            $attributes = $client->getAttributes();
            $attributes = array_diff_key(is_array($attributes) ? $attributes : [], array_flip(self::NOT_ATTRIBUTES));

            return new ProviderIdentity(['user' => $client->getUser()] + $attributes);
        }, $ticket);
    }

    /** The service of this sign-in: the callback, then its state, last, so the callback registered at the server stays a prefix of it. */
    private static function service(Handshake $handshake): string
    {
        return $handshake->callbackUrl . '&' . self::STATE_PARAMETER . '=' . rawurlencode((string)$handshake->get(self::STATE_KEY));
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
        $foreign = session_status() === PHP_SESSION_ACTIVE;
        $cookies = ini_get('session.use_cookies');
        if (!$foreign) {
            ini_set('session.use_cookies', '0');
        }
        $opened = false;
        $buffers = ob_get_level();
        ob_start();
        $libxml = libxml_use_internal_errors(true);
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
            $thrower = $error->getTrace()[0] ?? [];
            if ($error instanceof \ValueError && ($thrower['class'] ?? '') === \DOMDocument::class && $thrower['function'] === 'loadXML') {
                throw $this->notACasResponse($server);
            }
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
     * An answer phpCAS could not read.
     *
     * @param array{base: string, host: string, port: int, path: string, version: string, login: string, validate: string, ca: string} $server
     * @param bool                                                                                                                     $casLooking the answer has a serviceResponse element (an HTML page at a wrong address has none)
     */
    private function notACasResponse(array $server, bool $casLooking = false): ProviderFailure
    {
        $status = $this->askAgain($server)['status'];
        if ($status !== null && ServerDown::is($status)) {
            return new ProviderFailure(FailureReason::ProviderUnreachable, "the CAS server's ticket validation at {$server['validate']} answered HTTP $status: the provider's server is down or overloaded, or its proxy cannot reach it");
        }

        $error = null;
        foreach (libxml_get_errors() as $entry) {
            if ($entry->level >= LIBXML_ERR_ERROR) {
                $error = $entry;
                break;
            }
        }
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
