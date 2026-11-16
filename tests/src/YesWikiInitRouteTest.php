<?php

namespace YesWiki\Test\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Core\YesWikiInit;

require_once 'tests/YesWikiTestCase.php';

/** In worker mode one YesWikiInit routes every request, so a route must never survive into the next one (ADR-0024). */
class YesWikiInitRouteTest extends YesWikiTestCase
{
    /** @var array<string, mixed> */
    private array $server;

    /** @var array<string, mixed> */
    private array $get;

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
        $this->get = $_GET;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_GET = $this->get;
        parent::tearDown();
    }

    public function testTheRootAfterAPageIsTheRootAgain(): void
    {
        $init = (new \ReflectionClass(YesWikiInit::class))->newInstanceWithoutConstructor();

        $this->route($init, '/?ParcoursA/edit');
        $this->assertSame(['ParcoursA', 'edit'], [$init->page, $init->method]);

        $this->route($init, '/');
        $this->assertSame(['', ''], [$init->page, $init->method], 'the previous request must not answer for this one');
    }

    public function testTheWorkerScriptIsNotAPageName(): void
    {
        $init = (new \ReflectionClass(YesWikiInit::class))->newInstanceWithoutConstructor();

        $this->route($init, '/worker.php');

        $this->assertSame('', $init->page);
    }

    /** A server that rebuilds the callback's query as a form, keys sorted (Pocket ID), puts the wiki's callback key after its answer. */
    public function testTheSignInCallbackKeyAfterTheAnswerIsTheRoute(): void
    {
        $init = (new \ReflectionClass(YesWikiInit::class))->newInstanceWithoutConstructor();

        $this->route($init, '/?code=abc&iss=https%3A%2F%2Fidp.example&scope=openid+email&state=xyz&user%2Fsign-in%2Fpocketid%2Fcallback=');
        $this->assertSame(['user', 'sign-in/pocketid/callback'], [$init->page, $init->method]);

        $this->route($init, '/?code=abc&state=xyz&user/sign-in/pocketid/callback');
        $this->assertSame(['user', 'sign-in/pocketid/callback'], [$init->page, $init->method]);
    }

    /** Only the sign-in callback is looked for after the first key: any other later key leaves the route to the first one. */
    public function testALaterKeyThatIsNotTheSignInCallbackIsNotTheRoute(): void
    {
        $init = (new \ReflectionClass(YesWikiInit::class))->newInstanceWithoutConstructor();

        $this->route($init, '/?code=abc&SomePage/edit');
        $this->assertSame(['code', ''], [$init->page, $init->method]);

        $this->route($init, '/?code=abc&user/sign-in/pocketid');
        $this->assertSame(['code', ''], [$init->page, $init->method]);

        $this->route($init, '/?SomePage&user/sign-in/pocketid/callback');
        $this->assertSame(['SomePage', ''], [$init->page, $init->method]);
    }

    /**
     * A page given a value first gives way to a later sign-in callback key: deliberate, and harmless, since the callback refuses
     * whatever has no sign-in in progress (SignInFlowTest::testNoValidHandshakeIsARefusedPageNotACrash).
     */
    public function testAPageGivenAValueFirstGivesWayToALaterSignInCallbackKey(): void
    {
        $init = (new \ReflectionClass(YesWikiInit::class))->newInstanceWithoutConstructor();

        $this->route($init, '/?SomePage=x&code=abc&user/sign-in/pocketid/callback');
        $this->assertSame(['user', 'sign-in/pocketid/callback'], [$init->page, $init->method]);
    }

    /** The callback key is only looked for after a named first key, in the root query: index.php and an empty first key keep their route. */
    public function testIndexPhpAndAnEmptyFirstKeyKeepTheirRoute(): void
    {
        $init = (new \ReflectionClass(YesWikiInit::class))->newInstanceWithoutConstructor();

        $this->route($init, '/index.php?code=abc&user/sign-in/pocketid/callback');
        $this->assertSame(['', ''], [$init->page, $init->method]);

        $this->route($init, '/?=x&code=abc&user/sign-in/pocketid/callback');
        $this->assertSame(['', ''], [$init->page, $init->method]);
    }

    /**
     * A server that rebuilds the callback's query and drops its key without a value (authentik: Python's parse_qs; LemonLDAP::NG
     * 2.21 and 2.16: Perl's URI::QueryParam) keeps the `sso_callback=<id>` pair the callback address carries: it names the route.
     */
    public function testTheSignInCallbackNamedOnlyByItsPairIsTheRoute(): void
    {
        $init = (new \ReflectionClass(YesWikiInit::class))->newInstanceWithoutConstructor();

        // authentik: the key gone, the pair first, its answer after it
        $this->route($init, '/?sso_callback=authentik-oidc&code=XYZ&state=ABC');
        $this->assertSame(['user', 'sign-in/authentik-oidc/callback'], [$init->page, $init->method]);

        $this->route($init, '/?code=XYZ&sso_callback=authentik-oidc&state=ABC');
        $this->assertSame(['user', 'sign-in/authentik-oidc/callback'], [$init->page, $init->method]);

        // a page given a value first gives way to the pair, as it does to a later callback key: the callback refuses without a sign-in in progress
        $this->route($init, '/?SomePage=x&sso_callback=acme');
        $this->assertSame(['user', 'sign-in/acme/callback'], [$init->page, $init->method]);
    }

    /** The other forms the callback comes back in, from the address as built, keep their route. */
    public function testTheCallbackKeyStillNamesTheRoute(): void
    {
        $init = (new \ReflectionClass(YesWikiInit::class))->newInstanceWithoutConstructor();

        $this->route($init, '/?user/sign-in/acme/callback&sso_callback=acme&code=XYZ&state=ABC');
        $this->assertSame(['user', 'sign-in/acme/callback'], [$init->page, $init->method]);

        // LemonLDAP::NG 2.21 and 2.16 keep a key without a value only once encoded
        $this->route($init, '/?user%2Fsign-in%2Flemonldap-oidc%2Fcallback&sso_callback=lemonldap-oidc&code=XYZ&state=ABC&session_state=SS');
        $this->assertSame(['user', 'sign-in/lemonldap-oidc/callback'], [$init->page, $init->method]);

        // authentik appends an error with a second `?`, after the pair: the key is still first
        $this->route($init, '/?user/sign-in/authentik-oidc/callback&sso_callback=authentik-oidc?error=invalid_request&error_description=Invalid%20grant_type%20for%20provider&state=ABC');
        $this->assertSame(['user', 'sign-in/authentik-oidc/callback'], [$init->page, $init->method]);

        // the key wins over a pair that names another provider
        $this->route($init, '/?user/sign-in/acme/callback&sso_callback=other&code=XYZ');
        $this->assertSame(['user', 'sign-in/acme/callback'], [$init->page, $init->method]);
    }

    /** The pair names the callback only at the root, after an answer, alone and with an identifier the route can take. */
    public function testAnSsoCallbackPairElsewhereLeavesTheRouteAlone(): void
    {
        $init = (new \ReflectionClass(YesWikiInit::class))->newInstanceWithoutConstructor();

        $this->route($init, '/?SomePage&sso_callback=acme');
        $this->assertSame(['SomePage', ''], [$init->page, $init->method]);

        $this->route($init, '/?SomePage=&sso_callback=acme');
        $this->assertSame(['SomePage', ''], [$init->page, $init->method]);

        $this->route($init, '/?SomePage/edit&sso_callback=acme');
        $this->assertSame(['SomePage', 'edit'], [$init->page, $init->method]);

        $this->route($init, '/index.php?code=XYZ&sso_callback=acme');
        $this->assertSame(['', ''], [$init->page, $init->method]);

        $this->route($init, '/SomePage/edit?code=XYZ&sso_callback=acme');
        $this->assertSame(['SomePage', 'edit'], [$init->page, $init->method]);

        foreach ([
            '/?code=XYZ&sso_callback=a&sso_callback=b',
            '/?code=XYZ&sso_callback=',
            '/?code=XYZ&sso_callback=a%2Fb',
            '/?code=XYZ&sso_callback=a?error=x',
            '/?code=XYZ&Sso_Callback=acme',
        ] as $requestUri) {
            $this->route($init, $requestUri);
            $this->assertSame(['code', ''], [$init->page, $init->method], $requestUri);
        }
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function signInCallbackAddresses(): array
    {
        return [
            'the callback' => ['/?user/sign-in/acme/callback', true],
            'the callback, its page named in another case' => ['/?User/sign-in/acme/callback', true],
            'its key after the answer' => ['/?code=c&state=s&user%2Fsign-in%2Facme%2Fcallback=', true],
            'the callback as built' => ['/?user/sign-in/acme/callback&sso_callback=acme', true],
            'only its pair, the key dropped' => ['/?sso_callback=acme&code=c&state=s', true],
            'its pair after a page' => ['/?PagePrincipale&sso_callback=acme', false],
            'the start of a sign-in' => ['/?user/sign-in/acme', false],
            'the metadata' => ['/?user/sign-in/acme/metadata', false],
            'one segment too many' => ['/?user/sign-in/a/b/callback', false],
            'a page' => ['/?PagePrincipale', false],
            'the root' => ['/?', false],
        ];
    }

    /** Where an identity server's answer is routed: to the sign-in callback, whatever order the server gave the query. */
    #[DataProvider('signInCallbackAddresses')]
    public function testTheAnswerIsRoutedToTheSignInCallback(string $requestUri, bool $isCallback): void
    {
        $init = (new \ReflectionClass(YesWikiInit::class))->newInstanceWithoutConstructor();

        $this->route($init, $requestUri);

        $this->assertSame($isCallback, $init->page === 'user' && preg_match('#^sign-in/[^/]+/callback$#D', (string)$init->method) === 1, $requestUri);
    }

    private function route(YesWikiInit $init, string $requestUri): void
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_URI'] = $requestUri;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        $init->getRoute();
    }
}
