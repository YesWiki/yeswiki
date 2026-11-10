<?php

namespace YesWiki\Test\Render;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Action\BreadcrumbAction;
use YesWiki\Render\Action\RedirectAction;
use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Replicas 15: a chain of `{{redirect}}` is carried by the URL and the breadcrumb trail by the tab, so neither needs a session. */
#[CoversClass(RedirectAction::class)]
#[CoversClass(BreadcrumbAction::class)]
class RedirectChainTest extends YesWikiTestCase
{
    private Request $requestWas;

    /** @var array{tag: string, requested: ?string, method: string} */
    private array $pageWas;

    protected function setUp(): void
    {
        parent::setUp();
        $services = self::getWiki()->services;
        $this->requestWas = $services->get(CurrentRequest::class)->get();
        $page = $services->get(PageContext::class);
        $this->pageWas = ['tag' => $page->getTag(), 'requested' => $page->getRequestedTag(), 'method' => (string)$page->getMethod()];
    }

    protected function tearDown(): void
    {
        $services = self::getWiki()->services;
        $services->get(CurrentRequest::class)->replace($this->requestWas);
        $page = $services->get(PageContext::class);
        $page->setTag($this->pageWas['tag']);
        $page->setRequestedTag($this->pageWas['requested'] ?: null);
        $page->setMethod($this->pageWas['method']);
        parent::tearDown();
    }

    public function testTheChainIsReadFromTheUrl(): void
    {
        $this->assertSame(['pagea', 'pageb'], RedirectAction::chain(' PageA, ,PageB '));
        $this->assertSame([], RedirectAction::chain(''));
    }

    public function testARedirectBackToAPageOfTheChainStopsWithTheLoopMessage(): void
    {
        $html = $this->redirectFrom('PageB', 'PageA', 'pagea');

        $this->assertStringContainsString(_t('CIRCULAR_REDIRECTION_FROM_PAGE'), $html);
    }

    public function testAChainLongerThanFiveStops(): void
    {
        $html = $this->redirectFrom('PageF', 'PageG', 'pagea,pageb,pagec,paged,pagee');

        $this->assertStringContainsString(_t('CIRCULAR_REDIRECTION_FROM_PAGE'), $html);
    }

    public function testTheBreadcrumbShowsTheCurrentPageAndLeavesTheTrailToTheTab(): void
    {
        $page = self::getWiki()->services->get(PageContext::class);
        $page->setTag('PageCourante');

        $html = self::getWiki()->services->get(Performer::class)->run('breadcrumb', 'action', ['nb' => '3']);

        $this->assertStringContainsString('data-yw-breadcrumbs-current="PageCourante"', $html);
        $this->assertStringContainsString('data-yw-breadcrumbs-max="3"', $html);
        $this->assertStringContainsString('<li class="yw-breadcrumb__active">PageCourante</li>', $html);
        $this->assertStringContainsString('__YW_BREADCRUMB_TAG__', $html, 'the script builds the links of the trail');
    }

    private function redirectFrom(string $current, string $target, string $from): string
    {
        $services = self::getWiki()->services;
        $services->get(CurrentRequest::class)->replace(new Request([RedirectAction::FROM => $from]));
        $page = $services->get(PageContext::class);
        $page->setTag($current);
        $page->setRequestedTag($current);
        $page->setMethod('show');

        return $services->get(Performer::class)->run('redirect', 'action', ['page' => $target]);
    }
}
