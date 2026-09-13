<?php

namespace YesWiki\Test\Kernel;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `UrlFormatter::currentWith()`: this page, with one parameter changed, and nothing else added. */
class CurrentUrlWithParamsTest extends YesWikiTestCase
{
    private string $previousTag = '';

    private string $previousMethod = '';

    protected function setUp(): void
    {
        parent::setUp();

        $pageContext = $this->getWiki()->services->get(PageContext::class);
        $this->previousTag = $pageContext->getTag();
        $this->previousMethod = $pageContext->getRawMethod();
    }

    protected function tearDown(): void
    {
        $pageContext = $this->getWiki()->services->get(PageContext::class);
        $pageContext->setTag($this->previousTag);
        $pageContext->setMethod($this->previousMethod);

        parent::tearDown();
    }

    /**
     * Serve a request whose query is $query, on $tag/$method.
     *
     * @param array<string, mixed> $query
     */
    private function on(string $tag, string $method, array $query): void
    {
        $wiki = $this->getWiki();
        $wiki->services->get(CurrentRequest::class)->replace(new Request($query));
        $wiki->services->get(PageContext::class)->setTag($tag);
        $wiki->services->get(PageContext::class)->setMethod($method);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function currentWith(array $params): string
    {
        return $this->getWiki()->services->get(UrlFormatter::class)->currentWith($params);
    }

    /** `?PageName` reaches PHP as a parameter of that name with no value. */
    public function testThePageAddressIsNotCarriedIntoTheLink(): void
    {
        $this->on('PagePrincipale', 'show', ['PagePrincipale' => '', 'wiki' => 'PagePrincipale/show']);

        $url = $this->currentWith(['lang' => 'fr']);

        $this->assertStringEndsWith('?PagePrincipale&lang=fr', $url);
    }

    /** The default handler is what a bare tag already means, so it is left out. */
    public function testTheDefaultHandlerIsLeftOutOfTheLink(): void
    {
        $this->on('PagePrincipale', 'show', []);

        $this->assertStringNotContainsString('/show', $this->currentWith(['lang' => 'fr']));
    }

    public function testAnyOtherHandlerIsKept(): void
    {
        $this->on('PagePrincipale', 'iframe', []);

        $this->assertStringContainsString('PagePrincipale/iframe', $this->currentWith(['lang' => 'fr']));
    }

    /** The shape the bug reported: the address piling up one segment per click. */
    public function testTheAddressDoesNotAccumulateAcrossClicks(): void
    {
        $this->on('PagePrincipale', 'show', [
            'PagePrincipale/show' => '',
            'PagePrincipale' => '',
            'wiki' => 'PagePrincipale/show',
            'lang' => 'fr',
        ]);

        $url = $this->currentWith(['lang' => 'en']);

        $this->assertStringEndsWith('?PagePrincipale&lang=en', $url);
        $this->assertStringNotContainsString('%2F', $url);
    }

    /** A switch on a filtered list has to keep the filter. */
    public function testAGenuineParameterIsKept(): void
    {
        $this->on('Annuaire', 'show', ['PagePrincipale' => '', 'facette' => 'bf_ville=Lyon']);

        $url = $this->currentWith(['lang' => 'en']);

        $this->assertStringContainsString('facette=bf_ville', $url);
        $this->assertStringContainsString('lang=en', $url);
    }

    /** A bazar filter arrives as an array, which href()'s own encoding cannot take. */
    public function testAnArrayParameterSurvives(): void
    {
        $this->on('Annuaire', 'show', ['q' => ['bf_ville' => 'Lyon']]);

        $url = $this->currentWith(['lang' => 'en']);

        $this->assertStringContainsString('q%5Bbf_ville%5D=Lyon', $url);
    }

    public function testTheAskedParameterReplacesTheOneAlreadyThere(): void
    {
        $this->on('PagePrincipale', 'show', ['lang' => 'fr']);

        $url = $this->currentWith(['lang' => 'es']);

        $this->assertStringContainsString('lang=es', $url);
        $this->assertStringNotContainsString('lang=fr', $url);
    }
}
