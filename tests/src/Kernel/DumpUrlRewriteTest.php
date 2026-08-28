<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Database\DumpRewriter;

/**
 * A backup restored anywhere but where it was taken carries the source wiki's address in every stored link.
 *
 * Rewriting them is what makes a clone, a staging copy or a wiki that moved domain point at itself instead of at the wiki it came from.
 */
class DumpUrlRewriteTest extends TestCase
{
    #[DataProvider('rootProvider')]
    public function testTheRootIsWhatEveryStoredLinkStartsWith(string $baseUrl, string $expected): void
    {
        $this->assertSame($expected, DumpRewriter::root($baseUrl));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function rootProvider(): array
    {
        return [
            'a plain address gains the slash its links carry' => ['https://a.example/wiki', 'https://a.example/wiki/'],
            'a slash already there is left alone' => ['https://a.example/wiki/', 'https://a.example/wiki/'],
            'a query-style base keeps its root' => ['https://a.example/wiki/?', 'https://a.example/wiki/'],
            'surrounding space does not count' => ['  https://a.example/  ', 'https://a.example/'],
            'nothing at all' => ['', ''],
        ];
    }

    #[DataProvider('unsafeProvider')]
    public function testATargetThatWouldCorruptTheDumpIsRefused(string $baseUrl): void
    {
        $this->assertFalse(DumpRewriter::isSafeTarget($baseUrl));
    }

    /** @return array<string, array{0: string}> */
    public static function unsafeProvider(): array
    {
        return [
            'a double quote' => ['https://b.example/"'],
            'a single quote' => ["https://b.example/'"],
            'a backslash' => ['https://b.example/\\'],
            'a newline' => ["https://b.example/\n"],
            'nothing at all' => [''],
        ];
    }

    public function testNothingIsRewrittenWhenTheAddressIsUnchanged(): void
    {
        $this->assertSame([], DumpRewriter::substitutions('https://a.example/', 'https://a.example/'));
    }

    public function testNothingIsRewrittenWhenTheSourceIsUnknown(): void
    {
        $this->assertSame([], DumpRewriter::substitutions('', 'https://b.example/'));
    }

    public function testNothingIsRewrittenIntoAnUnsafeTarget(): void
    {
        $this->assertSame([], DumpRewriter::substitutions('https://a.example/', 'https://b.example/"'));
    }

    /** Both schemes are rewritten: a wiki that moved to https still has http links stored from before. */
    public function testEitherSchemeOfTheSourceIsRewritten(): void
    {
        $substitutions = DumpRewriter::substitutions('https://a.example/', 'https://b.example/');
        $sql = "INSERT INTO pages VALUES ('see https://a.example/PageOne and http://a.example/PageTwo')";

        $this->assertSame(
            "INSERT INTO pages VALUES ('see https://b.example/PageOne and https://b.example/PageTwo')",
            DumpRewriter::rewriteUrls($sql, $substitutions)
        );
    }

    /** An entry is json_encode'd into the page body, so its links reach the dump with escaped slashes. */
    public function testAnAddressInsideAJsonEncodedBodyIsRewrittenToo(): void
    {
        $substitutions = DumpRewriter::substitutions('https://a.example/', 'https://b.example/');
        $sql = 'INSERT INTO pages VALUES (\'{"bf_site":"https:\\\\/\\\\/a.example\\\\/PageOne"}\')';

        $rewritten = DumpRewriter::rewriteUrls($sql, $substitutions);

        $this->assertStringContainsString('https:\\\\/\\\\/b.example\\\\/PageOne', $rewritten);
        $this->assertStringNotContainsString('a.example', $rewritten);
    }

    public function testAnAddressThatIsNotTheSourceIsLeftAlone(): void
    {
        $substitutions = DumpRewriter::substitutions('https://a.example/', 'https://b.example/');
        $sql = "INSERT INTO pages VALUES ('https://elsewhere.example/PageOne')";

        $this->assertSame($sql, DumpRewriter::rewriteUrls($sql, $substitutions));
    }

    public function testTheDumpIsUntouchedWhenThereIsNothingToRewrite(): void
    {
        $sql = "INSERT INTO pages VALUES ('https://a.example/PageOne')";

        $this->assertSame($sql, DumpRewriter::rewriteUrls($sql, []));
    }
}
