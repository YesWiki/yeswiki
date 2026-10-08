<?php

namespace YesWiki\Test\Bazar\Service;

use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A list imported from another wiki keeps the id that wiki's forms refer to.
 */
class ListImportTest extends YesWikiTestCase
{
    private const REMOTE_ID = 'ListeImportTestDepartements';
    private const PLAIN_PAGE = 'ListeImportTestPlainPage';
    private const NODES = [['id' => '01', 'label' => 'Ain', 'children' => []]];

    private $wiki;
    private $listManager;
    private $pageManager;
    private $created;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->listManager = $this->wiki->services->get(ListManager::class);
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->created = [self::REMOTE_ID, self::PLAIN_PAGE];
    }

    protected function tearDown(): void
    {
        $tripleStore = $this->wiki->services->get(TripleStore::class);
        foreach (array_unique($this->created) as $id) {
            $tripleStore->delete($id, TripleStore::TYPE_URI, ListManager::TRIPLES_LIST_ID, '', '');
            $this->wiki->services->get(AclService::class)->delete($id);
            $this->pageManager->deleteOrphaned($id);
        }
        unset($_SESSION['user']);
    }

    public function testAnImportedListKeepsItsRemoteId()
    {
        $id = $this->import(self::REMOTE_ID, 'Départements');

        $this->assertSame(self::REMOTE_ID, $id);
        $this->assertTrue($this->listManager->isList(self::REMOTE_ID));
        $this->assertSame('Ain', $this->listManager->getOne(self::REMOTE_ID)['nodes'][0]['label']);
    }

    public function testImportingAnExistingListReplacesIt()
    {
        $this->listManager->create('Old title', [], self::REMOTE_ID);

        $id = $this->import(self::REMOTE_ID, 'New title');

        $this->assertSame(self::REMOTE_ID, $id);
        $this->assertSame('New title', $this->listManager->getOne(self::REMOTE_ID)['title']);
    }

    public function testAPageThatIsNotAListIsNeverOverwritten()
    {
        $this->pageManager->save(self::PLAIN_PAGE, 'plain content', '', true);

        $id = $this->import(self::PLAIN_PAGE, 'Plain');

        $this->assertNotSame(self::PLAIN_PAGE, $id);
        $this->assertSame('plain content', $this->pageManager->getOne(self::PLAIN_PAGE)['body']);
    }

    public function testAnIdThatIsNotAPageNameIsReplaced()
    {
        $id = $this->import('../Liste Import Test', 'Invalid id');

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $id);
    }

    public function testAListTheVisitorCannotWriteIsNotReplaced()
    {
        $this->listManager->create('Protected title', [], self::REMOTE_ID);
        $this->wiki->services->get(AclService::class)->save(self::REMOTE_ID, 'write', '@admins');
        unset($_SESSION['user']);

        $id = $this->import(self::REMOTE_ID, 'Hijacked title');

        $this->assertNull($id);
        $this->assertSame('Protected title', $this->listManager->getOne(self::REMOTE_ID)['title']);
    }

    private function import(string $remoteId, string $title): ?string
    {
        $id = $this->listManager->import($remoteId, $title, self::NODES);
        if ($id !== null) {
            $this->created[] = $id;
        }

        return $id;
    }
}
