<?php

namespace YesWiki\Test\Identity\SignIn;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Identity\Service\SignIn\Type\CasType;
use YesWiki\Identity\SignIn\CaBundle;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\Handshake;
use YesWiki\Identity\SignIn\Contract\HandshakeMode;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\Contract\ProviderIdentity;
use YesWiki\Identity\SignIn\Provider\CasProvider;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\SessionRequirement;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * phpCAS confined (spec section 6): a client per call, the ticket from the Request, the session started by the wiki and destroyed
 * afterwards, nothing printed, the certificates always checked. The CAS server is CasCannedRequest, which phpCAS calls instead of curl.
 */
class CasProviderTest extends YesWikiTestCase
{
    private const SERVER = 'https://cas.example.org/cas';
    private const CALLBACK = 'http://wiki.yeswiki.test/?user/sign-in/cas/callback';
    private const BASE_URL = 'http://wiki.yeswiki.test/';
    private const TICKET = 'ST-1-a1b2c3d4e5f6-cas';
    private const CA_FIXTURE = __DIR__ . '/../../../fixtures/saml/idp.crt';

    /** @var array<string, mixed> */
    private array $get = [];

    protected function setUp(): void
    {
        parent::setUp();
        // the boot is where phpCAS is told to throw instead of exiting
        self::getWiki();
        $this->get = $_GET;
        CasCannedRequest::reset();
    }

    protected function tearDown(): void
    {
        $_GET = $this->get;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        CasCannedRequest::reset();
        parent::tearDown();
    }

    public function testBeginRedirectsToLoginWithTheService(): void
    {
        $response = $this->provider()->begin(self::handshake());

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(self::SERVER . '/login?service=' . urlencode(self::CALLBACK), $response->getTargetUrl());
        $this->assertSame([], CasCannedRequest::$urls, 'leaving for the server asks it nothing');
        $this->assertSessionLeftNothing();
    }

    public function testAValidTicketReturnsUserAndAttributes(): void
    {
        CasCannedRequest::$body = self::success();

        $identity = $this->provider()->complete(self::returning(), self::handshake());

        $this->assertInstanceOf(ProviderIdentity::class, $identity);
        $this->assertSame('marie', $identity->attributes['user']);
        $this->assertSame('marie.dupont@acme.example', $identity->attributes['mail']);
        $this->assertSame('Dupont', $identity->attributes['sn']);
        $this->assertSame(['wiki-editors', 'staff'], $identity->attributes['groups']);
        $this->assertArrayNotHasKey('proxyGrantingTicket', $identity->attributes, 'a ticket is not an attribute');
        $this->assertSame(
            [self::SERVER . '/p3/serviceValidate?service=' . urlencode(self::CALLBACK) . '&ticket=' . self::TICKET],
            CasCannedRequest::$urls,
            'validated once, for the service the login was asked for',
        );
        $this->assertSame(10, CasCannedRequest::$curlOptions[0][CURLOPT_TIMEOUT] ?? null, 'a server that does not answer does not hold the request forever');
    }

    public function testVersionTwoValidatesAtServiceValidate(): void
    {
        CasCannedRequest::$body = self::success();

        $this->provider(['version' => '2.0'])->complete(self::returning(), self::handshake());

        $this->assertSame([self::SERVER . '/serviceValidate?service=' . urlencode(self::CALLBACK) . '&ticket=' . self::TICKET], CasCannedRequest::$urls);
    }

    public function testAnInvalidTicketIsRejectedWithItsCasCode(): void
    {
        CasCannedRequest::$body = self::refusal('INVALID_TICKET', "Ticket '" . self::TICKET . "' not recognized");

        $failure = $this->failure(fn () => $this->provider()->complete(self::returning(), self::handshake()));

        $this->assertSame(FailureReason::ProviderRejected, $failure->reason);
        $this->assertStringContainsString('INVALID_TICKET', $failure->adminDetail);
        $this->assertStringContainsString('not recognized', $failure->adminDetail);
        $this->assertStringNotContainsString(self::TICKET, $failure->adminDetail, 'the ticket is never written anywhere');
        $this->assertStringNotContainsString('serviceValidate', $failure->adminDetail, 'the address phpCAS asked holds the ticket');
        $this->assertStringNotContainsString('authenticationFailure', $failure->adminDetail, 'the code and the message, not what was sent');
    }

    public function testAMissingTicketIsInvalid(): void
    {
        foreach ([Request::create(self::CALLBACK), Request::create(self::CALLBACK . '&ticket='), Request::create(self::CALLBACK . '&ticket=not-a-ticket')] as $request) {
            $failure = $this->failure(fn () => $this->provider()->complete($request, self::handshake()));

            $this->assertSame(FailureReason::InvalidResponse, $failure->reason);
            $this->assertStringNotContainsString('not-a-ticket', $failure->adminDetail);
        }
        $this->assertSame([], CasCannedRequest::$urls, 'no ticket, no question to the server');
    }

