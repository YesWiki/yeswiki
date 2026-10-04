<?php

namespace YesWiki\Test\Formatters;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for the markdown-image-compatibility XSS in the wakka formatter :
 * an unescaped/unvalidated src attribute allowed breaking out of the src="..." HTML
 * attribute (e.g. via a quote+tab) to inject an event-handler attribute.
 */
class WakkaFormatterTest extends YesWikiTestCase
{
    #[DataProvider('dataProviderTestMarkdownImageIsSafe')]
    public function testMarkdownImageIsSafe(string $markdown, ?string $expectedSrc)
    {
        $wiki = $this->getWiki();
        $html = $wiki->Format($markdown);

        // no <img> tag should ever carry an event-handler attribute or a javascript:/data: src,
        // regardless of how the rest of the markdown was (mis)parsed
        $this->assertDoesNotMatchRegularExpression(
            '/<img\b[^>]*\son[a-z]+\s*=/i',
            $html,
            "'$markdown' produced an <img> tag with an event-handler attribute: $html"
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<img\b[^>]*\ssrc\s*=\s*"(?:javascript|data|vbscript):/i',
            $html,
            "'$markdown' produced an <img> tag with a dangerous src scheme: $html"
        );

        if ($expectedSrc !== null) {
            $this->assertStringContainsString('src="' . $expectedSrc . '"', $html);
        }
    }

    public static function dataProviderTestMarkdownImageIsSafe(): array
    {
        return [
            'quote+tab attribute breakout (reported PoC)' => [
                "![](x\"\tonerror=alert&#40;document.domain&#41;)",
                null,
            ],
            'quote+space attribute breakout' => [
                '![](x" onerror=alert(1))',
                null,
            ],
            'javascript scheme' => [
                '![](javascript:alert(1))',
                null,
            ],
            'data scheme' => [
                '![](data:text/html,<script>alert(1)</script>)',
                null,
            ],
            'legitimate https image' => [
                '![alt text](https://example.com/img.png)',
                'https://example.com/img.png',
            ],
            'legitimate relative attachment path' => [
                '![alt text](files3/abc/photo.jpg "a title")',
                'files3/abc/photo.jpg',
            ],
        ];
    }

    /**
     * Regression test for a CPU-exhaustion DoS in the markdown-link branch of the
     * wakka formatter's mega-regex: an unbounded `[^\]]+`/`[^\)]+` scan restarts at
     * every `[` in the input, making a run of N `[` characters cost O(N^2).
     * A vulnerable formatter takes multiple seconds on this payload; a fixed one
     * (bounded quantifiers) completes in well under a second.
     */
    public function testMarkdownLinkBracketRunIsNotQuadratic()
    {
        $wiki = $this->getWiki();
        $payload = str_repeat('[', 60000);

        $start = microtime(true);
        $wiki->Format($payload);
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(
            2.0,
            $elapsed,
            "Formatting a run of '[' characters took {$elapsed}s: "
            . 'the markdown-link regex looks unbounded again (algorithmic-complexity DoS).'
        );
    }

    /** Each list item of the formatted text, as its nesting depth and its own text. */
    private function listItems(string $wikiText): array
    {
        $html = $this->getWiki()->Format($wikiText);
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>');
        $items = [];
        foreach ($dom->getElementsByTagName('li') as $li) {
            $depth = 0;
            for ($node = $li->parentNode; $node !== null; $node = $node->parentNode) {
                if (in_array($node->nodeName, ['ul', 'ol'], true)) {
                    $depth++;
                }
            }
            $own = '';
            foreach ($li->childNodes as $child) {
                if (!in_array($child->nodeName, ['ul', 'ol'], true)) {
                    $own .= $child->textContent;
                }
            }
            $items[] = [$depth, trim($own)];
        }

        return $items;
    }

    public static function indentedLists(): array
    {
        return [
            'spaces' => [" - a\n  - b", [[1, 'a'], [2, 'b']]],
            'tabs' => ["\t- a\n\t\t- b", [[1, 'a'], [2, 'b']]],
            'tab then spaces' => ["\t- a\n\t  - b", [[1, 'a'], [2, 'b']]],
            'a tab is wider than a space' => [" - a\n\t- b", [[1, 'a'], [2, 'b']]],
            'back to the first level' => [" - a\n  - b\n - c", [[1, 'a'], [2, 'b'], [1, 'c']]],
            'tabs back to the first level' => ["\t- a\n\t\t- b\n\t- c", [[1, 'a'], [2, 'b'], [1, 'c']]],
            'numbered' => [" 1) a\n 1) b", [[1, 'a'], [1, 'b']]],
        ];
    }

    #[DataProvider('indentedLists')]
    public function testIndentedLists(string $wikiText, array $expected)
    {
        $this->assertSame($expected, $this->listItems($wikiText));
    }
}
