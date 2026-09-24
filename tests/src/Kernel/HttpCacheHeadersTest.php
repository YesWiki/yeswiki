<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Core\YesWikiInit;
use YesWiki\Kernel\Service\HttpCacheHeaders;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\LazySessionTokenStorage;

require_once 'tests/YesWikiTestCase.php';

/** Only anonymous page views may be shared, and only a request that wrote to the session gets one. */
class HttpCacheHeadersTest extends TestCase
{
    private bool $hadSession = false;

    /** @var array<array-key, mixed> */
    private array $session = [];

    /** @var array<string, mixed> */
    private array $server;

    /** @var array<string, mixed> */
    private array $cookie;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->cookie = $_COOKIE;
        $this->hadSession = session_status() === PHP_SESSION_ACTIVE;
        $this->session = $_SESSION ?? [];
        if ($this->hadSession) {
            session_write_close();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SERVER = $this->server;
        $_COOKIE = $this->cookie;
        if ($this->hadSession) {
            @session_start();
        }
        $_SESSION = $this->session;
    }

    public function testAnAnonymousPageViewIsSharedForTheConfiguredTime(): void
    {
        $response = new Response('page');

        $this->headers(60)->decide($this->pageView(), $response);

        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertSame('60', $response->headers->getCacheControlDirective('s-maxage'));
        $this->assertSame('0', $response->headers->getCacheControlDirective('max-age'));
        $this->assertSame('86400', $response->headers->getCacheControlDirective('stale-if-error'));
        $this->assertSame(['Cookie', 'HX-Request'], $response->getVary());
    }

    public function testAFeedIsSharedLikeThePage(): void
    {
        $response = new Response('<rss/>');

        $this->headers(60)->decide($this->pageView('GET', true, 'rss'), $response);

        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
    }

    public function testASecondLanguageMakesTheBrowserLanguagePartOfTheKey(): void
    {
        $response = new Response('page');

        $this->headers(60, ['fr', 'en'])->decide($this->pageView(), $response);

        $this->assertContains('Accept-Language', $response->getVary());
    }

    #[DataProvider('notShared')]
    public function testWhatMustNotBeShared(int $ttl, string $method, bool $pageView, string $handler, int $status, bool $setsCookie): void
    {
        $request = $this->pageView($method, $pageView, $handler);
        $response = new Response('x', $status);
        if ($setsCookie) {
            $response->headers->setCookie(Cookie::create('lang', 'en'));
        }

        $this->headers($ttl)->decide($request, $response);

        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    /** @return array<string, array{int, string, bool, string, int, bool}> */
    public static function notShared(): array
    {
        return [
            'caching is off by default' => [0, 'GET', true, 'show', 200, false],
            'a form submission' => [60, 'POST', true, 'show', 200, false],
            'the editor' => [60, 'GET', true, 'edit', 200, false],
            'a routed screen' => [60, 'GET', false, '', 200, false],
            'a server error' => [60, 'GET', true, 'show', 500, false],
            'a response setting a cookie' => [60, 'GET', true, '', 200, true],
        ];
    }

    public function testAnOpenSessionMakesTheResponsePrivateAndUnstored(): void
    {
        session_start();
        $response = new Response('page');

        $this->headers(60)->decide($this->pageView(), $response);

        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testNothingWrittenMeansNoSession(): void
    {
        $_SESSION = ['flash_messages' => [], '_csrf' => []];

        $this->assertFalse(HttpCacheHeaders::persistDeferredSession());
        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testWhatARequestWroteIsKeptWhenTheSessionOpensLate(): void
    {
        (new LazySessionTokenStorage())->setToken('main', 'abc');
        $_SESSION['message'] = 'Enregistré';

        $this->assertSame(PHP_SESSION_NONE, session_status(), 'writing a token must not open a session by itself');
        $this->assertTrue(HttpCacheHeaders::persistDeferredSession());
        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        $this->assertSame('abc', $_SESSION['_csrf']['main']);
        $this->assertSame('Enregistré', $_SESSION['message']);
    }

    public function testASessionOpensUpFrontOnlyForAReturningVisitorOrAChange(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_COOKIE['YesWiki-main']);
        $this->assertFalse(YesWikiInit::needsSessionNow('YesWiki-main'));

        $_COOKIE['YesWiki-main'] = 'someid';
        $this->assertTrue(YesWikiInit::needsSessionNow('YesWiki-main'));

        unset($_COOKIE['YesWiki-main']);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertTrue(YesWikiInit::needsSessionNow('YesWiki-main'));
    }

    /** @param list<string> $languages */
    private function headers(int $ttl, array $languages = ['fr']): HttpCacheHeaders
    {
        $language = $this->createStub(LanguageService::class);
        $language->method('availableLanguages')->willReturn($languages);

        return new HttpCacheHeaders(new ParameterBag([HttpCacheHeaders::TTL => $ttl]), $language);
    }

    private function pageView(string $method = 'GET', bool $pageView = true, string $handler = ''): Request
    {
        $request = Request::create('http://wiki.example/?PagePrincipale', $method);
        if ($pageView) {
            $request->attributes->set('_tag', 'PagePrincipale');
        }
        $request->attributes->set('_method', $handler);

        return $request;
    }
}