    public function testTheTicketIsReadFromTheRequestNeverFromGet(): void
    {
        // phpCAS reads $_GET['ticket'] when a client is made, and prints an error page for one it finds ill-formed
        $_GET = ['ticket' => 'ill-formed<script>'];
        CasCannedRequest::$body = self::success();
        $this->expectOutputString('');

        $identity = $this->provider()->complete(self::returning(), self::handshake());

        $this->assertSame('marie', $identity->attributes['user']);
        $this->assertSame(['ticket' => 'ill-formed<script>'], $_GET, 'what the request said is left as it was');
    }

    public function testNothingIsPrintedOnFailure(): void
    {
        $this->expectOutputString('');
        $level = ob_get_level();
        // phpCAS prints its error pages when verbose, a static anything in the process may have switched on
        \phpCAS::setVerbose(true);
        try {
            foreach ([self::refusal('INVALID_SERVICE', 'wrong service'), '<html>not CAS</html>', 'not even XML <', null] as $body) {
                CasCannedRequest::$body = $body;
                $this->failure(fn () => $this->provider()->complete(self::returning(), self::handshake()));
            }
        } finally {
            \phpCAS::setVerbose(false);
        }

        $this->assertSame($level, ob_get_level(), 'every buffer opened is closed');
    }

    public function testABadResponseIsInvalidAndNotQuoted(): void
    {
        CasCannedRequest::$body = '<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas"><cas:nonsense>secret-looking</cas:nonsense></cas:serviceResponse>';

        $failure = $this->failure(fn () => $this->provider()->complete(self::returning(), self::handshake()));

        $this->assertSame(FailureReason::InvalidResponse, $failure->reason);
        $this->assertStringNotContainsString('secret-looking', $failure->adminDetail);
    }

    public function testAnUnreachableServerIsUnreachableAndSaysWhy(): void
    {
        CasCannedRequest::$body = null;

        $failure = $this->failure(fn () => $this->provider()->complete(self::returning(), self::handshake()));

        $this->assertSame(FailureReason::ProviderUnreachable, $failure->reason);
        $this->assertStringContainsString(CasCannedRequest::ERROR, $failure->adminDetail, 'the network or TLS error, as curl gave it');
        $this->assertStringNotContainsString(self::TICKET, $failure->adminDetail);
        $this->assertStringNotContainsString('ticket=', implode(' ', array_slice(CasCannedRequest::$urls, 1)), 'asking why carries no ticket');
    }

    public function testAnythingElseThatStopsPhpCasIsSaidWithoutTheTicket(): void
    {
        CasCannedRequest::$thrown = new \RuntimeException('the connection broke while asking for ticket ' . self::TICKET);

        $failure = $this->failure(fn () => $this->provider()->complete(self::returning(), self::handshake()));

        $this->assertSame(FailureReason::InternalError, $failure->reason);
        $this->assertStringContainsString('the connection broke', $failure->adminDetail);
        $this->assertStringNotContainsString(self::TICKET, $failure->adminDetail);
        $this->assertSessionLeftNothing();
    }

    public function testTheSessionIsDestroyedAfterwards(): void
    {
        $cookies = ini_get('session.use_cookies');
        CasCannedRequest::$body = self::success();
        $this->provider()->complete(self::returning(), self::handshake());
        $this->assertSessionLeftNothing();
        $this->assertSame($cookies, ini_get('session.use_cookies'), 'the session settings are put back for the next request of the worker');

        // the user validated above is not carried into the next sign-in
        CasCannedRequest::$body = self::refusal('INVALID_TICKET', 'not recognized');
        $failure = $this->failure(fn () => $this->provider()->complete(self::returning(), self::handshake()));
        $this->assertSame(FailureReason::ProviderRejected, $failure->reason);
        $this->assertSessionLeftNothing();
    }

    public function testAUserLeftInAnOpenSessionIsNotTrusted(): void
    {
        $sessions = self::getWiki()->services->get(SessionRequirement::class);
        $this->assertTrue($sessions->start(), 'a session another part of the wiki opened');
        $_SESSION['phpCAS'] = ['user' => 'mallory', 'attributes' => ['mail' => 'mallory@example.com']];
        $_SESSION['theirs'] = 'kept';
        CasCannedRequest::$body = self::refusal('INVALID_TICKET', 'not recognized');

        $failure = $this->failure(fn () => $this->provider()->complete(self::returning(), self::handshake()));

        $this->assertSame(FailureReason::ProviderRejected, $failure->reason, 'the ticket is what is checked, not the session');
        $this->assertSame(PHP_SESSION_ACTIVE, session_status(), 'a session this provider did not open is not its own to destroy');
        $this->assertArrayNotHasKey('phpCAS', $_SESSION);
        $this->assertSame('kept', $_SESSION['theirs']);
    }

