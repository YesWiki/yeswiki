<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Kernel\Database\SqlDumper;
use YesWiki\Kernel\Database\SqlStatementSplitter;
use YesWiki\Kernel\Service\DbService;

/**
 * Ticket 17: archive restore replayed the dump through `mysqli_multi_query()`, so it worked on MySQL and nowhere else -- an SQLite install could take a backup it could never put back.
 */
class DatabaseRestoreTest extends TestCase
{
    private string $file;
    private DbService $db;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-restore-') . '.sqlite';
        $this->db = new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'probe_',
            'debug' => false,
        ]));
    }

    protected function tearDown(): void
    {
        unset($this->db);
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    /** A table with the kind of content that breaks a naive dump: quotes, semicolons, NULL. */
    private function seed(): void
    {
        $this->db->query('CREATE TABLE "probe_pages" (tag VARCHAR(190), body TEXT, note TEXT)');
        $this->db->query(
            "INSERT INTO probe_pages (tag, body, note) VALUES ('Home', '"
            . $this->db->escape('a; b \'quoted\' and "double" — {{action}}')
            . "', NULL)"
        );
        $this->db->query("INSERT INTO probe_pages (tag, body, note) VALUES ('Other', 'plain', 'kept')");

        $this->db->query('CREATE TABLE "other_wiki" (id INTEGER)');
        $this->db->query('INSERT INTO other_wiki (id) VALUES (42)');
    }

    public function testTheDumpRecordsWhichDriverProducedIt(): void
    {
        $this->seed();
        $backup = $this->db->dumper()->dump();

        $this->assertSame('', $backup['error']);
        $this->assertStringContainsString('-- YesWiki-Dialect: sqlite', $backup['sql']);
    }

    /** The point of the ticket: the content comes back, not merely "restore returned". */
    public function testBackupThenRestoreBringsTheContentBack(): void
    {
        $this->seed();
        $backup = $this->db->dumper()->dump();
        $this->assertSame('', $backup['error'], 'backing up must not error');

        $this->db->query('DROP TABLE probe_pages');
        $this->assertNotContains('probe_pages', $this->db->schema()->getTables(), 'the table must really be gone');

        $this->db->restoreFromDump($backup['sql']);

        $rows = $this->db->loadAll('SELECT tag, body, note FROM probe_pages ORDER BY tag');
        $this->assertCount(2, $rows);
        $this->assertSame('Home', $rows[0]['tag']);
        $this->assertSame('a; b \'quoted\' and "double" — {{action}}', $rows[0]['body']);
        $this->assertNull($rows[0]['note'], 'a NULL must come back as NULL, not as an empty string');
        $this->assertSame('kept', $rows[1]['note']);
    }

    /** Restore drops *this wiki's* tables — a second wiki sharing the database is not its business. */
    public function testTablesOutsideThePrefixAreLeftAlone(): void
    {
        $this->seed();
        $backup = $this->db->dumper()->dump();

        $this->db->restoreFromDump($backup['sql']);

        $this->assertSame('42', (string)$this->db->loadAll('SELECT id FROM other_wiki')[0]['id']);
    }

    public function testRestoringTwiceIsStillCorrect(): void
    {
        $this->seed();
        $backup = $this->db->dumper()->dump();

        $this->db->restoreFromDump($backup['sql']);
        $this->db->restoreFromDump($backup['sql']);

        $this->assertCount(2, $this->db->loadAll('SELECT tag FROM probe_pages'), 'rows must not be duplicated');
    }

    /** A dump replayed on the wrong driver fails part-way — after the tables have been dropped. */
    public function testADumpFromAnotherDriverIsRefusedBeforeAnythingIsDropped(): void
    {
        $this->seed();

        try {
            $this->db->restoreFromDump("-- YesWiki-Dialect: mysql\nDROP TABLE IF EXISTS whatever;");
            $this->fail('restoring a foreign dump must throw');
        } catch (\Exception $e) {
            $this->assertStringContainsString('mysql', $e->getMessage());
            $this->assertStringContainsString('sqlite', $e->getMessage());
        }

        $this->assertContains('probe_pages', $this->db->schema()->getTables(), 'nothing may be dropped when the dump is refused');
        $this->assertCount(2, $this->db->loadAll('SELECT tag FROM probe_pages'));
    }

    /** A dump with no marker predates ticket 17, and only MySQL could have produced one. */
    public function testAnUnmarkedDumpIsTreatedAsMysql(): void
    {
        $this->seed();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/mysql/');
        $this->db->restoreFromDump("-- SQL Dump\nDROP TABLE IF EXISTS whatever;");
    }

    public function testAFailingStatementSaysWhichOneItWas(): void
    {
        $this->seed();

        try {
            $this->db->restoreFromDump("-- YesWiki-Dialect: sqlite\nSELECT 1;\nTHIS IS NOT SQL;");
            $this->fail('a broken statement must throw');
        } catch (\Exception $e) {
            $this->assertStringContainsString('statement 2 of 2', $e->getMessage());
            $this->assertStringContainsString('THIS IS NOT SQL', $e->getMessage());
        }
    }

    public function testAnEmptyDumpIsRefusedRatherThanDroppingEverything(): void
    {
        $this->seed();

        try {
            $this->db->restoreFromDump("-- YesWiki-Dialect: sqlite\n-- nothing here\n");
            $this->fail('an empty dump must throw');
        } catch (\Exception $e) {
            $this->assertStringContainsString('no statements', $e->getMessage());
        }

        $this->assertContains('probe_pages', $this->db->schema()->getTables());
    }

    /** A second wiki whose prefix starts with this one's: `probe_ecto__` beside `probe_`. */
    private function seedCoHostedWiki(): void
    {
        $this->db->query('CREATE TABLE "probe_ecto__pages" (tag VARCHAR(190), body TEXT)');
        $this->db->query('CREATE TABLE "probe_ecto__triples" (id INTEGER)');
        $this->db->query("INSERT INTO probe_ecto__pages (tag, body) VALUES ('Theirs', 'not yours')");
    }

    public function testTheDumpLeavesOutAWikiWhosePrefixStartsTheSame(): void
    {
        $this->seed();
        $this->seedCoHostedWiki();

        $sql = $this->db->dumper()->dump()['sql'];

        $this->assertStringContainsString('probe_pages', $sql);
        $this->assertStringNotContainsString('probe_ecto__', $sql);
    }

    public function testAStagedRestoreLeavesAWikiWhosePrefixStartsTheSameAlone(): void
    {
        $this->seed();
        $this->seedCoHostedWiki();
        $backup = $this->db->dumper()->dump();

        $this->db->restoreStagedFromDump($backup['sql']);

        $this->assertSame('not yours', $this->db->loadAll('SELECT body FROM probe_ecto__pages')[0]['body']);
        $this->assertContains('probe_ecto__triples', $this->db->schema()->getTables());
        $this->assertCount(2, $this->db->loadAll('SELECT tag FROM probe_pages'));
    }

    /** A backup taken before the dump knew better carries the other wiki's tables: they are not replayed. */
    public function testAnOldDumpCarryingTheOtherWikisTablesDoesNotTouchThem(): void
    {
        $this->seed();
        $this->seedCoHostedWiki();
        $old = "-- YesWiki-Dialect: sqlite\n"
            . "CREATE TABLE \"probe_pages\" (tag VARCHAR(190), body TEXT, note TEXT);\n"
            . "INSERT INTO \"probe_pages\" (tag, body, note) VALUES ('Restored', 'b', NULL);\n"
            . "CREATE TABLE \"probe_triples\" (id INTEGER);\n"
            . "CREATE TABLE \"probe_ecto__pages\" (tag VARCHAR(190), body TEXT);\n"
            . "INSERT INTO \"probe_ecto__pages\" (tag, body) VALUES ('Stale', 'from the backup');\n"
            . "CREATE TABLE \"probe_ecto__triples\" (id INTEGER);\n";

        $this->db->restoreStagedFromDump($old);

        $this->assertSame(['Restored'], array_column($this->db->loadAll('SELECT tag FROM probe_pages'), 'tag'));
        $this->assertSame(['Theirs'], array_column($this->db->loadAll('SELECT tag FROM probe_ecto__pages'), 'tag'));
    }

    /** One INSERT per table outgrows max_allowed_packet on a big wiki, so rows go in bounded batches. */
    public function testRowsAreDumpedInBoundedBatches(): void
    {
        $this->db->query('CREATE TABLE "probe_pages" (tag VARCHAR(190), body TEXT)');
        $this->db->query('CREATE TABLE "probe_triples" (id INTEGER)');
        $rows = SqlDumper::MAX_INSERT_ROWS * 2 + 7;
        $this->db->transactional(function () use ($rows): void {
            for ($i = 0; $i < $rows; $i++) {
                $this->db->query("INSERT INTO probe_pages (tag, body) VALUES ('Page$i', 'x')");
            }
        });
        $this->db->query("INSERT INTO probe_pages (tag, body) VALUES ('Big', '" . str_repeat('y', SqlDumper::MAX_INSERT_BYTES) . "')");

        $sql = $this->db->dumper()->dump()['sql'];

        $this->assertSame(4, substr_count($sql, 'INSERT INTO "probe_pages"'));
        foreach (SqlStatementSplitter::split($sql) as $statement) {
            $this->assertLessThanOrEqual(SqlDumper::MAX_INSERT_BYTES * 2, \strlen($statement));
        }

        $this->db->restoreStagedFromDump($sql);
        $this->assertSame($rows + 1, $this->db->countRows('SELECT tag FROM probe_pages'));
    }

    public function testADumpCanBeStreamedToAFileAndRestoredFromIt(): void
    {
        $this->seed();
        $this->db->query('CREATE TABLE "probe_triples" (id INTEGER)');
        $file = tempnam(sys_get_temp_dir(), 'yw-dump-');
        $handle = fopen($file, 'wb');
        if ($handle === false) {
            $this->fail('cannot write the dump file');
        }
        $this->db->dumper()->dumpTo($handle);
        fclose($handle);
        $this->db->query("DELETE FROM probe_pages WHERE tag = 'Home'");

        try {
            $this->db->restoreStagedFromStream(
                function () use ($file) {
                    $read = fopen($file, 'rb');
                    if ($read === false) {
                        $this->fail('cannot read the dump file back');
                    }

                    return $read;
                },
                fn (string $statement): string => str_replace("'kept'", "'rewritten'", $statement)
            );
        } finally {
            unlink($file);
        }

        $rows = $this->db->loadAll('SELECT tag, note FROM probe_pages ORDER BY tag');
        $this->assertSame(['Home', 'Other'], array_column($rows, 'tag'));
        $this->assertSame('rewritten', $rows[1]['note']);
    }
}
