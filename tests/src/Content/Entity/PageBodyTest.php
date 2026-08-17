<?php

namespace YesWiki\Test\Content\Entity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Content\Entity\PageBody;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 09: `pages.body` is one shape -- a JSON object -- for every Content type. */
class PageBodyTest extends TestCase
{
    public function testDecodeReturnsTheDecodedObject(): void
    {
        $this->assertSame(
            ['content' => 'hello', 'keywords' => ['a', 'b']],
            PageBody::decode('{"content":"hello","keywords":["a","b"]}')
        );
    }

    public function testDecodeOfAnEmptyBodyIsAnEmptyArray(): void
    {
        $this->assertSame([], PageBody::decode(''));
        $this->assertSame([], PageBody::decode('   '));
        $this->assertSame([], PageBody::decode(null));
    }

    /** The legacy fallback. */
    public function testDecodeOfLegacyMarkupWrapsItAsContent(): void
    {
        $markup = "# Title\nsome **text**";

        $this->assertSame(['content' => $markup], PageBody::decode($markup));
        $this->assertSame($markup, PageBody::content(PageBody::decode($markup)));
    }

    /**
     * Markup starting with `{` is why the migration cannot sniff the body's shape from its first character: 83 pages in the reference wiki open with an action call.
     */
    public function testDecodeOfMarkupStartingWithABraceIsStillMarkup(): void
    {
        $markup = '{{entrylist id="1"}}';

        $this->assertSame(['content' => $markup], PageBody::decode($markup));
    }

    public function testEncodeAlwaysProducesAJsonObject(): void
    {
        $this->assertSame('{}', PageBody::encode([]));

        $this->assertSame('{"0":"a","1":"b"}', PageBody::encode(['a', 'b']));
    }

    public function testEncodeLeavesUnicodeAndSlashesAlone(): void
    {
        $encoded = PageBody::encode(['content' => 'Expérience http://example.org/a']);

        $this->assertStringContainsString('Expérience', $encoded);
        $this->assertStringContainsString('http://example.org/a', $encoded);
        $this->assertStringNotContainsString('\u00e9', $encoded);
        $this->assertStringNotContainsString('\\/', $encoded);
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('roundTripProvider')]
    public function testRoundTripIsLossless(string $label, array $body): void
    {
        $this->assertSame($body, PageBody::decode(PageBody::encode($body)), $label);
    }

    /**
     * @return array<int, array{string, array<string, mixed>}>
     */
    public static function roundTripProvider(): array
    {
        return [
            ['accented text', ['content' => 'Expérience inspirante à Bordeaux']],
            ['a backslash', ['content' => 'C:\\Users\\path and a \\n that is not a newline']],
            ['real newlines and tabs', ['content' => "line one\nline two\n\ttabbed"]],
            ['double quotes', ['content' => 'He said "hello" to me']],
            ['wiki action markup', ['content' => '{{entrylist id="1" template="map"}}']],
            ['html', ['content' => '<div class="x">&amp; &lt;b&gt;</div>']],
            ['emoji', ['content' => 'ok 👍 done']],
            ['keywords list', ['content' => '', 'keywords' => ['accueil', 'mode d’emploi']]],
            ['nested field map', ['label' => ['1' => 'Site web', '2' => 'Expérience'], 'title' => 'Type']],
            ['empty string values', ['content' => '', 'title' => '']],
            ['numeric-ish strings stay strings', ['content' => '007', 'title' => '1.50']],
        ];
    }

    /** Encoding an already-decoded body must be a fixed point. */
    public function testEncodingIsIdempotent(): void
    {
        $body = ['content' => 'Expérience "quoted" and C:\\path', 'keywords' => ['à propos']];

        $once = PageBody::encode($body);
        $twice = PageBody::encode(PageBody::decode($once));

        $this->assertSame($once, $twice);
    }

    /**
     * A body that already carries the corruption must survive the codec unchanged rather than gaining another layer.
     */
    public function testAlreadyCorruptedTextIsNotEscapedAgain(): void
    {
        $corrupted = PageBody::decode('{"content":"Exp\\\\u00e9rience"}');

        $this->assertSame(['content' => 'Exp\\u00e9rience'], $corrupted);
        $this->assertSame($corrupted, PageBody::decode(PageBody::encode($corrupted)));
    }

    public function testContentOfABodyWithoutContentIsEmpty(): void
    {
        $this->assertSame('', PageBody::content([]));
        $this->assertSame('', PageBody::content(['title' => 'no prose here']));
    }

    public function testEqualsIgnoresKeyOrder(): void
    {
        $this->assertTrue(PageBody::equals(
            ['content' => 'x', 'title' => 'y'],
            ['title' => 'y', 'content' => 'x']
        ));
        $this->assertTrue(PageBody::equals(
            ['a' => ['n' => 1, 'm' => 2]],
            ['a' => ['m' => 2, 'n' => 1]]
        ));
    }

    public function testEqualsSeesRealDifferences(): void
    {
        $this->assertFalse(PageBody::equals(['content' => 'x'], ['content' => 'y']));
        $this->assertFalse(PageBody::equals(['content' => 'x'], ['content' => 'x', 'title' => 'y']));

        $this->assertFalse(PageBody::equals(['n' => '1'], ['n' => 1]));
    }
}
