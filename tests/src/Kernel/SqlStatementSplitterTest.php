<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Database\SqlStatementSplitter;

/**
 * Ticket 17: archive restore stopped going through `mysqli_multi_query()`, so splitting a
 * dump into statements became our job.
 *
 * These are the cases where a naive `explode(';')` corrupts rather than fails: a semicolon
 * inside a wiki page's stored body is not hypothetical -- page content is full of prose,
 * HTML entities and `{{action}}` calls.
 */
class SqlStatementSplitterTest extends TestCase
{
    public function testSplitsOnSemicolons(): void
    {
        $this->assertSame(
            ['SELECT 1', 'SELECT 2'],
            SqlStatementSplitter::split('SELECT 1; SELECT 2;')
        );
    }

    public function testATrailingSemicolonIsOptional(): void
    {
        $this->assertSame(['SELECT 1'], SqlStatementSplitter::split('SELECT 1'));
    }

    public function testEmptyStatementsAreDropped(): void
    {
        // old backups contain bare semicolons where a table had no rows
        $this->assertSame(['SELECT 1'], SqlStatementSplitter::split(";\n;\nSELECT 1;\n;\n"));
    }

    public function testASemicolonInsideAStringIsNotASplit(): void
    {
        $sql = "INSERT INTO `pages` (`body`) VALUES ('a; b; c');";
        $this->assertSame(["INSERT INTO `pages` (`body`) VALUES ('a; b; c')"], SqlStatementSplitter::split($sql));
    }

    public function testADoubledQuoteInsideAStringDoesNotEndIt(): void
    {
        $sql = "INSERT INTO t VALUES ('it''s; fine');";
        $this->assertSame(["INSERT INTO t VALUES ('it''s; fine')"], SqlStatementSplitter::split($sql));
    }

    /** What our own dump emits, and MySQL's default escaping. */
    public function testABackslashEscapedQuoteInsideAStringDoesNotEndIt(): void
    {
        $sql = "INSERT INTO t VALUES ('it\\'s; fine');";
        $this->assertSame(["INSERT INTO t VALUES ('it\\'s; fine')"], SqlStatementSplitter::split($sql));
    }

    public function testASemicolonInsideAQuotedIdentifierIsNotASplit(): void
    {
        $sql = 'SELECT `weird;column` FROM t; SELECT 2;';
        $this->assertSame(['SELECT `weird;column` FROM t', 'SELECT 2'], SqlStatementSplitter::split($sql));
    }

    public function testASemicolonInsideALineCommentIsNotASplit(): void
    {
        $sql = "-- a comment; with a semicolon\nSELECT 1;";
        $this->assertSame(['SELECT 1'], SqlStatementSplitter::split($sql));
    }

    public function testASemicolonInsideABlockCommentIsNotASplit(): void
    {
        $sql = '/* a comment; here */ SELECT 1;';
        $this->assertSame(['SELECT 1'], SqlStatementSplitter::split($sql));
    }

    public function testCommentOnlyChunksAreDropped(): void
    {
        $sql = "-- SQL Dump\n-- Generated on : 2026-07-31\n\nSELECT 1;";
        $this->assertSame(['SELECT 1'], SqlStatementSplitter::split($sql));
    }

    /**
     * MySQL's version-gated comments are executable statements in a MySQL dump, so they must
     * survive rather than be treated as commentary.
     */
    public function testMysqlExecutableCommentsSurvive(): void
    {
        $sql = "/*!40101 SET NAMES utf8mb4 */;\nSELECT 1;";
        $this->assertSame(['/*!40101 SET NAMES utf8mb4 */', 'SELECT 1'], SqlStatementSplitter::split($sql));
    }

    /** `--` is only a comment when followed by whitespace; `5--3` is arithmetic. */
    public function testADoubleHyphenWithoutWhitespaceIsNotAComment(): void
    {
        $this->assertSame(['SELECT 5--3', 'SELECT 2'], SqlStatementSplitter::split('SELECT 5--3; SELECT 2;'));
    }

    public function testAHashLineCommentIsHandled(): void
    {
        $sql = "# a comment; here\nSELECT 1;";
        $this->assertSame(['SELECT 1'], SqlStatementSplitter::split($sql));
    }

