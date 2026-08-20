<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Core\Entity\DumpRewrite;
use YesWiki\Core\Service\DumpRewriter;

require_once 'includes/entities/DumpRewrite.php';
require_once 'includes/services/DumpRewriter.php';

class DumpRewriterTest extends TestCase
{
    private static function dump(array $tables): string
    {
        $sql = "-- SQL Dump\n\n";
        foreach ($tables as $table) {
            $sql .= "-- \n-- Structure of table : `$table`\n-- \n";
            $sql .= "CREATE TABLE `$table` (\n  `tag` varchar(191) NOT NULL\n) ENGINE=InnoDB;\n\n";
            $sql .= "INSERT INTO `$table` (`tag`) VALUES\n('PagePrincipale');\n\n";
        }

        return $sql;
    }

    public function testTablesAreListedOnce()
    {
        $this->assertSame(
            ['yeswiki_pages', 'yeswiki_acls'],
            DumpRewriter::tables(self::dump(['yeswiki_pages', 'yeswiki_acls']))
        );
    }

    public function testDetectFindsThePrefixOfACompleteDump()
    {
        $tables = array_map(function ($table) {
            return "yeswiki_$table";
        }, DumpRewriter::CORE_TABLES);

        $this->assertSame('yeswiki_', DumpRewriter::detectPrefix(self::dump($tables)));
    }

    public function testDetectIgnoresAnotherWikiSweptInByTheBackup()
    {
        $sql = self::dump([
            'yeswiki_acls', 'yeswiki_pages', 'yeswiki_triples', 'yeswiki_users',
            'yeswiki_second__acls', 'yeswiki_second__pages', 'yeswiki_second__triples', 'yeswiki_second__users',
        ]);

        $this->assertSame('yeswiki_', DumpRewriter::detectPrefix($sql));
    }

    public function testDetectFindsNothingInAForeignDump()
    {
        $this->assertSame('', DumpRewriter::detectPrefix(self::dump(['wp_posts', 'wp_options'])));
    }

    public function testDetectFindsNothingWithoutAPrefix()
    {
        $this->assertSame('', DumpRewriter::detectPrefix(self::dump(['pages', 'acls', 'users'])));
    }

    public function testRewriteRenamesEveryTableOfTheDump()
    {
        $sql = self::dump(['yeswiki_pages', 'yeswiki_acls', 'yeswiki_second__pages']);
        $rewritten = DumpRewriter::rewriteTables($sql, 'yeswiki_', 'wiki2_');

        $this->assertSame(
            ['wiki2_pages', 'wiki2_acls', 'wiki2_second__pages'],
            DumpRewriter::tables($rewritten)
        );
        $this->assertStringContainsString('INSERT INTO `wiki2_pages`', $rewritten);
        $this->assertStringNotContainsString('yeswiki_', $rewritten);
    }

    public function testRewriteLeavesTheStoredContentAlone()
    {
        $sql = "CREATE TABLE `yeswiki_pages` (\n  `body` text\n);\n"
            . "INSERT INTO `yeswiki_pages` (`body`) VALUES\n('yeswiki_pages holds the pages');\n";

        $this->assertStringContainsString(
            "('yeswiki_pages holds the pages')",
            DumpRewriter::rewriteTables($sql, 'yeswiki_', 'wiki2_')
        );
    }

    #[DataProvider('unchangedProvider')]
    public function testDumpIsUntouched(string $from, string $to)
    {
        $sql = self::dump(['yeswiki_pages']);

        $this->assertSame($sql, DumpRewriter::rewriteTables($sql, $from, $to));
    }

    public static function unchangedProvider()
    {
        return [
            'same prefix' => ['yeswiki_', 'yeswiki_'],
            'unknown source' => ['', 'wiki2_'],
            'source names no table' => ['absent_', 'wiki2_'],
            'target with a backtick' => ['yeswiki_', 'wiki`2_'],
            'target with a quote' => ['yeswiki_', "wiki'2_"],
            'empty target' => ['yeswiki_', ''],
        ];
    }

    #[DataProvider('invalidPrefixProvider')]
    public function testInvalidPrefixesAreRefused(string $prefix)
    {
        $this->assertFalse(DumpRewriter::isValidPrefix($prefix));
    }

