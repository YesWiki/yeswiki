<?php

namespace YesWiki\Test\Formatters;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The wakka formatter's output for markdown images, links and indented lists.
 */
class WakkaFormatterTest extends YesWikiTestCase
{
    #[DataProvider('dataProviderTestMarkdownImageIsSafe')]
    public function testMarkdownImageIsSafe(string $markdown, ?string $expectedSrc)
    {
        $wiki = $this->getWiki();
        $html = $wiki->Format($markdown);

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

    /** A run of `[` formats in linear time. */
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

    /** The attributes of each link in the formatted text, in document order. */
    private function linkAttributes(string $wikiText): array
    {
        $html = $this->getWiki()->Format($wikiText);
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>');
        $links = [];
        foreach ($dom->getElementsByTagName('a') as $a) {
            $attrs = [];
            foreach ($a->attributes as $attr) {
                $attrs[$attr->name] = $attr->value;
            }
            $links[] = $attrs;
        }

        return $links;
    }

    public static function hostileLinks(): array
    {
        return [
            'event handler in markdown extra' => ['[x](https://example.com){onmouseover=alert(1)}'],
            'event handler on a wiki link' => ['[x](PagePrincipale){onfocus=alert(1) autofocus=1}'],
            'quote breakout from a value' => ['[x](https://example.com){title=a"onmouseover="alert(1)}'],
            'quote breakout from an id' => ['[x](https://example.com){#a"onmouseover="alert(1)}'],
            'style attribute' => ['[x](https://example.com){style=position:fixed}'],
            'javascript destination' => ['[x](javascript:alert(1))'],
            'uppercase javascript destination' => ['[x](JavaScript:alert(1))'],
            'data destination' => ['[x](data:text/html,<script>alert(1)</script>)'],
            'javascript forced link' => ['[[javascript:alert(1) x]]'],
            'vbscript forced link' => ['[[vbscript:msgbox(1) x]]'],
        ];
    }

    #[DataProvider('hostileLinks')]
    public function testALinkCarriesNoScript(string $wikiText)
    {
        $this->assertStringContainsString('x', strip_tags($this->getWiki()->Format($wikiText)));
        foreach ($this->linkAttributes($wikiText) as $attrs) {
            foreach ($attrs as $name => $value) {
                $this->assertStringStartsNotWith('on', strtolower($name), "$wikiText kept $name");
                $this->assertNotSame('style', $name, "$wikiText kept style");
                $this->assertNotSame('autofocus', $name, "$wikiText kept autofocus");
            }
            $this->assertDoesNotMatchRegularExpression('/^\s*(javascript|vbscript|data):/i', $attrs['href'] ?? '', $wikiText);
        }
    }

    public function testMarkdownExtraKeepsLinkAttributes()
    {
        $links = $this->linkAttributes('[x](https://example.com){#anchor .btn.btn-primary target=_blank title="Title" data-size=modal-lg}');

        $this->assertCount(1, $links);
        $this->assertSame('https://example.com', $links[0]['href']);
        $this->assertSame('anchor', $links[0]['id']);
        $this->assertSame('btn btn-primary', $links[0]['class']);
        $this->assertSame('_blank', $links[0]['target']);
        $this->assertSame('Title', $links[0]['title']);
        $this->assertSame('modal-lg', $links[0]['data-size']);
    }

    public function testAnAttributeValueKeepsItsQuoteAsText()
    {
        $links = $this->linkAttributes('[x](https://example.com){title=a"b}');

        $this->assertSame('a"b', $links[0]['title']);
    }

    public function testOrdinarySchemesStayLinks()
    {
        $this->assertSame('mailto:a@example.com', $this->linkAttributes('[x](mailto:a@example.com)')[0]['href']);
        $this->assertSame('tel:+33100000000', $this->linkAttributes('[x](tel:+33100000000)')[0]['href']);
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
