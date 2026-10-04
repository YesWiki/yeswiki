<?php

namespace YesWiki\Test\Core\Services;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Database\DumpRewriter;

#[CoversMethod(DumpRewriter::class, 'renames')]
#[CoversMethod(DumpRewriter::class, 'quoteIndexNames')]
#[CoversMethod(DumpRewriter::class, 'constraints')]
class StagedRestoreTest extends TestCase
{
    private const DUMP = <<<'SQL'
        CREATE TABLE `other_pages` (`id` int);
        CREATE TABLE `other_triples` (`id` int);
        CREATE TABLE `other_search_index` (`id` int);
        INSERT INTO `other_pages` VALUES (1, 'a page mentioning other_pages in its body');
        SQL;

    public function testThePrefixIsReadFromTheDumpRatherThanAssumed(): void
    {
        $this->assertSame('other_', DumpRewriter::detectPrefix(self::DUMP));
    }

    public function testAPostgresOrSqliteDumpIsReadToo(): void
    {
        $quoted = 'CREATE TABLE "wiki_pages" (id int);' . "\n" . 'CREATE TABLE "wiki_triples" (id int);';

        $this->assertSame('wiki_', DumpRewriter::detectPrefix($quoted));
    }

    public function testEveryTableIsRenamedOntoTheStagingPrefix(): void
    {
        $renames = DumpRewriter::renames(DumpRewriter::tables(self::DUMP), 'other_', 'ywstaging123_');

        $this->assertSame([
            'other_pages' => 'ywstaging123_pages',
            'other_triples' => 'ywstaging123_triples',
            'other_search_index' => 'ywstaging123_search_index',
        ], $renames);
    }

    /** Only quoted identifiers are rewritten: a page whose text happens to name a table is content, not schema, and rewriting it would corrupt the wiki being restored. */
    public function testAPageThatMentionsATableNameIsLeftAlone(): void
    {
        $renames = DumpRewriter::renames(DumpRewriter::tables(self::DUMP), 'other_', 'ywstaging123_');
        $rewritten = DumpRewriter::rewrite(
            "INSERT INTO `other_pages` VALUES (1, 'a page mentioning other_pages in its body')",
            $renames
        );

        $this->assertStringContainsString('INSERT INTO `ywstaging123_pages`', $rewritten);
        $this->assertStringContainsString("'a page mentioning other_pages in its body'", $rewritten);
    }

    public function testADumpThatIsNotAWikiBackupNamesNoPrefix(): void
    {
        $this->assertSame('', DumpRewriter::detectPrefix('CREATE TABLE `invoices` (`id` int);'));
    }

    public function testAStagingPrefixIsNeverAPrefixOfTheLiveOne(): void
    {
        foreach (['yeswiki_', 'yw_', 'ywstaging_'] as $live) {
            $isolated = self::isolatedPrefix($live, 'staging');
            $this->assertFalse(str_starts_with($isolated, $live), "$isolated starts with $live");
            $this->assertFalse(str_starts_with($live, $isolated), "$live starts with $isolated");
        }
    }

    public function testARenameIsRefusedOntoAPrefixThatIsNotAnIdentifier(): void
    {
        $tables = DumpRewriter::tables(self::DUMP);

        $this->assertSame([], DumpRewriter::renames($tables, 'other_', 'staging`; DROP TABLE x; --'));
        $this->assertSame([], DumpRewriter::renames($tables, 'other_', 'other_'));
        $this->assertSame([], DumpRewriter::renames($tables, '', 'staging_'));
    }

    private static function isolatedPrefix(string $livePrefix, string $tag): string
    {
        $prefix = 'yw' . $tag . substr(sha1($livePrefix), 0, 6) . '_';
        while (str_starts_with($prefix, $livePrefix) || str_starts_with($livePrefix, $prefix)) {
            $prefix = "x$prefix";
        }

        return $prefix;
    }

    public function testAnotherWikiUnderALongerPrefixIsNotThisOnes(): void
    {
        $tables = ['yeswiki_pages', 'yeswiki_triples', 'yeswiki_journal', 'yeswiki_ecto__pages', 'yeswiki_ecto__triples', 'yeswiki_ecto__journal', 'unrelated'];

        $this->assertSame(['yeswiki_ecto__'], DumpRewriter::otherWikiPrefixes($tables, 'yeswiki_'));
        $this->assertSame(['yeswiki_pages', 'yeswiki_triples', 'yeswiki_journal'], DumpRewriter::ownTables($tables, 'yeswiki_'));
        $this->assertSame(['yeswiki_ecto__pages', 'yeswiki_ecto__triples', 'yeswiki_ecto__journal'], DumpRewriter::ownTables($tables, 'yeswiki_ecto__'));
    }

    /** One stray table ending like a core one is not a wiki: it stays with the prefix it starts with. */
    public function testASingleLookalikeTableIsNotAWiki(): void
    {
        $tables = ['yeswiki_pages', 'yeswiki_triples', 'yeswiki_old_pages'];

        $this->assertSame([], DumpRewriter::otherWikiPrefixes($tables, 'yeswiki_'));
        $this->assertSame($tables, DumpRewriter::ownTables($tables, 'yeswiki_'));
    }

    public function testAStatementIsJudgedOnTheTablesItNamesNotOnItsData(): void
    {
        $foreign = ['yeswiki_ecto__pages' => true];

        $this->assertTrue(DumpRewriter::concerns('INSERT INTO `yeswiki_ecto__pages` VALUES (1)', $foreign));
        $this->assertTrue(DumpRewriter::concerns('CREATE TABLE "yeswiki_ecto__pages" (id int)', $foreign));
        $this->assertFalse(DumpRewriter::concerns('INSERT INTO `yeswiki_pages` VALUES (\'{"t":"yeswiki_ecto__pages"}\')', $foreign));
        $this->assertFalse(DumpRewriter::concerns('INSERT INTO `yeswiki_ecto__pages` VALUES (1)', []));
    }

    public function testAPostgreSqlIndexDefinitionGetsQuotedNamesThatARenameCanFind(): void
    {
        $this->assertSame(
            'CREATE UNIQUE INDEX "other_pages_idx_tag" ON "other_pages" USING btree (tag)',
            DumpRewriter::quoteIndexNames('CREATE UNIQUE INDEX other_pages_idx_tag ON public.other_pages USING btree (tag)')
        );
        $this->assertSame(
            'CREATE INDEX "Mixed" ON "other_pages"(tag)',
            DumpRewriter::quoteIndexNames('CREATE INDEX "Mixed" ON other_pages(tag)')
        );
        $this->assertSame("INSERT INTO \"other_pages\" VALUES ('CREATE INDEX x ON y (z)')", DumpRewriter::quoteIndexNames("INSERT INTO \"other_pages\" VALUES ('CREATE INDEX x ON y (z)')"));
    }

    public function testConstraintNamesAreFoundForTheRestoreToRename(): void
    {
        $this->assertSame(
            ['other_pages_pkey', 'other_pages_latest_check'],
            DumpRewriter::constraints('CREATE TABLE "other_pages" ("id" integer, CONSTRAINT "other_pages_pkey" PRIMARY KEY (id), CONSTRAINT "other_pages_latest_check" CHECK (latest IN (\'Y\')))')
        );
    }
}
