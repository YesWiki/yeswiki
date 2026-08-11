<?php

namespace YesWiki\Test\Kernel\Database;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Kernel\Database\SchemaManager;
use YesWiki\Kernel\Database\SqlDumper;
use YesWiki\Kernel\Service\DbService;

/**
 * Ticket 32: a dump has to survive the search index.
 *
 * A backup is only worth what it restores, and this was the gap between the two. On SQLite the
 * search index is an FTS5 virtual table with four shadow tables and a pair of triggers
 * (ADR-0015), and the dump treated all of that as ordinary storage. The archive was written
 * without complaint and then **could not be replayed** -- it died on
 * `INSERT INTO "x_fts" ("title", "text", "x_fts", "rank")`, because those last two are FTS5
 * pseudo-columns. Worse than the PostgreSQL bug this ticket was raised for: that one refused to
 * make a backup at all, loudly, at backup time.
 *
 * Built against a throwaway SQLite file rather than the wiki's DbService, for the reason
 * DatabaseRestoreTest gives: restore drops every prefixed table, so pointing this at the
 * development database has to be impossible rather than merely inadvisable.
 */
#[CoversMethod(SqlDumper::class, 'dump')]
#[CoversMethod(SchemaManager::class, 'dumpRoleFor')]
#[CoversMethod(SchemaManager::class, 'postDataStatements')]
#[CoversMethod(SchemaManager::class, 'dumpableColumns')]
class SearchIndexDumpRoundTripTest extends TestCase
{
    private string $file = '';

    private DbService $dbService;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-dump-') . '.db';
        $this->dbService = new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'rt_',
            'debug' => false,
        ]));

        foreach ($this->dbService->dialect()->searchIndexDdl('rt_search_index', 'rt_search_queue') as $ddl) {
            $this->dbService->query($ddl);
        }
        $this->insertIndexed('Tag1', 'Hello', 'searchable body text');
    }

    protected function tearDown(): void
    {
        unset($this->dbService);
        foreach ([$this->file, substr($this->file, 0, -3)] as $path) {
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function insertIndexed(string $tag, string $title, string $text): void
    {
        $this->dbService->query(
            'INSERT INTO rt_search_index (tag, acl, acl_hash, page_read_acl, title, text, updated_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$tag, '', md5($tag), '', $title, $text, '2026-01-01 00:00:00']
        );
    }

    /** @return list<string> */
    private function search(string $term): array
    {
        $expression = $this->dbService->dialect()->searchMatchExpression('rt_search_index', [[$term]]);

        return array_map(
            static fn (array $row): string => (string)$row['tag'],
            $this->dbService->loadAll("SELECT tag FROM rt_search_index WHERE {$expression}")
        );
    }

    public function testAnFtsWikiSurvivesADumpAndRestore(): void
    {
        $this->assertSame(['Tag1'], $this->search('searchable'), 'fixture: the index works to begin with');

        $dump = $this->dbService->dumper()->dump();
        $this->assertSame('', $dump['error']);
        $this->assertNotSame('', $dump['sql']);

        // this is the assertion that used to fail, and it failed at restore time
        $this->dbService->restoreFromDump($dump['sql']);

        $this->assertSame(
            ['Tag1'],
            $this->search('searchable'),
            'the restored wiki must still find what it could find before'
        );
    }

    /**
     * The triggers, which are what keeps the index in step with the table. They were not dumped
     * at all, so a restored wiki went on working for existing rows and silently stopped indexing
     * every row written afterwards -- a failure that only shows up as "search misses recent
     * pages", long after the restore.
     */
    public function testTheRestoredIndexIsStillMaintained(): void
    {
        $this->dbService->restoreFromDump($this->dbService->dumper()->dump()['sql']);

        $this->insertIndexed('Tag2', 'Second', 'another findable phrase');

        $this->assertSame(['Tag2'], $this->search('findable'), 'the triggers must have been restored');
    }

    public function testShadowTablesAreNotDumpedAndTheVirtualTableCarriesNoData(): void
    {
        $sql = $this->dbService->dumper()->dump()['sql'];

        $this->assertStringContainsString('CREATE VIRTUAL TABLE', $sql, 'the FTS table itself is structure');
        $this->assertStringContainsString('CREATE TRIGGER', $sql, 'the triggers belong in the dump');
        $this->assertStringContainsString(
            "VALUES('rebuild')",
            $sql,
            'the derived index is repopulated rather than inserted row by row'
        );

        // The bug's exact signature. The dump legitimately names the FTS table -- in the triggers
        // and in the rebuild -- so what has to be absent is an INSERT of its *rows*, which is
        // recognisable by the FTS5 pseudo-columns `rank` and the table's own name appearing as a
        // column. Asserting on `rank` catches it precisely and nothing else in a dump produces it.
        $this->assertStringNotContainsString(
            '"rank"',
            $sql,
            'an FTS pseudo-column in the dump means the virtual table\'s rows were dumped -- unrestorable'
        );
        $this->assertStringContainsString(
            'is derived and rebuilt after the data',
            $sql,
            'the dump should say why that table has no data section'
        );
        foreach (['_data', '_idx', '_docsize', '_config'] as $shadow) {
            $this->assertStringNotContainsString(
                '"rt_search_index_fts' . $shadow . '"',
                $sql,
                "shadow table {$shadow} must not appear in a dump"
            );
        }
    }

    public function testDumpRoleClassifiesTheThreeKindsOfTable(): void
    {
        $schema = $this->dbService->schema();

        $this->assertSame(SchemaManager::DUMP_FULL, $schema->dumpRoleFor('rt_search_index'));
        $this->assertSame(SchemaManager::DUMP_STRUCTURE_ONLY, $schema->dumpRoleFor('rt_search_index_fts'));
        $this->assertSame(SchemaManager::DUMP_SKIP, $schema->dumpRoleFor('rt_search_index_fts_data'));
        $this->assertSame(SchemaManager::DUMP_SKIP, $schema->dumpRoleFor('rt_search_index_fts_config'));
    }

    /** Indexes were dropped on the floor by the SQLite dump; on a large wiki that is felt, not seen. */
    public function testIndexesComeBack(): void
    {
        $this->dbService->restoreFromDump($this->dbService->dumper()->dump()['sql']);

        $indexes = array_map(
            static fn (array $row): string => (string)$row['name'],
            $this->dbService->loadAll(
                "SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'rt_search_index'"
                . ' AND sql IS NOT NULL ORDER BY name'
            )
        );

        $this->assertContains('rt_search_index_idx_tag', $indexes);
        $this->assertContains('rt_search_index_idx_acl_hash', $indexes);
    }
}
