<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Deleting a page takes its triples with it. */
class PageManagerDeleteOrphanedTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'DeleteOrphanedTestPage';
    private const PROPERTY = 'http://outils-reseaux.org/_vocabulary/deleteorphanedtest';

    private PageManager $pageManager;

    private TripleStore $tripleStore;

    protected function setUp(): void
    {
        parent::setUp();
        $services = $this->getWiki()->services;
        $this->pageManager = $services->get(PageManager::class);
        $this->tripleStore = $services->get(TripleStore::class);
        $this->pageManager->save(self::PAGE_TAG, [PageBody::CONTENT => 'content'], '', true);
        $this->tripleStore->create(self::PAGE_TAG, self::PROPERTY, 'value', '', '');
    }

    protected function tearDown(): void
    {
        $this->tripleStore->delete(self::PAGE_TAG, self::PROPERTY, null, '', '');
        $this->pageManager->deleteOrphaned(self::PAGE_TAG);
        parent::tearDown();
    }

    public function testTheTriplesOfADeletedPageAreGone(): void
    {
        $this->pageManager->deleteOrphaned(self::PAGE_TAG);

        $this->assertNull($this->tripleStore->exist(self::PAGE_TAG, self::PROPERTY, 'value', '', ''));
    }
}
