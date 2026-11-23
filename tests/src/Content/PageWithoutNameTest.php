<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\CoversClass;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\Trash;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A page with an empty tag goes alone: every page that is not a comment has an empty parent. */
#[CoversClass(PageManager::class)]
#[CoversClass(Trash::class)]
class PageWithoutNameTest extends YesWikiTestCase
{
    private const PAGE = 'PageWithoutNameTestNeighbour';
    private const RENAMED = 'PageWithoutNameTestRenamed';

    protected function setUp(): void
    {
        parent::setUp();
        $admins = self::getWiki()->services->get(GroupManager::class)->getMembers('admins');
        if ($admins === []) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
        self::actAs((string)$admins[0]);
        $services = self::getWiki()->services;
        $services->get(PageManager::class)->save(self::PAGE, [PageBody::CONTENT => 'voisine'], '', true);
        $db = $services->get(DbService::class);
        $pages = trim($db->prefixTable('pages'));
        $db->query(
            "INSERT INTO {$pages} (tag, {$db->quoteIdentifier('time')}, body, owner, {$db->quoteIdentifier('user')}, latest, type, parent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            ['', '2020-01-01 00:00:00', PageBody::encode([PageBody::CONTENT => 'sans nom']), '', '', 'Y', 'page', '']
        );
    }

    protected function tearDown(): void
    {
        $services = self::getWiki()->services;
        $trash = $services->get(Trash::class);
        $pages = $services->get(PageManager::class);
        foreach (['', self::PAGE, self::RENAMED] as $tag) {
            if ($trash->record($tag) !== null) {
                $trash->erase($tag);
            }
        }
        $db = $services->get(DbService::class);
        $db->query('DELETE FROM ' . trim($db->prefixTable('pages')) . ' WHERE tag IN (?, ?, ?)', ['', self::PAGE, self::RENAMED]);
        foreach (['', self::PAGE, self::RENAMED] as $tag) {
            $pages->forget($tag);
        }
        self::actAs(null);
        parent::tearDown();
    }

    public function testTrashingAndErasingItLeavesTheOtherPagesAlone(): void
    {
        $services = self::getWiki()->services;
        $trash = $services->get(Trash::class);
        $pages = $services->get(PageManager::class);

        $this->assertTrue($trash->trash(''));
        $this->assertNull($pages->getOne('', null, false, true));
        $this->assertNotNull($pages->getOne(self::PAGE, null, false, true));
        $this->assertSame([], $trash->record('')['comments'] ?? null);

        $this->assertTrue($trash->erase(''));
        $this->assertFalse($pages->tagExists(''));
        $this->assertNotNull($pages->getOne(self::PAGE, null, false, true));
    }

    public function testDeletingItLeavesTheOtherPagesAlone(): void
    {
        $pages = self::getWiki()->services->get(PageManager::class);

        $pages->deleteOrphaned('');

        $this->assertFalse($pages->tagExists(''));
        $this->assertNotNull($pages->getOne(self::PAGE, null, false, true));
    }

    public function testRenamingItAdoptsNoOtherPage(): void
    {
        $services = self::getWiki()->services;
        $pages = $services->get(PageManager::class);
        $db = $services->get(DbService::class);

        $pages->renameTag('', self::RENAMED);

        $this->assertTrue($pages->tagExists(self::RENAMED));
        $neighbour = $db->loadSingle('SELECT parent FROM ' . trim($db->prefixTable('pages')) . " WHERE tag = ? AND latest = 'Y'", [self::PAGE]);
        $this->assertNotNull($neighbour);
        $this->assertSame('', (string)$neighbour['parent']);
    }

    public function testSavingOneIsRefused(): void
    {
        $services = self::getWiki()->services;
        $db = $services->get(DbService::class);
        $pages = trim($db->prefixTable('pages'));
        $db->query("DELETE FROM {$pages} WHERE tag = ''");
        $services->get(PageManager::class)->forget('');

        foreach (['', ' '] as $tag) {
            try {
                $services->get(PageManager::class)->save($tag, [PageBody::CONTENT => ''], '', true);
                $this->fail('saving under an empty tag is refused');
            } catch (\InvalidArgumentException $refused) {
                $this->assertSame(_t('INCORRECT_PAGENAME'), $refused->getMessage());
            }
        }
        $this->assertNull($db->loadSingle("SELECT id FROM {$pages} WHERE TRIM(tag) = ''"));
    }
}
