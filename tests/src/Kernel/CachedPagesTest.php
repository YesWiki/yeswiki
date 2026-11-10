<?php

namespace YesWiki\Test\Kernel;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Performable\ActionRegistry;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\HttpCacheHeaders;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PersonalResponse;
use YesWiki\Kernel\Service\StaleCacheAnnouncer;
use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Replicas 17: what a shared cache keeps is the same for everyone, so no response carrying something personal is one it may keep. */
class CachedPagesTest extends YesWikiTestCase
{
    private const TOKEN = '/name="csrf-token"|\b\d{10}\.[A-Za-z0-9_-]{43}\b/';

    private Request $requestWas;

    private string $tagWas;

    /** @var array<string, mixed> */
    private array $cookiesWere;

    protected function setUp(): void
    {
        parent::setUp();
        $services = self::getWiki()->services;
        $this->requestWas = $services->get(CurrentRequest::class)->get();
        $this->tagWas = $services->get(PageContext::class)->getTag();
        $this->cookiesWere = $_COOKIE;
    }

    protected function tearDown(): void
    {
        $services = self::getWiki()->services;
        $services->get(CurrentRequest::class)->replace($this->requestWas);
        $services->get(PageContext::class)->setTag($this->tagWas);
        $_COOKIE = $this->cookiesWere;
        parent::tearDown();
    }

    public function testIssuingAnyTokenMakesTheResponseSomebodysOwn(): void
    {
        $services = self::getWiki()->services;
        self::actAs(null);
        $personal = $services->get(PersonalResponse::class);
        $personal->startNewRequest();
        $_COOKIE = [];

        $services->get(CsrfTokenManager::class)->getToken('any-form');

        $this->assertTrue($personal->isMarked(), 'a token is bound to its visitor, so the page that carries it is theirs alone');
    }

    /** A guard for later: today no action gives an anonymous reader a token with its default arguments, and one that starts to must say so. */
    public function testNoActionGivesAnAnonymousReaderATokenInAResponseACacheCouldKeep(): void
    {
        $services = self::getWiki()->services;
        self::actAs(null);
        $services->get(PageContext::class)->setTag('PagePrincipale');
        $personal = $services->get(PersonalResponse::class);
        $unmarked = [];
        foreach ($services->get(ActionRegistry::class)->names('action') as $action) {
            $personal->startNewRequest();
            $_COOKIE = [];
            $services->get(CurrentRequest::class)->replace(new Request());
            $html = '';
            ob_start();
            try {
                $html = $services->get(Performer::class)->run($action, 'action', []);
            } catch (\Throwable) {
            } finally {
                $html .= (string)ob_get_clean();
            }
            if (preg_match(self::TOKEN, $html) === 1 && !$personal->isMarked()) {
                $unmarked[] = $action;
            }
        }

        $this->assertSame([], $unmarked, 'these actions render a token for an anonymous reader and leave the response public');
    }

    public function testAResponseWithATokenIsPrivate(): void
    {
        $personal = new PersonalResponse();
        $personal->mark();
        $headers = new HttpCacheHeaders(new ParameterBag([HttpCacheHeaders::TTL => 60]), self::getWiki()->services->get(LanguageService::class), $personal);
        $request = Request::create('http://wiki.example/?PagePrincipale');
        $request->attributes->set('_tag', 'PagePrincipale');
        $request->attributes->set('_method', '');
        $response = new Response('page');

        $headers->decide($request, $response);

        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testOnlyALanguageReadFromTheBrowserMakesItPartOfTheKey(): void
    {
        $languages = self::getWiki()->services->get(LanguageService::class);

        $languages->startNewRequest();
        $_COOKIE[LanguageService::COOKIE] = 'en';
        $languages->detectPreferredLanguage('', ['fr', 'en'], 'en-US,en;q=0.8');
        $this->assertFalse($languages->dependsOnAcceptLanguage(), 'the yw-lang cookie chose it, and Cookie is already in Vary');

        $languages->startNewRequest();
        unset($_COOKIE[LanguageService::COOKIE]);
        $languages->detectPreferredLanguage('', ['fr', 'en'], 'en-US,en;q=0.8');
        $this->assertTrue($languages->dependsOnAcceptLanguage());

        $languages->startNewRequest();
        $languages->detectPreferredLanguage('', ['fr'], 'en-US,en;q=0.8');
        $this->assertFalse($languages->dependsOnAcceptLanguage(), 'one language is the same for every browser');
    }

    public function testASavedPageAnnouncesTheAddressesACacheShouldForget(): void
    {
        $services = self::getWiki()->services;
        $heard = new class {
            /** @var list<array<string, mixed>> */
            public array $events = [];

            public function record(Event $event): void
            {
                $this->events[] = $event->getData();
            }
        };
        $events = $services->get(EventDispatcher::class);
        $listener = $heard->record(...);
        $events->addListener(StaleCacheAnnouncer::EVENT, $listener);
        try {
            $quiet = new StaleCacheAnnouncer(new ParameterBag([HttpCacheHeaders::TTL => 0]), $services);
            $quiet->announce(new Event(['id' => 'PageEnregistree']));
            $this->assertSame(0, count($heard->events), 'nothing is announced while no cache keeps the pages');

            (new StaleCacheAnnouncer(new ParameterBag([HttpCacheHeaders::TTL => 60]), $services))->announce(new Event(['id' => 'PageEnregistree']));
            $this->assertCount(1, $heard->events);
            $announced = $heard->events[0];
            $this->assertSame('PageEnregistree', $announced['tag']);
            $this->assertNotEmpty(array_filter($announced['urls'], static fn (string $url): bool => str_contains($url, 'PageEnregistree')));
        } finally {
            $events->removeListener(StaleCacheAnnouncer::EVENT, $listener);
        }
    }
}
