<?php

namespace YesWiki\Test\Bazar\Controller;

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The page an entry's edit link returns to keeps the current page and its query, never an earlier return address.
 */
class EntryControllerEditContextTest extends YesWikiTestCase
{
    private $wiki;
    private array $saved = [];

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->saved = [$this->wiki->tag, $this->wiki->method, $_SERVER['QUERY_STRING'] ?? null, $_SERVER['REQUEST_URI'] ?? null];
    }

    protected function tearDown(): void
    {
        [$this->wiki->tag, $this->wiki->method, $_SERVER['QUERY_STRING'], $_SERVER['REQUEST_URI']] = $this->saved;
    }

    private function contextFor(string $tag, string $method, string $query, string $entryTag = 'SomeEntry'): string
    {
        $this->wiki->tag = $tag;
        $this->wiki->method = $method;
        $_SERVER['QUERY_STRING'] = $query;
        $_SERVER['REQUEST_URI'] = '/?' . $query;

        return $this->wiki->services->get(EntryController::class)->getEditContextUrl($entryTag);
    }

    public function testAnEarlierIncomingUrlIsNotCarriedOver()
    {
        $chain = 'SomeEntry%2Feditiframe'
            . '&incomingurl=https%3A%2F%2Fexample.org%2F%3FOtherEntry%2Feditiframe'
            . '&incomingurl=https%3A%2F%2Fexample.org%2Findex.php%3FBazaR%2Fiframe'
            . '&pageID=26&vue=consulter&wiki=BazaR/iframe';

        $url = $this->contextFor('BazaR', 'iframe', $chain);

        $this->assertStringNotContainsStringIgnoringCase('incomingurl', $url);
        $this->assertSame($this->wiki->Href('iframe', 'BazaR', 'pageID=26&vue=consulter', false), $url);
    }

    public function testTheListingPageAndItsQueryAreKept()
    {
        $url = $this->contextFor('ListPage', 'show', 'ListPage&vue=consulter&facette=bf_ville=Brest');

        $this->assertSame($this->wiki->Href('', 'ListPage', 'vue=consulter&facette=bf_ville=Brest', false), $url);
    }

    public function testTheEntryOwnPageNeedsNoContext()
    {
        $this->assertSame('', $this->contextFor('SomeEntry', 'show', 'SomeEntry'));
        $this->assertSame('', $this->contextFor('SomeEntry', 'iframe', 'SomeEntry/iframe'));
    }

    public function testAnEditPageGivesNoContext()
    {
        $this->assertSame('', $this->contextFor('ListPage', 'editiframe', 'ListPage/editiframe&incomingurl=x'));
    }
}
