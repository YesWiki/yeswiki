<?php

namespace YesWiki\Test\Actions;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `{{pageonlyindex}}` lists every page except the entries -- and for a while it listed nothing at all.
 */
class PageonlyindexActionTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'PageonlyindexRegressionPage';
    private const ENTRY_TAG = 'PageonlyindexRegressionEntry';

    /** The fixtures go when the test does. */
    public static function tearDownAfterClass(): void
    {
        $pageManager = self::getWiki()->services->get(PageManager::class);
        foreach ([self::PAGE_TAG, self::ENTRY_TAG] as $tag) {
            $pageManager->deleteOrphaned($tag);
        }
    }

    public function testItListsPagesAndNotEntries(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        $pageManager->save(self::PAGE_TAG, [PageBody::CONTENT => 'An ordinary page.'], '', true);
        $pageManager->save(
            self::ENTRY_TAG,
            [PageBody::CONTENT => '', 'tag' => self::ENTRY_TAG, 'form_id' => '1', 'bf_titre' => 'An entry'],
            '',
            true,
            null,
            PageType::ENTRY
        );

        $wiki->services->get(PageContext::class)->assignPage($pageManager->getOne(self::PAGE_TAG));
        $html = $wiki->services->get(\YesWiki\Render\Service\Performer::class)
            ->run('pageonlyindex', 'action', []);

        $this->assertStringContainsString(self::PAGE_TAG, $html, 'a page must appear in the page-only index');
        $this->assertStringNotContainsString(self::ENTRY_TAG, $html, 'an entry must not');
    }
}