    public static function invalidPrefixProvider()
    {
        return [
            [''],
            ['wiki`2_'],
            ["wiki'2_"],
            ['wiki 2_'],
            ['wiki-2_'],
            ['wiki.2_'],
            ["wiki\n2_"],
        ];
    }

    public function testValidPrefixIsAccepted()
    {
        $this->assertTrue(DumpRewriter::isValidPrefix('yeswiki_'));
        $this->assertTrue(DumpRewriter::isValidPrefix('yw2'));
    }

    private static function info(): array
    {
        return ['base_url' => 'https://old.example/wiki/?', 'table_prefix' => 'yeswiki_'];
    }

    public function testPrepareRenamesTablesAndRewritesLinksAtOnce()
    {
        $sql = self::dump(['yeswiki_pages', 'yeswiki_acls'])
            . "INSERT INTO `yeswiki_pages` (`body`) VALUES ('see https://old.example/wiki/?PageY');\n";

        $dump = DumpRewriter::prepare($sql, self::info(), 'wiki2_', 'https://new.example/?');

        $this->assertInstanceOf(DumpRewrite::class, $dump);
        $this->assertSame(['wiki2_pages', 'wiki2_acls'], DumpRewriter::tables($dump->sql));
        $this->assertStringContainsString('https://new.example/?PageY', $dump->sql);
        $this->assertTrue($dump->renamedTables());
        $this->assertTrue($dump->rewroteUrls());
        $this->assertSame('yeswiki_', $dump->prefixFrom);
        $this->assertSame('wiki2_', $dump->prefixTo);
        $this->assertSame('https://old.example/wiki/', $dump->urlFrom);
        $this->assertSame('https://new.example/', $dump->urlTo);
    }

    public function testPrepareReportsNothingWhenNothingChanges()
    {
        $dump = DumpRewriter::prepare(self::dump(['yeswiki_pages']), self::info(), 'yeswiki_', 'https://old.example/wiki/?');

        $this->assertFalse($dump->renamedTables());
        $this->assertFalse($dump->rewroteUrls());
        $this->assertSame('', $dump->urlFrom);
    }

    public function testPrepareLeavesLinksAloneWhenAsked()
    {
        $sql = self::dump(['yeswiki_pages']) . "INSERT INTO `yeswiki_pages` (`body`) VALUES ('https://old.example/wiki/?PageY');\n";

        $dump = DumpRewriter::prepare($sql, self::info(), 'yeswiki_', 'https://new.example/?', false);

        $this->assertStringContainsString('https://old.example/wiki/?PageY', $dump->sql);
        $this->assertFalse($dump->rewroteUrls());
    }

    public function testPrepareTrustsTheDumpOverAnInfoFileThatLies()
    {
        $dump = DumpRewriter::prepare(self::dump(['yeswiki_pages']), ['table_prefix' => 'lying_'], 'wiki2_', '');

        $this->assertSame('yeswiki_', $dump->prefixFrom);
        $this->assertSame(['wiki2_pages'], DumpRewriter::tables($dump->sql));
    }

    public function testPrepareFallsBackToTheInfoFileOfAnUnreadableDump()
    {
        $dump = DumpRewriter::prepare('-- empty dump', ['table_prefix' => 'yeswiki_'], 'wiki2_', '');

        $this->assertSame('yeswiki_', $dump->prefixFrom);
    }

