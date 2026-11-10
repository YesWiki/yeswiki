<?php

namespace YesWiki\Test\Identity\SignIn;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Admin\Service\ApiService;
use YesWiki\Identity\Service\RequestGuard;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** SAML answers with a POST from the identity server's site: only the callback may take it, and it brings its own proof. */
class SignInCallbackGuardTest extends YesWikiTestCase
{
    private RequestGuard $guard;
    private ?Request $requestWas = null;

    protected function setUp(): void
    {
        parent::setUp();
        $services = self::getWiki()->services;
        $this->guard = $services->get(RequestGuard::class);
        $current = $services->get(CurrentRequest::class);
        $this->requestWas = $current->has() ? $current->get() : null;
    }

    protected function tearDown(): void
    {
        if ($this->requestWas !== null) {
            self::getWiki()->services->get(CurrentRequest::class)->replace($this->requestWas);
        }
        parent::tearDown();
    }

    public function testACrossSitePostToTheCallbackIsAllowed(): void
    {
        $request = Request::create('/user/sign-in/acme/callback', 'POST', server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        $this->assertNull($this->guard->refusal($request));
    }

    public function testACrossSitePostElsewhereIsStillRefused(): void
    {
        $request = Request::create('/user', 'POST', server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        $this->assertNotNull($this->guard->refusal($request));
    }

    public function testTheQueryStringFormOfTheCallbackIsAllowed(): void
    {
        $request = Request::create('/?user/sign-in/acme/callback', 'POST', server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        $this->assertNull($this->guard->refusal($request));
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
        ];
    }

    #[DataProvider('urlsThatAreNotTheCallback')]
    public function testOnlyTheCallbackIsLetThrough(string $url): void
    {
        $request = Request::create($url, 'POST', server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        $this->assertNotNull($this->guard->refusal($request), $url);
    }

    /** @return array<string, array{string, bool}> */
    public static function routedRequests(): array
    {
        return [
            'the callback, path' => ['/user/sign-in/acme/callback', false],
            'the callback, query' => ['/?user/sign-in/acme/callback', false],
            'anything else' => ['/user/sign-in/acme/callback/extra', true],
        ];
    }

    /** The routed requests have a second check of their own, and a callback refused there never reaches its route. */
    #[DataProvider('routedRequests')]
    public function testTheRoutedCheckAgrees(string $url, bool $refused): void
    {
        $services = self::getWiki()->services;
        $services->get(CurrentRequest::class)->replace(Request::create($url, 'POST', server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']));

        $this->assertSame($refused, $services->get(ApiService::class)->isCrossOriginWrite());
    }
}