    /** An unterminated literal must not swallow the splitter -- the statement fails on execution instead. */
    /**
     * A trigger body is a compound statement, and its inner semicolons are not boundaries.
     *
     * SQLite's search index (ADR-0015) is maintained by exactly these triggers, so before this a
     * SQLite archive containing a search index could not be restored at all: the split cut the
     * body in half and the replay died on "incomplete input". The failure was at restore time,
     * not backup time -- the archive looked fine until the day it was needed.
     */
    public function testATriggerBodyIsOneStatement(): void
    {
        $trigger = 'CREATE TRIGGER "idx_ai" AFTER INSERT ON "idx" BEGIN'
            . ' INSERT INTO "idx_fts"(rowid, "title") VALUES (new."id", new."title");'
            . ' END';

        $this->assertSame(
            ['SELECT 1', $trigger, 'SELECT 2'],
            SqlStatementSplitter::split('SELECT 1; ' . $trigger . '; SELECT 2;')
        );
    }

    public function testATriggerBodyWithSeveralStatementsIsStillOneStatement(): void
    {
        $trigger = 'CREATE TRIGGER "t" AFTER DELETE ON "a" BEGIN'
            . ' DELETE FROM "b" WHERE id = old.id;'
            . ' INSERT INTO "c"(id) VALUES (old.id);'
            . ' END';

        $this->assertSame([$trigger], SqlStatementSplitter::split($trigger . ';'));
    }

    /** A CASE ... END inside the body must not close the block early. */
    public function testACaseExpressionInsideATriggerDoesNotEndTheBlock(): void
    {
        $trigger = 'CREATE TRIGGER "t" AFTER UPDATE ON "a" BEGIN'
            . ' UPDATE "a" SET n = CASE WHEN new.n > 0 THEN 1 ELSE 0 END WHERE id = new.id;'
            . ' END';

        $this->assertSame([$trigger], SqlStatementSplitter::split($trigger . ';'));
    }

    /**
     * The narrowness of the trigger rule, pinned. PostgreSQL's dump preamble is a bare `BEGIN;`
     * and its epilogue a bare `COMMIT;` -- if `BEGIN` opened a block unconditionally, an entire
     * pgsql dump would come back as one unterminated statement and no restore would ever work.
     */
    public function testABareBeginIsItsOwnStatement(): void
    {
        $this->assertSame(
            ['BEGIN', 'INSERT INTO "t" ("a") VALUES (\'1\')', 'COMMIT'],
            SqlStatementSplitter::split('BEGIN;' . "\n" . 'INSERT INTO "t" ("a") VALUES (\'1\');' . "\n" . 'COMMIT;')
        );
    }

    /** `END` as part of a longer word is not the block keyword. */
    public function testAWordContainingEndIsNotTheKeyword(): void
    {
        $trigger = 'CREATE TRIGGER "t" AFTER INSERT ON "a" BEGIN'
            . " INSERT INTO \"appendix\"(\"legend\") VALUES ('x');"
            . ' END';

        $this->assertSame([$trigger], SqlStatementSplitter::split($trigger . ';'));
    }

    public function testAnUnterminatedStringDoesNotLoop(): void
    {
        $this->assertSame(["SELECT 'oops"], SqlStatementSplitter::split("SELECT 'oops"));
    }

    public function testARealisticDumpRoundTrips(): void
    {
        $dump = <<<'SQL'
            -- SQL Dump
            -- Generated on : 2026-07-31

            /*!40101 SET NAMES utf8mb4 */;

            --
            -- Structure of table : `yw_pages`
            --
            CREATE TABLE `yw_pages` (`tag` VARCHAR(255), `body` LONGTEXT);

            --
            -- Data of table : `yw_pages`
            --
            INSERT INTO `yw_pages` (`tag`, `body`) VALUES ('Home', '{"content":"a; b"}'),
            ('Other', 'it''s got; semicolons');
            SQL;

        $statements = SqlStatementSplitter::split($dump);

        $this->assertCount(3, $statements);
        $this->assertStringStartsWith('/*!40101', $statements[0]);
        $this->assertStringContainsString('CREATE TABLE', $statements[1]);
        $this->assertStringContainsString("'{\"content\":\"a; b\"}'", $statements[2]);
        $this->assertStringContainsString("it''s got; semicolons", $statements[2]);
    }
}
