<?php

namespace YesWiki\Test\Content;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The page an entry's edit link returns to keeps the current page and its query, never an earlier return address. */
class EntryControllerEditContextTest extends YesWikiTestCase
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

    private function contextFor(string $tag, string $method, string $query, string $entryTag = 'SomeEntry'): string
    {
        $services = $this->getWiki()->services;
        $services->get(CurrentRequest::class)->replace(Request::create('/?' . $query));
        $services->get(PageContext::class)->setTag($tag);
        $services->get(PageContext::class)->setMethod($method);

        return $services->get(EntryController::class)->getEditContextUrl($entryTag);
    }

    private function href(string $method, string $tag, string $query): string
    {
        return $this->getWiki()->services->get(UrlFormatter::class)->href($method, $tag, $query, false);
    }

    public function testAnEarlierIncomingUrlIsNotCarriedOver(): void
    {
        $chain = 'SomeEntry%2Feditiframe'
            . '&incomingurl=https%3A%2F%2Fexample.org%2F%3FOtherEntry%2Feditiframe'
            . '&incomingurl=https%3A%2F%2Fexample.org%2Findex.php%3FBazaR%2Fiframe'
            . '&pageID=26&vue=consulter&wiki=BazaR/iframe';

        $url = $this->contextFor('BazaR', 'iframe', $chain);

        $this->assertStringNotContainsStringIgnoringCase('incomingurl', $url);
        $this->assertSame($this->href('iframe', 'BazaR', 'pageID=26&vue=consulter'), $url);
    }

    public function testTheListingPageAndItsQueryAreKept(): void
    {
        $url = $this->contextFor('ListPage', 'show', 'ListPage&vue=consulter&facette=bf_ville');

        $this->assertSame($this->href('', 'ListPage', 'vue=consulter&facette=bf_ville'), $url);
    }

    public function testTheEntryOwnPageNeedsNoContext(): void
    {
        $this->assertSame('', $this->contextFor('SomeEntry', 'show', 'SomeEntry'));
        $this->assertSame('', $this->contextFor('SomeEntry', 'iframe', 'SomeEntry/iframe'));
    }

    public function testAnEditPageGivesNoContext(): void
    {
        $this->assertSame('', $this->contextFor('ListPage', 'editiframe', 'ListPage/editiframe&incomingurl=x'));
    }
}
