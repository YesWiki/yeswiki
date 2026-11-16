<?php

namespace YesWiki\Test\Identity\SignIn;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Admin\Service\ApiService;
use YesWiki\Core\YesWikiInit;
use YesWiki\Identity\Service\RequestGuard;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** SAML answers with a POST from the identity server's site: only the callback may take it, and it brings its own proof. */
class SignInCallbackGuardTest extends YesWikiTestCase
{
    private RequestGuard $guard;
    private ?Request $requestWas = null;

    /** @var array<string, mixed> */
    private array $server;

    /** @var array<string, mixed> */
    private array $get;

    protected function setUp(): void
    {
        parent::setUp();
        $services = self::getWiki()->services;
        $this->guard = $services->get(RequestGuard::class);
        $current = $services->get(CurrentRequest::class);
        $this->requestWas = $current->has() ? $current->get() : null;
        $this->server = $_SERVER;
        $this->get = $_GET;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_GET = $this->get;
        self::getWiki()->services->get(PageContext::class)->startNewRequest();
        if ($this->requestWas !== null) {
            self::getWiki()->services->get(CurrentRequest::class)->replace($this->requestWas);
        }
        parent::tearDown();
    }

    public function testACrossSitePostToTheCallbackIsAllowed(): void
    {
        $request = $this->crossSitePost('/user/sign-in/acme/callback');
        $this->assertNull($this->guard->refusal($request));
    }

    public function testACrossSitePostElsewhereIsStillRefused(): void
    {
        $request = $this->crossSitePost('/user');
        $this->assertNotNull($this->guard->refusal($request));
    }

    public function testTheQueryStringFormOfTheCallbackIsAllowed(): void
    {
        $request = $this->crossSitePost('/?user/sign-in/acme/callback');
        $this->assertNull($this->guard->refusal($request));
    }

    /** @return array<string, array{string}> */
    public static function callbackAddresses(): array
    {
        return [
            'the wiki parameter' => ['/?wiki=user/sign-in/x/callback'],
            'the wiki parameter, leading slash' => ['/?wiki=/user/sign-in/x/callback'],
            'through index.php' => ['/index.php?wiki=user/sign-in/x/callback'],
            'parameters after it' => ['/?user/sign-in/x/callback&SAMLResponse=abc'],
            'the equals sign a form can add' => ['/?user/sign-in/x/callback='],
            'the reserved word in capitals' => ['/?USER/sign-in/x/callback'],
            // a server that rebuilds the query as a form (Pocket ID: Go's url.Values.Encode sorts the keys) keeps the callback key last
            'the answer first, the encoded callback key last' => ['/?code=abc&iss=https%3A%2F%2Fidp.example&scope=openid+email&state=xyz&user%2Fsign-in%2Fx%2Fcallback='],
            'the answer first, the callback key last' => ['/?code=abc&state=xyz&user/sign-in/x/callback'],
            // deliberate: a page given a value first gives way to the callback key, which refuses without a sign-in in progress
            'a page given a value first, the callback key later' => ['/?SomePage=x&code=abc&user/sign-in/x/callback'],
            'the callback as built, its pair after the key' => ['/?user/sign-in/x/callback&sso_callback=x&SAMLResponse=abc'],
            // authentik (parse_qs) drops the key without a value and keeps the pair
            'the pair alone, before the answer' => ['/?sso_callback=x&code=abc&state=xyz'],
            'the pair alone, after the answer' => ['/?code=abc&sso_callback=x&state=xyz'],
            // LemonLDAP::NG 2.21 and 2.16 (URI::QueryParam) keep the key encoded
            'the encoded key first, then the pair' => ['/?user%2Fsign-in%2Fx%2Fcallback&sso_callback=x&code=abc'],
            // authentik glues its error onto the pair with a second `?`
            'an error glued onto the pair' => ['/?user/sign-in/x/callback&sso_callback=x?error=invalid_request&state=xyz'],
            'a page given a value first, the pair later' => ['/?SomePage=x&sso_callback=x'],
        ];
    }

    #[DataProvider('callbackAddresses')]
    public function testEveryFormTheDispatcherReadsAsTheCallbackIsAllowed(string $url): void
    {
        $request = $this->crossSitePost($url);
        $this->assertNull($this->guard->refusal($request), $url);
    }

