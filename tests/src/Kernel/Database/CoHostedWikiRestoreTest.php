<?php

namespace YesWiki\Test\Kernel\Database;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Kernel\Database\DumpRewriter;
use YesWiki\Kernel\Database\SqlDumper;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** On the wiki's own database server, a backup and restore of one prefix leaves a wiki under a longer prefix alone. */
class CoHostedWikiRestoreTest extends YesWikiTestCase
{
    private const LIVE = 'ywcohost_';
    private const OTHER = 'ywcohost_ecto__';

    private DbService $db;

    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getWiki()->config;
        $params = [];
        foreach (['db_driver', 'db_host', 'db_port', 'db_database', 'db_user', 'db_password', 'db_charset'] as $key) {
            if (isset($config[$key])) {
                $params[$key] = $config[$key];
            }
        }
        $this->db = new DbService(new ParameterBag($params + ['table_prefix' => self::LIVE, 'debug' => false]));
        $this->dropProbeTables();

        foreach ([self::LIVE, self::OTHER] as $prefix) {
            foreach (['pages', 'triples'] as $table) {
                $this->db->query('CREATE TABLE ' . $this->db->quoteIdentifier($prefix . $table) . ' (tag VARCHAR(190), body TEXT)');
            }
        }
        $this->db->query('INSERT INTO ' . $this->db->quoteIdentifier(self::LIVE . 'pages') . " (tag, body) VALUES ('Mine', 'before')");
        $this->db->query('INSERT INTO ' . $this->db->quoteIdentifier(self::OTHER . 'pages') . " (tag, body) VALUES ('Theirs', 'untouched')");
    }

    protected function tearDown(): void
    {
        $this->dropProbeTables();
        unset($this->db);
        parent::tearDown();
    }

    private function dropProbeTables(): void
    {
        foreach ($this->db->schema()->getTables() as $table) {
            if (str_starts_with($table, self::LIVE) || preg_match('/^x*yw(staging|replaced)' . substr(sha1(self::LIVE), 0, 6) . '_/', $table)) {
                $this->db->query('DROP TABLE IF EXISTS ' . $this->db->quoteIdentifier($table));
            }
        }
    }

    public function testTheOtherWikisTablesAreNeitherDumpedNorReplaced(): void
    {
        $dump = $this->db->dumper()->dump();
        $this->assertSame('', $dump['error']);
        $this->assertSame([self::LIVE . 'pages', self::LIVE . 'triples'], DumpRewriter::tables($dump['sql']));

        $this->db->query('UPDATE ' . $this->db->quoteIdentifier(self::LIVE . 'pages') . " SET body = 'after'");
        $this->db->restoreStagedFromDump($dump['sql']);

        $this->assertSame('before', $this->db->scalar('SELECT body FROM ' . $this->db->quoteIdentifier(self::LIVE . 'pages')));
        $this->assertSame('untouched', $this->db->scalar('SELECT body FROM ' . $this->db->quoteIdentifier(self::OTHER . 'pages')));
        $this->assertContains(self::OTHER . 'triples', $this->db->schema()->getTables());
    }

    public function testManyRowsComeBackThroughBatchedInserts(): void
    {
        $table = $this->db->quoteIdentifier(self::LIVE . 'pages');
        $rows = SqlDumper::MAX_INSERT_ROWS + 3;
        $this->db->transactional(function () use ($table, $rows): void {
            for ($i = 0; $i < $rows; $i++) {
                $this->db->query("INSERT INTO $table (tag, body) VALUES (?, ?)", ["Page$i", str_repeat('é', 50)]);
            }
        });
        $sql = $this->db->dumper()->dump()['sql'];
        $this->assertSame(2, substr_count($sql, 'INSERT INTO ' . $table));

        $this->db->restoreStagedFromDump($sql);

        $this->assertSame($rows + 1, $this->db->countRows("SELECT tag FROM $table"));
        $this->assertSame(1, $this->db->countRows('SELECT tag FROM ' . $this->db->quoteIdentifier(self::OTHER . 'pages')));
    }
}
