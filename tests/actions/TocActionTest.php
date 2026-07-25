<?php

namespace YesWiki\Test\Actions;

use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for ticket 13 (toc absorbed into core): actions/toc.php's server-side
 * static TOC depends on formatters/wakka__.php (formerly tools/toc's own formatter hook)
 * to number each rendered <hN> the same way translate2toc() numbers them from the raw
 * markdown, so the toc's generated links resolve to the right heading. Also confirms the
 * ADR-0005 conversion (yw-* classes, no Bootstrap data-toggle="collapse") and that the
 * dropped tocjs.php's jQuery scrollspy/animate script isn't emitted anymore.
 */
class TocActionTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'TocActionRegressionPage';

    public function testTocLinksMatchAssignedHeadingIdsAndUsesNoBootstrapOrJquery()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        $body = "{{toc}}\n\n## First heading\n\nSome text.\n\n## Second heading\n\nMore text.\n";
        $pageManager->save(self::PAGE_TAG, $body, '', true);
        $page = $pageManager->getOne(self::PAGE_TAG);
        $wiki->SetPage($page);

        $html = $wiki->Format($body);

        // headings get numbered TOC_{level}_{n} ids...
        $this->assertMatchesRegularExpression('/<h2[^>]*\sid="TOC_2_1"[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<h2[^>]*\sid="TOC_2_2"[^>]*>/', $html);

        // ...and the toc box's generated links point at those exact same ids
        $this->assertStringContainsString('href="#TOC_2_1"', $html);
        $this->assertStringContainsString('href="#TOC_2_2"', $html);

        // ADR-0005: no Bootstrap collapse/well classes, no jQuery scrollspy/animate script
        $this->assertStringContainsString('yw-toc', $html);
        $this->assertStringContainsString('data-yw-collapse-toggle', $html);
        $this->assertStringNotContainsString('data-toggle="collapse"', $html);
        $this->assertStringNotContainsString('class="toc well', $html);
        $this->assertStringNotContainsString('scrollspy', $html);
    }
}