    /** @return array<string, array{string}> */
    public static function urlsThatAreNotTheCallback(): array
    {
        return [
            'more after the callback, path' => ['/user/sign-in/acme/callback/extra'],
            'more after the callback, query' => ['/?user/sign-in/acme/callback/extra'],
            'two segments for the id' => ['/user/sign-in/a/b/callback'],
            'something before, path' => ['/xuser/sign-in/acme/callback'],
            'something before, query' => ['/?xuser/sign-in/acme/callback'],
            'the provider start' => ['/user/sign-in/acme'],
            'the metadata' => ['/user/sign-in/acme/metadata'],
            'no id' => ['/user/sign-in//callback'],
            'not the first query key' => ['/?PagePrincipale&user/sign-in/acme/callback'],
            'a trailing newline' => ['/?user/sign-in/acme/callback%0A'],
            'the callback first, but wiki decides, query' => ['/?user/sign-in/x/callback&wiki=api/users/1'],
            'the callback first, but wiki decides, path' => ['/user/sign-in/x/callback?wiki=api/users/1'],
            'the callback as the first key of a page handler' => ['/SomePage/edit?user/sign-in/x/callback'],
            'wiki names more than the callback' => ['/?wiki=user/sign-in/x/callback/extra'],
            'a page first, the callback key later' => ['/?SomePage=&user/sign-in/x/callback'],
            'a page handler first, the callback key later' => ['/SomePage/edit?code=abc&user/sign-in/x/callback'],
            'two callback keys after the answer' => ['/?code=abc&user/sign-in/a/callback&user/sign-in/b/callback'],
            'more after the callback key, after the answer' => ['/?code=abc&user/sign-in/x/callback/extra'],
            'a value for the callback key, after the answer' => ['/?code=abc&user/sign-in/x/callback=1'],
            'the callback key later, but wiki decides' => ['/?code=abc&user/sign-in/x/callback&wiki=api/users/1'],
            'the callback key later, through index.php' => ['/index.php?code=abc&user/sign-in/x/callback'],
            'the callback key later, after an empty first key' => ['/?=x&code=abc&user/sign-in/x/callback'],
            'a page first, the pair later' => ['/?SomePage&sso_callback=x'],
            'a page with an empty value first, the pair later' => ['/?SomePage=&sso_callback=x'],
            'the pair, through index.php' => ['/index.php?code=abc&sso_callback=x'],
            'the pair after a page handler' => ['/SomePage/edit?code=abc&sso_callback=x'],
            'the pair, but wiki decides' => ['/?code=abc&sso_callback=x&wiki=api/users/1'],
            'two pairs' => ['/?code=abc&sso_callback=a&sso_callback=b'],
            'an empty pair' => ['/?code=abc&sso_callback='],
            'a pair whose identifier has a slash' => ['/?code=abc&sso_callback=a%2Fb'],
            'a pair with an error glued on, without the key' => ['/?code=abc&sso_callback=x?error=invalid_request'],
            'the pair after an empty first key' => ['/?=x&sso_callback=x'],
        ];
    }

    #[DataProvider('urlsThatAreNotTheCallback')]
    public function testOnlyTheCallbackIsLetThrough(string $url): void
    {
        $request = $this->crossSitePost($url);
        $this->assertNotNull($this->guard->refusal($request), $url);
    }

    /** @return array<string, array{string, bool}> */
    public static function routedRequests(): array
    {
        return [
            'the callback, path' => ['/user/sign-in/acme/callback', false],
            'the callback, query' => ['/?user/sign-in/acme/callback', false],
            'the callback, wiki parameter' => ['/?wiki=user/sign-in/x/callback', false],
            'the callback key after the answer' => ['/?code=abc&state=xyz&user%2Fsign-in%2Facme%2Fcallback=', false],
            'the pair alone' => ['/?sso_callback=acme&code=abc&state=xyz', false],
            'the pair after a page' => ['/?SomePage&sso_callback=acme', true],
            'anything else' => ['/user/sign-in/acme/callback/extra', true],
            'the callback first, but wiki decides, query' => ['/?user/sign-in/x/callback&wiki=api/users/1', true],
            'the callback first, but wiki decides, path' => ['/user/sign-in/x/callback?wiki=api/users/1', true],
            'the callback as the first key of a page handler' => ['/SomePage/edit?user/sign-in/x/callback', true],
        ];
    }

    /** The routed requests have a second check of their own, and a callback refused there never reaches its route. */
    #[DataProvider('routedRequests')]
    public function testTheRoutedCheckAgrees(string $url, bool $refused): void
    {
        $services = self::getWiki()->services;
        $services->get(CurrentRequest::class)->replace($this->crossSitePost($url));

        $this->assertSame($refused, $services->get(ApiService::class)->isCrossOriginWrite());
    }

    /** A POST from another site, routed as the runtime routes it: YesWikiInit reads its address, PageContext holds what it read. */
    private function crossSitePost(string $url): Request
    {
        $request = Request::create($url, 'POST', server: ['HTTP_SEC_FETCH_SITE' => 'cross-site', 'SCRIPT_NAME' => '/index.php']);
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_URI'] = $request->server->get('REQUEST_URI');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = $request->query->all();
        $init = (new \ReflectionClass(YesWikiInit::class))->newInstanceWithoutConstructor();
        $init->getRoute();
        $pageContext = self::getWiki()->services->get(PageContext::class);
        $pageContext->setRequestedTag($init->page);
        $pageContext->setMethod((string)$init->method);

        return $request;
    }
}