    public function testPrepareRefusesADumpItCannotRead()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(DumpRewriter::UNKNOWN_SOURCE_PREFIX);
        DumpRewriter::prepare(self::dump(['wp_posts']), [], 'wiki2_', '');
    }

    public function testPrepareRefusesAnUnusableTargetPrefix()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(DumpRewriter::INVALID_TARGET_PREFIX);
        DumpRewriter::prepare(self::dump(['yeswiki_pages']), [], "wiki'2_", '');
    }

    #[DataProvider('rootProvider')]
    public function testRoot(string $input, string $expected)
    {
        $this->assertEquals($expected, DumpRewriter::root($input));
    }

    public static function rootProvider()
    {
        return [
            ['https://old.example/?', 'https://old.example/'],
            ['https://old.example/', 'https://old.example/'],
            ['https://old.example', 'https://old.example/'],
            ['https://old.example/wiki/?', 'https://old.example/wiki/'],
            ['  https://old.example/wiki/??  ', 'https://old.example/wiki/'],
            ['https://old.example/wiki/index.php?', 'https://old.example/wiki/index.php'],
            ['', ''],
            ['?', ''],
        ];
    }

    #[DataProvider('unsafeTargetProvider')]
    public function testUnsafeTargetsAreRefused(string $target)
    {
        $this->assertFalse(DumpRewriter::isSafeTarget($target));
        $this->assertEmpty(DumpRewriter::substitutions('https://old.example/?', $target));
    }

    public static function unsafeTargetProvider()
    {
        return [
            ["https://new.example/'; DROP TABLE x; --/"],
            ['https://new.example/"/'],
            ['https://new.example/\\/'],
            ["https://new.example/\n/"],
            [''],
        ];
    }

    public function testNoSubstitutionWhenAddressDidNotChange()
    {
        $this->assertEmpty(DumpRewriter::substitutions('https://same.example/?', 'https://same.example/'));
    }

    public function testNoSubstitutionWithoutASourceAddress()
    {
        $this->assertEmpty(DumpRewriter::substitutions('', 'https://new.example/?'));
    }

    public function testPlainLinksAreRewritten()
    {
        $subs = DumpRewriter::substitutions('https://old.example/wiki/?', 'https://new.example/?');
        $sql = "INSERT INTO `yw_pages` VALUES ('PageX','see https://old.example/wiki/?PageY and https://old.example/wiki/files/a.jpg');";

        $this->assertEquals(
            "INSERT INTO `yw_pages` VALUES ('PageX','see https://new.example/?PageY and https://new.example/files/a.jpg');",
            DumpRewriter::rewriteUrls($sql, $subs)
        );
    }

    public function testJsonEscapedLinksAreRewritten()
    {
        $subs = DumpRewriter::substitutions('https://old.example/wiki/?', 'https://new.example/?');
        $entry = json_encode(['bf_image' => 'https://old.example/wiki/files/a.jpg']);
        $sql = "INSERT INTO `yw_pages` VALUES ('EntryX','" . addslashes($entry) . "');";

        $rewritten = DumpRewriter::rewriteUrls($sql, $subs);
        $this->assertStringContainsString('https:\\\\/\\\\/new.example\\\\/files\\\\/a.jpg', $rewritten);
        $this->assertStringNotContainsString('old.example', $rewritten);
        $this->assertEquals(
            ['bf_image' => 'https://new.example/files/a.jpg'],
            json_decode(stripslashes(substr($rewritten, strpos($rewritten, "'EntryX','") + 10, -3)), true)
        );
    }

    public function testBothSchemesOfTheSourceHostAreRewritten()
    {
        $subs = DumpRewriter::substitutions('https://old.example/?', 'https://new.example/?');
        $sql = "('http://old.example/PageX','https://old.example/PageY')";

        $this->assertEquals(
            "('https://new.example/PageX','https://new.example/PageY')",
            DumpRewriter::rewriteUrls($sql, $subs)
        );
    }

    public function testALongerHostSharingThePrefixIsLeftAlone()
    {
        $subs = DumpRewriter::substitutions('https://old.example/?', 'https://new.example/?');
        $sql = "('https://old.example.org/PageX')";

        $this->assertEquals($sql, DumpRewriter::rewriteUrls($sql, $subs));
    }

    public function testASubfolderSourceLeavesTheRestOfTheHostAlone()
    {
        $subs = DumpRewriter::substitutions('https://host.example/wiki/?', 'https://new.example/?');
        $sql = "('https://host.example/other/PageX','https://host.example/wiki/PageY')";

        $this->assertEquals(
            "('https://host.example/other/PageX','https://new.example/PageY')",
            DumpRewriter::rewriteUrls($sql, $subs)
        );
    }

    public function testRewriteIsANoOpWithoutSubstitutions()
    {
        $sql = "('https://old.example/PageX')";

        $this->assertEquals($sql, DumpRewriter::rewriteUrls($sql, []));
    }
}
