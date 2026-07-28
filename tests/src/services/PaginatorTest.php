<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Service\Paginator;


/**
 * Ticket 02 replaced 1706 lines of vendored PEAR Pager with Paginator. These pin the
 * behaviour the one caller (BazarListeAction) actually relied on: the current page's
 * slice, PEAR "Jumping"-mode page blocks, and `pageID`-keyed links.
 */
class PaginatorTest extends TestCase
{
    /**
     * @return list<int>
     */
    private static function items(int $n): array
    {
        return range(1, $n);
    }

    public function testGetPageDataReturnsTheRequestedSlice(): void
    {
        $p = new Paginator(self::items(25), 10, 2);

        $this->assertSame(range(11, 20), $p->getPageData());
        $this->assertSame(3, $p->getTotalPages());
        $this->assertSame(2, $p->getCurrentPage());
    }

    public function testLastPageMayBeShorterThanPerPage(): void
    {
        $p = new Paginator(self::items(25), 10, 3);

        $this->assertSame([21, 22, 23, 24, 25], $p->getPageData());
    }

    public function testCurrentPageIsClampedIntoRange(): void
    {
        $this->assertSame(3, (new Paginator(self::items(25), 10, 99))->getCurrentPage());
        $this->assertSame(1, (new Paginator(self::items(25), 10, -4))->getCurrentPage());
    }

    public function testEmptyItemListStillHasOnePage(): void
    {
        $p = new Paginator([], 10, 1);

        $this->assertSame(1, $p->getTotalPages());
        $this->assertSame([], $p->getPageData());
        $this->assertSame('', $p->renderLinks('http://x/index.php'));
    }

    public function testASinglePageRendersNoLinks(): void
    {
        $this->assertSame('', (new Paginator(self::items(5), 10, 1))->renderLinks('http://x/index.php'));
    }

    /**
     * PEAR's "Jumping" mode (BAZ_MODE_DIVISION's value) shows page numbers in fixed
     * blocks of `delta` rather than sliding a window around the current page: with
     * delta=5, page 3 shows 1-5 and page 6 shows 6-10.
     */
    public function testJumpingModeShowsFixedBlocksNotASlidingWindow(): void
    {
        $items = self::items(100); // 10 pages at perPage=10

        $this->assertSame([1, 2, 3, 4, 5], (new Paginator($items, 10, 3, 5))->getPageRange());
        $this->assertSame([6, 7, 8, 9, 10], (new Paginator($items, 10, 6, 5))->getPageRange());
    }

    public function testPageRangeIsTruncatedAtTheLastPage(): void
    {
        // 7 pages, delta 5 -> second block is 6..7, not 6..10
        $this->assertSame([6, 7], (new Paginator(self::items(65), 10, 6, 5))->getPageRange());
    }

    public function testLinksCarryExtraVarsAndOverwritePageId(): void
    {
        $html = (new Paginator(self::items(30), 10, 2))
            ->renderLinks('http://x/index.php', ['form' => '3', 'pageID' => '2']);

        $this->assertStringContainsString('form=3', $html);
        $this->assertStringContainsString('pageID=1', $html);
        $this->assertStringContainsString('pageID=3', $html);
        // the stale pageID from the incoming query must not survive alongside the new one
        $this->assertSame(1, substr_count($html, 'pageID'.'=2'));
    }

    public function testCurrentPageIsMarkedActiveAndPrevNextAppearOnlyWhenReachable(): void
    {
        $first = (new Paginator(self::items(30), 10, 1))->renderLinks('http://x/index.php', [], ['prev' => 'P', 'next' => 'N']);
        $middle = (new Paginator(self::items(30), 10, 2))->renderLinks('http://x/index.php', [], ['prev' => 'P', 'next' => 'N']);
        $last = (new Paginator(self::items(30), 10, 3))->renderLinks('http://x/index.php', [], ['prev' => 'P', 'next' => 'N']);

        $this->assertStringNotContainsString('>P<', $first, 'no prev link on the first page');
        $this->assertStringContainsString('>N<', $first);
        $this->assertStringContainsString('>P<', $middle);
        $this->assertStringContainsString('>N<', $middle);
        $this->assertStringContainsString('>P<', $last);
        $this->assertStringNotContainsString('>N<', $last, 'no next link on the last page');

        $this->assertStringContainsString('yw-pagination__item--active', $middle);
        $this->assertStringNotContainsString('class="pagination"', $middle, 'Bootstrap markup must not come back');
    }

    public function testUrlsAreHtmlEscaped(): void
    {
        $html = (new Paginator(self::items(30), 10, 1))
            ->renderLinks('http://x/index.php', ['q' => 'a&b']);

        $this->assertStringContainsString('&amp;', $html);
        $this->assertStringNotContainsString('"a&b"', $html);
    }

    public function testPageFromQueryToleratesGarbage(): void
    {
        $this->assertSame(1, Paginator::pageFromQuery([]));
        $this->assertSame(4, Paginator::pageFromQuery(['pageID' => '4']));
        $this->assertSame(1, Paginator::pageFromQuery(['pageID' => 'not-a-number']));
        $this->assertSame(1, Paginator::pageFromQuery(['pageID' => '-3']));
    }
}
