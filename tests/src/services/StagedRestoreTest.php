<?php

namespace YesWiki\Test\Core\Services;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Database\DumpRewriter;

#[CoversMethod(DumpRewriter::class, 'renames')]
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
}