    public function testNoStaticOfPhpCasCarriesASignIn(): void
    {
        CasCannedRequest::$body = self::success();
        $this->provider()->complete(self::returning(), self::handshake());

        $statics = (new \ReflectionClass(\phpCAS::class))->getStaticProperties();
        $this->assertNull($statics['_PHPCAS_CLIENT'], 'no static client');
        $this->assertNull($statics['_PHPCAS_INIT_CALL']);
        $this->assertFalse($statics['_PHPCAS_VERBOSE'], 'phpCAS prints its errors only when verbose');
        $debug = is_array($statics['_PHPCAS_DEBUG']) ? $statics['_PHPCAS_DEBUG'] : [];
        $this->assertSame([], array_diff(array_keys($debug), ['indent']), 'no logger and no debug file: the traces, which quote the ticket, go nowhere');
        $this->assertFalse((new \ReflectionClass(\CAS_GracefullTerminationException::class))->getStaticPropertyValue('_exitWhenThrown'), 'the boot told phpCAS to throw instead of exiting');
    }

    public function testNeverDisablesCertificateChecks(): void
    {
        $source = (string)file_get_contents((string)(new \ReflectionClass(CasProvider::class))->getFileName());
        foreach (['setNoCasServerValidation', 'phpCAS::client', 'phpCAS::proxy', 'forceAuthentication', 'setVerbose', 'setLogger', 'setDebug', 'CURLOPT_SSL_VERIFY'] as $call) {
            $this->assertStringNotContainsString($call, $source);
        }

        CasCannedRequest::$body = self::success();
        $this->provider()->complete(self::returning(), self::handshake());
        $this->assertSame([[CaBundle::path(null), true]], CasCannedRequest::$certificates, 'the system CAs, and the name checked');

        CasCannedRequest::reset();
        CasCannedRequest::$body = self::success();
        $ca = (string)realpath(self::CA_FIXTURE);
        $this->provider(['ca_cert' => $ca])->complete(self::returning(), self::handshake());
        $this->assertSame([[$ca, true]], CasCannedRequest::$certificates, 'the configured CA');
    }

    public function testConfiguredCaIsUsedElseTheSystemOne(): void
    {
        $this->assertSame('/x', CaBundle::path('/x'));
        $this->assertSame('/x', CaBundle::path(' /x '));
        $system = CaBundle::path(null);
        $this->assertFileIsReadable($system);
        $this->assertSame($system, CaBundle::path(''));
    }

    public function testAnUnreadableConfiguredCaIsAConfigurationError(): void
    {
        $failure = $this->failure(fn () => $this->provider(['ca_cert' => '/no/such/ca.pem'])->begin(self::handshake()));

        $this->assertSame(FailureReason::ConfigurationInvalid, $failure->reason);
        $this->assertStringContainsString('/no/such/ca.pem', $failure->adminDetail);
        $this->assertSessionLeftNothing();
    }

    public function testTheServerUrlIsUsedAsConfigured(): void
    {
        $server = 'http://auth.yeswiki.test/cas';
        $provider = $this->provider(['server_url' => $server . '/']);

        $login = $provider->begin(self::handshake());
        $this->assertInstanceOf(RedirectResponse::class, $login);
        $this->assertSame($server . '/login?service=' . urlencode(self::CALLBACK), $login->getTargetUrl());

        CasCannedRequest::$body = self::success();
        $provider->complete(self::returning(), self::handshake());
        $this->assertSame([$server . '/p3/serviceValidate?service=' . urlencode(self::CALLBACK) . '&ticket=' . self::TICKET], CasCannedRequest::$urls, 'no forced https');
    }

    public function testAServerUrlAndAVersionAreRequired(): void
    {
        foreach ([['server_url' => ''], ['server_url' => 'cas.example.org'], ['server_url' => 'ftp://cas.example.org/cas'], ['version' => '1.0']] as $connection) {
            $failure = $this->failure(fn () => $this->provider($connection)->begin(self::handshake()));

            $this->assertSame(FailureReason::ConfigurationInvalid, $failure->reason, (string)json_encode($connection));
            $this->assertStringContainsString((string)array_key_first($connection), $failure->adminDetail);
        }
        $this->assertSessionLeftNothing();
    }

