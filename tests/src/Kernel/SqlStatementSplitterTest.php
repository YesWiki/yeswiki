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
