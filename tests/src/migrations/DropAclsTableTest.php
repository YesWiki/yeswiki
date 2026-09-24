<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A Doryphore wiki's page restrictions must survive the move from the acls table into metadata. */
class DropAclsTableTest extends YesWikiTestCase
{
    private const TAG = 'TestDropAclsTableKeepsRestrictions';
    private const TABLE = 'yeswiki_test_legacy_acls';

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20260723000001_DropAclsTable.php';
    }

    public function testEveryRevisionGetsTheListsAndKeepsItsOtherMetadata(): void
    {
        $dbService = $this->getWiki()->services->get(DbService::class);
        $pages = trim($dbService->prefixTable('pages'));

        $dbService->query('CREATE TABLE ' . self::TABLE . ' (page_tag VARCHAR(191), privilege VARCHAR(20), list TEXT)');
        $this->insertRevision($dbService, $pages, 'N', null);
        $this->insertRevision($dbService, $pages, 'Y', json_encode(['theme' => 'margot'], JSON_THROW_ON_ERROR));

        try {
            foreach ([['read', '@admins'], ['write', '@admins'], ['comment', 'comments-closed']] as [$privilege, $list]) {
                $dbService->query('INSERT INTO ' . self::TABLE . ' (page_tag, privilege, list) VALUES (?, ?, ?)', [self::TAG, $privilege, $list]);
            }

            $migration = new \DropAclsTable();
            $migration->setDbService($dbService);

            $this->assertSame(1, $migration->carryIntoMetadata(self::TABLE));

            $rows = $dbService->loadAll("SELECT latest, metadata FROM {$pages} WHERE tag = ? ORDER BY id", [self::TAG]);
            $expected = ['read' => '@admins', 'write' => '@admins', 'comment' => 'comments-closed'];
            $this->assertSame($expected, json_decode((string)$rows[0]['metadata'], true)['acls']);
            $latest = json_decode((string)$rows[1]['metadata'], true);
            $this->assertSame($expected, $latest['acls']);
            $this->assertSame('margot', $latest['theme'], 'what else the metadata held is kept');
        } finally {
            $dbService->query("DELETE FROM {$pages} WHERE tag = ?", [self::TAG]);
            $dbService->query('DROP TABLE IF EXISTS ' . self::TABLE);
        }
    }

    private function insertRevision(DbService $dbService, string $pages, string $latest, ?string $metadata): void
    {
        $dbService->query(
            "INSERT INTO {$pages} (tag, {$dbService->quoteIdentifier('time')}, body, owner,"
            . " {$dbService->quoteIdentifier('user')}, latest, type, parent, metadata)"
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [self::TAG, '2020-01-01 00:00:00', PageBody::encode(['content' => 'x']), '', '', $latest, 'page', '', $metadata]
        );
    }
}
