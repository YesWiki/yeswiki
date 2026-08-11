<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Kernel\Service\DbService;

/**
 * Ticket 17: archive restore replayed the dump through `mysqli_multi_query()`, so it worked on
 * MySQL and nowhere else -- an SQLite install could take a backup it could never put back.
 *
 * **Every test here runs against a throwaway SQLite file in a temp directory.** That is not
 * tidiness: restore drops every prefixed table before replaying, so a test that reached the
 * wiki's own connection would destroy the development database the moment the replay failed.
 * Constructing DbService with its own ParameterBag makes pointing it at the real wiki
 * impossible rather than merely inadvisable.
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
        $this->db->query('CREATE TABLE probe_pages (tag VARCHAR(190), body TEXT, note TEXT)');
        $this->db->query(
            "INSERT INTO probe_pages (tag, body, note) VALUES ('Home', '"
            . $this->db->escape('a; b \'quoted\' and "double" — {{action}}')
            . "', NULL)"
        );
        $this->db->query("INSERT INTO probe_pages (tag, body, note) VALUES ('Other', 'plain', 'kept')");
        // a table outside the prefix must survive a restore untouched
        $this->db->query('CREATE TABLE other_wiki (id INTEGER)');
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

    /**
     * A dump replayed on the wrong driver fails part-way — after the tables have been dropped.
     * Refusing up front is what leaves the database as it was.
     */
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
}
