<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Deleting a page takes its triples with it, so a deleted entry stops being an entry.
 */
class PageManagerDeleteOrphanedTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'DeleteOrphanedTestPage';
    private const PROPERTY = 'http://outils-reseaux.org/_vocabulary/type';

    private $pageManager;
    private $tripleStore;

    protected function setUp(): void
    {
        $wiki = $this->getWiki();
        $this->pageManager = $wiki->services->get(PageManager::class);
        $this->tripleStore = $wiki->services->get(TripleStore::class);
        $this->pageManager->save(self::PAGE_TAG, 'content', '', true);
        $this->tripleStore->create(self::PAGE_TAG, self::PROPERTY, 'fiche_bazar', '', '');
    }

    protected function tearDown(): void
    {
        $this->tripleStore->delete(self::PAGE_TAG, self::PROPERTY, null, '', '');
        $this->pageManager->deleteOrphaned(self::PAGE_TAG);
    }

    public function testTheTriplesOfADeletedPageAreGone()
    {
        $this->pageManager->deleteOrphaned(self::PAGE_TAG);

        $this->assertNull($this->tripleStore->exist(self::PAGE_TAG, self::PROPERTY, 'fiche_bazar', '', ''));
    }
}
