<?php

namespace YesWiki\Test\Actions;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for ticket 13 (toc absorbed into core), rewritten by ticket 06.
 *
 * Heading ids used to be assigned by a counter in formatters/wakka__.php regexing the
 * rendered HTML, mirrored by a second counter in translate2toc() reading the raw markdown.
 * Two independent counters meant the links and the anchors could silently drift -- any
 * action emitting its own <hN> shifted one side only. Both now come from a single
 * CommonMark HeadingPermalink pass over the AST.
 *
 * The assertion is therefore the invariant, not literal id strings: every link the toc box
 * emits must resolve to an id that actually exists in the rendered page.
 */
class TocActionTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'TocActionRegressionPage';

    /**
     * The fixtures go when the tests do.
     *
     * phpunit runs against a real wiki -- the developer's own -- so a fixture left behind is a
     * page in somebody's index, for ever.
     */
    public static function tearDownAfterClass(): void
    {
        $pageManager = self::getWiki()->services->get(PageManager::class);
        foreach ([self::PAGE_TAG] as $tag) {
            $pageManager->deleteOrphaned($tag);
        }
    }

    public function testTocLinksMatchAssignedHeadingIdsAndUsesNoBootstrapOrJquery()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        // two headings sharing a title on purpose: the old counter scheme could not tell
        // them apart in a way the two sides agreed on
        $body = "{{toc}}\n\n## First heading\n\nSome text.\n\n## Second heading\n\nMore.\n\n## First heading\n\nAgain.\n";
        $pageManager->save(self::PAGE_TAG, [PageBody::CONTENT => $body], '', true);
        $page = $pageManager->getOne(self::PAGE_TAG);
        $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class)->assignPage($page);

        $html = $wiki->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format($body);

        // every heading carries an id
        preg_match_all('/<h[1-6][^>]*\sid="([^"]+)"/', $html, $idMatches);
        $headingIds = $idMatches[1];
        $this->assertCount(3, $headingIds, 'each heading must get an id');
        $this->assertSame($headingIds, array_unique($headingIds), 'duplicate titles must still get distinct ids');

        // every toc link resolves to one of them -- the invariant the two-counter scheme could not hold
        preg_match_all('/<a href="#([^"]+)"/', $html, $linkMatches);
        $this->assertCount(3, $linkMatches[1], 'the toc must link every heading');
        foreach ($linkMatches[1] as $target) {
            $this->assertContains($target, $headingIds, "toc links to #$target but no heading has that id");
        }

        // ADR-0005: no Bootstrap collapse/well classes, no jQuery scrollspy/animate script
        $this->assertStringContainsString('yw-toc', $html);
        $this->assertStringContainsString('data-yw-collapse-toggle', $html);
        $this->assertStringNotContainsString('data-toggle="collapse"', $html);
        $this->assertStringNotContainsString('class="toc well', $html);
        $this->assertStringNotContainsString('scrollspy', $html);
    }
}
