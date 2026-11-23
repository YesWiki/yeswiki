<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Deleting a page takes its triples with it, and a page with an empty tag goes alone. */
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

    public function testAPageWithAnEmptyTagGoesAlone()
    {
        $dbService = $this->getWiki()->services->get(DbService::class);
        $pages = $dbService->prefixTable('pages');
        $dbService->query("INSERT INTO {$pages} (tag, time, body, body_r, owner, user, latest, handler, comment_on) VALUES ('', NOW(), 'sans nom', '', '', '', 'Y', 'page', '')");

        $this->pageManager->deleteOrphaned('');

        $this->assertNull($dbService->loadSingle("SELECT id FROM {$pages} WHERE tag = ''"));
        $this->assertNotNull($this->pageManager->getOne(self::PAGE_TAG));
    }
}