    public function testTheTypeBuildsAProviderAtTheWikisAddress(): void
    {
        $urls = new UrlFormatter(new ParameterBag(['base_url' => 'http://wiki.yeswiki.test/?', 'rewrite_mode' => false]), new PageContext(), new CurrentRequest());
        $type = new CasType(self::getWiki()->services->get(SessionRequirement::class), $urls);

        $provider = $type->create(ProviderConfig::fromArray('cas', ['type' => 'cas', 'connection' => ['server_url' => self::SERVER]], $type->defaultAttributes()));

        $this->assertInstanceOf(CasProvider::class, $provider);
        $this->assertSame(['id' => 'user', 'email' => 'mail', 'first_name' => 'givenName', 'last_name' => 'sn', 'groups' => 'groups'], $type->defaultAttributes());
        $this->assertSame(['server_url', 'version', 'ca_cert'], array_map(fn ($setting): string => $setting->name(), $type->connectionSettings()));
    }

    /** @param array<string, mixed> $connection */
    private function provider(array $connection = []): CasProvider
    {
        $config = ProviderConfig::fromArray('cas', [
            'type' => 'cas',
            'label' => 'Acme CAS',
            'connection' => $connection + ['server_url' => self::SERVER, 'version' => '3.0'],
        ]);

        return new CasProvider($config, self::getWiki()->services->get(SessionRequirement::class), self::BASE_URL, CasCannedRequest::class);
    }

    private static function handshake(): Handshake
    {
        return new Handshake('cas', 'http://wiki.yeswiki.test/?PagePrincipale', self::CALLBACK, HandshakeMode::Real, time() + 600);
    }

    private static function returning(): Request
    {
        return Request::create(self::CALLBACK . '&ticket=' . self::TICKET);
    }

    private function failure(callable $call): ProviderFailure
    {
        try {
            $call();
        } catch (ProviderFailure $failure) {
            return $failure;
        }
        $this->fail('no ProviderFailure was thrown');
    }

    private function assertSessionLeftNothing(): void
    {
        $this->assertNotSame(PHP_SESSION_ACTIVE, session_status(), 'the session opened for phpCAS is gone');
        $this->assertArrayNotHasKey('phpCAS', $_SESSION ?? []);
    }

    private static function success(): string
    {
        return '<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas"><cas:authenticationSuccess>'
            . '<cas:user>marie</cas:user><cas:attributes>'
            . '<cas:mail>marie.dupont@acme.example</cas:mail><cas:givenName>Marie</cas:givenName><cas:sn>Dupont</cas:sn>'
            . '<cas:groups>wiki-editors</cas:groups><cas:groups>staff</cas:groups>'
            . '</cas:attributes><cas:proxyGrantingTicket>PGTIOU-1-secret</cas:proxyGrantingTicket>'
            . '</cas:authenticationSuccess></cas:serviceResponse>';
    }

    private static function refusal(string $code, string $message): string
    {
        return '<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas"><cas:authenticationFailure code="' . $code . '">'
            . htmlspecialchars($message) . '</cas:authenticationFailure></cas:serviceResponse>';
    }
}

/**
 * The CAS server, as phpCAS's request interface: it answers $body, or fails like curl when it is null.
 * phpCAS makes it with `new`, so what a test sets and reads is static; the test resets it.
 */
class CasCannedRequest extends \CAS_Request_AbstractRequest
{
    public const ERROR = 'CURL error #60: SSL certificate problem: unable to get local issuer certificate';

    public static ?string $body = null;

    /** Thrown instead of answering, when set. */
    public static ?\Throwable $thrown = null;

    /** @var list<string> */
    public static array $urls = [];

    /** @var list<array{0: ?string, 1: bool}> */
    public static array $certificates = [];

    /** @var list<array<int, mixed>> */
    public static array $curlOptions = [];

    public static function reset(): void
    {
        self::$body = null;
        self::$thrown = null;
        self::$urls = [];
        self::$certificates = [];
        self::$curlOptions = [];
    }

    /**
     * What phpCAS hands CAS_Request_CurlRequest, outside the interface.
     *
     * @param array<int, mixed> $options
     */
    public function setCurlOptions(array $options): void
    {
        self::$curlOptions[] = $options;
    }

    protected function sendRequest()
    {
        self::$urls[] = (string)$this->url;
        self::$certificates[] = [$this->caCertPath, $this->validateCN];
        if (self::$thrown !== null) {
            throw self::$thrown;
        }
        $body = self::$body;
        if ($body === null) {
            $this->storeErrorMessage(self::ERROR);

            return false;
        }
        $this->storeResponseHeaders(['HTTP/1.1 200 OK']);
        $this->storeResponseBody($body);

        return true;
    }
}
