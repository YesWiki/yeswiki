<?php

namespace YesWiki\Test\Actions;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Regression test for ticket 13 (toc absorbed into core), rewritten by ticket 06. */
class TocActionTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'TocActionRegressionPage';

    /** The fixtures go when the tests do. */
    public static function tearDownAfterClass(): void
    {
        $pageManager = self::getWiki()->services->get(PageManager::class);
        foreach ([self::PAGE_TAG] as $tag) {
            $pageManager->deleteOrphaned($tag);
        }
    }

    public function testTocLinksMatchAssignedHeadingIdsAndUsesNoBootstrapOrJquery(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        $body = "{{toc}}\n\n## First heading\n\nSome text.\n\n## Second heading\n\nMore.\n\n## First heading\n\nAgain.\n";
        $pageManager->save(self::PAGE_TAG, [PageBody::CONTENT => $body], '', true);
        $page = $pageManager->getOne(self::PAGE_TAG);
        $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class)->assignPage($page);

        $html = $wiki->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format($body);

        preg_match_all('/<h[1-6][^>]*\sid="([^"]+)"/', $html, $idMatches);
        $headingIds = $idMatches[1];
        $this->assertCount(3, $headingIds, 'each heading must get an id');
        $this->assertSame($headingIds, array_unique($headingIds), 'duplicate titles must still get distinct ids');

        preg_match_all('/<a href="#([^"]+)"/', $html, $linkMatches);
        $this->assertCount(3, $linkMatches[1], 'the toc must link every heading');
        foreach ($linkMatches[1] as $target) {
            $this->assertContains($target, $headingIds, "toc links to #$target but no heading has that id");
        }

        $this->assertStringContainsString('yw-toc', $html);
        $this->assertStringContainsString('data-yw-collapse-toggle', $html);
        $this->assertStringNotContainsString('data-toggle="collapse"', $html);
        $this->assertStringNotContainsString('class="toc well', $html);
        $this->assertStringNotContainsString('scrollspy', $html);
    }
}
