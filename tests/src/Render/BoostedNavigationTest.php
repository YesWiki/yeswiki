<?php

namespace YesWiki\Test\Render;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Render\Service\BoostedNavigation;
use YesWiki\Render\Service\ThemeManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 16: internal links load through htmx. */
class BoostedNavigationTest extends YesWikiTestCase
{
    private function boosted(): BoostedNavigation
    {
        return $this->getWiki()->services->get(BoostedNavigation::class);
    }

    /**
     * Put a request in flight with the headers htmx would send.
     *
     * @param array<string,string> $headers
     */
    private function withRequest(array $headers): void
    {
        $request = Request::create('/TestPage');
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }
        $this->getWiki()->services->get(CurrentRequest::class)->replace($request);
    }

    /**
     * @return array<string,string>
     */
    private function boostHeaders(?string $fingerprint = null): array
    {
        return [
            'HX-Request' => 'true',
            'HX-Boosted' => 'true',
            BoostedNavigation::FINGERPRINT_HEADER => $fingerprint ?? $this->boosted()->fingerprint(),
        ];
    }

    protected function tearDown(): void
    {
        $this->getWiki()->services->get(CurrentRequest::class)->replace(Request::create('/'));
        parent::tearDown();
    }

    public function testAnOrdinaryRequestIsNotBoosted(): void
    {
        $this->withRequest([]);
        $this->assertFalse($this->boosted()->isBoosted());
    }

    /** htmx sends HX-Request for every ajax call; only HX-Boosted marks a *navigation*. */
    public function testAnInlineHtmxRequestIsNotABoostedNavigation(): void
    {
        $this->withRequest(['HX-Request' => 'true']);
        $this->assertFalse(
            $this->boosted()->isBoosted(),
            'the tags autocomplete and the admin table are htmx requests but not navigations'
        );
    }

    public function testABoostedNavigationIsRecognised(): void
    {
        $this->withRequest($this->boostHeaders());
        $this->assertTrue($this->boosted()->isBoosted());
    }

    public function testTheFingerprintCoversEverythingOutsideTheBodyBlock(): void
    {
        $themeManager = $this->getWiki()->services->get(ThemeManager::class);
        $identity = $themeManager->layoutIdentity();

        $this->assertCount(9, $identity);
        $this->assertNotSame('', $this->boosted()->fingerprint());
    }

    public function testAMatchingFingerprintIsAccepted(): void
    {
        $this->withRequest($this->boostHeaders());
        $this->assertTrue($this->boosted()->fingerprintMatches());
    }

    public function testADifferentSkeletonIsRejected(): void
    {
        $this->withRequest($this->boostHeaders('not-this-layout'));
        $this->assertFalse($this->boosted()->fingerprintMatches());
    }

    /**
     * A boosted request that sends no fingerprint is a mismatch: a full load is always a correct answer, whereas swapping into an unknown skeleton is not.
     */
    public function testAMissingFingerprintIsRejected(): void
    {
        $this->withRequest(['HX-Request' => 'true', 'HX-Boosted' => 'true']);
        $this->assertFalse($this->boosted()->fingerprintMatches());
    }

    public function testAFullLoadResponseTellsHtmxToNavigateForReal(): void
    {
        $this->withRequest($this->boostHeaders());
        $response = $this->boosted()->fullLoadResponse('/SomewhereElse');

        $this->assertSame(204, $response->getStatusCode(), 'no body is worth sending');
        $this->assertSame('/SomewhereElse', $response->headers->get('HX-Redirect'));
    }

    public function testTheFullLoadDefaultsToTheRequestedUrl(): void
    {
        $this->withRequest($this->boostHeaders());
        $this->assertStringContainsString('/TestPage', (string)$this->boosted()->fullLoadResponse()->headers->get('HX-Redirect'));
    }

    /** The config flag decides whether the skeleton emits hx-boost at all. */
    public function testTheFlagIsOnByDefault(): void
    {
        $this->assertTrue($this->boosted()->isEnabled());
    }

    public function testAFullRenderIsAWholeDocument(): void
    {
        $this->withRequest([]);
        $page = $this->getWiki()->services->get(\YesWiki\Render\Service\TemplateEngine::class)
            ->renderPage('<p>hello</p>');

        $this->assertStringContainsString('<!doctype html>', $page);
        $this->assertStringContainsString('</head>', $page);
        $this->assertStringContainsString('<p>hello</p>', $page);
    }

    /**
     * The fragment is the body block, a top-level <title>, and the declared assets as an out-of-band swap.
     */
    public function testABoostedRenderIsTheBodyBlockOnly(): void
    {
        $this->withRequest($this->boostHeaders());
        $fragment = $this->getWiki()->services->get(\YesWiki\Render\Service\TemplateEngine::class)
            ->renderPage('<p>hello</p>');

        $this->assertStringContainsString('<p>hello</p>', $fragment, 'the page content is there');
        $this->assertStringContainsString('<body', $fragment, 'the body block is what is returned');
        $this->assertStringNotContainsString('<head>', $fragment, 'htmx strips a literal <head> from a fragment');
        $this->assertStringNotContainsString('<!doctype', $fragment);
    }

    public function testABoostedRenderCarriesTheTitleSoHtmxCanApplyIt(): void
    {
        $this->withRequest($this->boostHeaders());
        $fragment = $this->getWiki()->services->get(\YesWiki\Render\Service\TemplateEngine::class)
            ->renderPage('<p>hello</p>');

        $this->assertMatchesRegularExpression('#<title>.+</title>#s', $fragment);
    }

    /** pageTag has to be current after a swap, so the props script rides inside the body. */
    public function testABoostedRenderCarriesThePageStateScript(): void
    {
        $this->withRequest($this->boostHeaders());
        $fragment = $this->getWiki()->services->get(\YesWiki\Render\Service\TemplateEngine::class)
            ->renderPage('<p>hello</p>');

        $this->assertStringContainsString('var wiki = {', $fragment);
        $this->assertStringContainsString('pageTag', $fragment);
    }

    /** Rendering a page is what makes a response swappable; nothing else is. */
    public function testRenderingAPageMarksTheResponseSwappable(): void
    {
        $this->withRequest($this->boostHeaders());
        $this->getWiki()->services->get(\YesWiki\Render\Service\TemplateEngine::class)->renderPage('<p>hi</p>');

        $this->assertTrue($this->boosted()->hasRenderedAPage());
    }
}
