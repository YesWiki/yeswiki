<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Core\Service\BaseUrlRewriter;

require_once 'includes/services/BaseUrlRewriter.php';

class BaseUrlRewriterTest extends TestCase
{
    #[DataProvider('rootProvider')]
    public function testRoot(string $input, string $expected)
    {
        $this->assertEquals($expected, BaseUrlRewriter::root($input));
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
        $this->assertFalse(BaseUrlRewriter::isSafeTarget($target));
        $this->assertEmpty(BaseUrlRewriter::substitutions('https://old.example/?', $target));
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
        $this->assertEmpty(BaseUrlRewriter::substitutions('https://same.example/?', 'https://same.example/'));
    }

    public function testNoSubstitutionWithoutASourceAddress()
    {
        $this->assertEmpty(BaseUrlRewriter::substitutions('', 'https://new.example/?'));
    }

    public function testPlainLinksAreRewritten()
    {
        $subs = BaseUrlRewriter::substitutions('https://old.example/wiki/?', 'https://new.example/?');
        $sql = "INSERT INTO `yw_pages` VALUES ('PageX','see https://old.example/wiki/?PageY and https://old.example/wiki/files/a.jpg');";

        $this->assertEquals(
            "INSERT INTO `yw_pages` VALUES ('PageX','see https://new.example/?PageY and https://new.example/files/a.jpg');",
            BaseUrlRewriter::rewrite($sql, $subs)
        );
    }

    public function testJsonEscapedLinksAreRewritten()
    {
        $subs = BaseUrlRewriter::substitutions('https://old.example/wiki/?', 'https://new.example/?');
        $entry = json_encode(['bf_image' => 'https://old.example/wiki/files/a.jpg']);
        $sql = "INSERT INTO `yw_pages` VALUES ('EntryX','" . addslashes($entry) . "');";

        $rewritten = BaseUrlRewriter::rewrite($sql, $subs);
        $this->assertStringContainsString('https:\\\\/\\\\/new.example\\\\/files\\\\/a.jpg', $rewritten);
        $this->assertStringNotContainsString('old.example', $rewritten);
        $this->assertEquals(
            ['bf_image' => 'https://new.example/files/a.jpg'],
            json_decode(stripslashes(substr($rewritten, strpos($rewritten, "'EntryX','") + 10, -3)), true)
        );
    }

    public function testBothSchemesOfTheSourceHostAreRewritten()
    {
        $subs = BaseUrlRewriter::substitutions('https://old.example/?', 'https://new.example/?');
        $sql = "('http://old.example/PageX','https://old.example/PageY')";

        $this->assertEquals(
            "('https://new.example/PageX','https://new.example/PageY')",
            BaseUrlRewriter::rewrite($sql, $subs)
        );
    }

    public function testALongerHostSharingThePrefixIsLeftAlone()
    {
        $subs = BaseUrlRewriter::substitutions('https://old.example/?', 'https://new.example/?');
        $sql = "('https://old.example.org/PageX')";

        $this->assertEquals($sql, BaseUrlRewriter::rewrite($sql, $subs));
    }

    public function testASubfolderSourceLeavesTheRestOfTheHostAlone()
    {
        $subs = BaseUrlRewriter::substitutions('https://host.example/wiki/?', 'https://new.example/?');
        $sql = "('https://host.example/other/PageX','https://host.example/wiki/PageY')";

        $this->assertEquals(
            "('https://host.example/other/PageX','https://new.example/PageY')",
            BaseUrlRewriter::rewrite($sql, $subs)
        );
    }

    public function testRewriteIsANoOpWithoutSubstitutions()
    {
        $sql = "('https://old.example/PageX')";

        $this->assertEquals($sql, BaseUrlRewriter::rewrite($sql, []));
    }
}
