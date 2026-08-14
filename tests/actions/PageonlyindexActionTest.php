<?php

namespace YesWiki\Test\Actions;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `{{pageonlyindex}}` lists every page except the entries -- and for a while it listed
 * nothing at all.
 *
 * It separated the two by asking whether the body started with a brace (`body NOT LIKE
 * '{"%'`): an entry's body was JSON and a page's was wiki markup. Ticket 09 made every body
 * JSON, the predicate became universally false, and the action has emitted an empty index on
 * every wiki since. Nothing noticed, because an index with nothing in it looks exactly like a
 * wiki with nothing to index -- which is the whole reason this test asserts a page IS listed
 * rather than only that an entry is not.
 *
 * Found by ticket 38's sweep for text operators applied to `body`, since a native JSON column
 * has none.
 */
class PageonlyindexActionTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'PageonlyindexRegressionPage';

    public function testItListsPagesAndNotEntries(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        $pageManager->save(self::PAGE_TAG, [PageBody::CONTENT => 'An ordinary page.'], '', true);
        $pageManager->save(
            'PageonlyindexRegressionEntry',
            [PageBody::CONTENT => '', 'form_id' => '1', 'bf_titre' => 'An entry'],
            '',
            true,
            null,
            PageType::ENTRY
        );

        $wiki->services->get(PageContext::class)->assignPage($pageManager->getOne(self::PAGE_TAG));
        $html = $wiki->services->get(\YesWiki\Kernel\Service\Performer::class)
            ->run('pageonlyindex', 'action', []);

        $this->assertStringContainsString(self::PAGE_TAG, $html, 'a page must appear in the page-only index');
        $this->assertStringNotContainsString('PageonlyindexRegressionEntry', $html, 'an entry must not');
    }
}
