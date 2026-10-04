<?php

namespace YesWiki\Test\Kernel\Database;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Admin\Service\SchemaCreator;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** On SQLite, index, trigger and full-text table names belong to the whole file, so a staged restore has to rename them as well as the tables. */
class SqliteStagedRestoreTest extends YesWikiTestCase
{
    private string $file = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->getWiki();
        $this->file = sys_get_temp_dir() . '/yw-staged-' . bin2hex(random_bytes(4)) . '.sqlite';
        SchemaCreator::create(new \PDO('sqlite:' . $this->file), 'ywsr_');
        $db = $this->db('ywsr_');
        $db->query("INSERT INTO ywsr_pages (tag, time, body, owner, user, latest) VALUES ('Mine', '2026-01-01 00:00:00', '{}', '', '', 'Y')");
        $this->indexPage($db, 'ywsr_', 'BeforeTheBackup', 'restored words');
    }

    protected function tearDown(): void
    {
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
        parent::tearDown();
    }

    private function db(string $prefix): DbService
    {
        return new DbService(new ParameterBag(['db_driver' => 'sqlite', 'db_database' => $this->file, 'table_prefix' => $prefix, 'base_url' => '', 'debug' => false]));
    }

    private function indexPage(DbService $db, string $prefix, string $tag, string $text): void
    {
        $db->query(
            "INSERT INTO {$prefix}search_index (tag, acl, acl_hash, page_read_acl, title, text, updated_at) VALUES (?, '*', 'h', '*', ?, ?, '2026-01-01')",
            [$tag, $tag, $text]
        );
    }

    /** @return list<string> */
    private function objects(DbService $db, string $type): array
    {
        return array_map(static fn (array $row): string => (string)$row['name'], $db->loadAll('SELECT name FROM sqlite_master WHERE type = ? ORDER BY name', [$type]));
    }

    public function testAWikiIsRestoredOverItselfWithItsSearchIndexWorking(): void
    {
        $db = $this->db('ywsr_');
        $dump = $db->dumper()->dump();
        $this->assertSame('', $dump['error']);
        $indexesBefore = $this->objects($db, 'index');
        $triggersBefore = $this->objects($db, 'trigger');

        $db->restoreStagedFromDump($dump['sql']);
        $db->restoreStagedFromDump($dump['sql']);

        $this->assertSame($indexesBefore, $this->objects($db, 'index'), 'the indexes come back under their own names');
        $this->assertSame($triggersBefore, $this->objects($db, 'trigger'));
        $this->assertSame(1, (int)$db->scalar("SELECT COUNT(*) FROM ywsr_search_index_fts WHERE ywsr_search_index_fts MATCH 'restored'"));
        $this->indexPage($db, 'ywsr_', 'AfterTheRestore', 'fresh words');
        $this->assertSame(1, (int)$db->scalar("SELECT COUNT(*) FROM ywsr_search_index_fts WHERE ywsr_search_index_fts MATCH 'fresh'"), 'the triggers still feed the index');
    }

    public function testAWikiRestoredUnderAnotherPrefixNamesEverythingAfterIt(): void
    {
        $dump = $this->db('ywsr_')->dumper()->dump();
        $copy = $this->db('ywcp_');

        $copy->restoreStagedFromDump($dump['sql']);

        $this->assertSame(1, (int)$copy->scalar("SELECT COUNT(*) FROM ywcp_search_index_fts WHERE ywcp_search_index_fts MATCH 'restored'"));
        foreach (['index', 'trigger'] as $type) {
            foreach ($this->objects($copy, $type) as $name) {
                $this->assertMatchesRegularExpression('/^(ywsr_|ywcp_|sqlite_)/', $name, "no $type is left under the staging prefix");
            }
        }
        $this->assertCount(\count(preg_grep('/^ywsr_/', $this->objects($copy, 'trigger')) ?: []), preg_grep('/^ywcp_/', $this->objects($copy, 'trigger')) ?: []);
        $this->indexPage($copy, 'ywcp_', 'InTheCopy', 'copied words');
        $this->assertSame(1, (int)$copy->scalar("SELECT COUNT(*) FROM ywcp_search_index_fts WHERE ywcp_search_index_fts MATCH 'copied'"));
        $this->assertSame(0, (int)$copy->scalar("SELECT COUNT(*) FROM ywsr_search_index_fts WHERE ywsr_search_index_fts MATCH 'copied'"), 'the source wiki is not written to');
    }
}
