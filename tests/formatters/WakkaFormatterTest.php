<?php

namespace YesWiki\Test\Formatters;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The markdown formatter's output for images and links carries no script.
 */
class WakkaFormatterTest extends YesWikiTestCase
{
    #[DataProvider('dataProviderTestMarkdownImageIsSafe')]
    public function testMarkdownImageIsSafe(string $markdown, ?string $expectedSrc): void
    {
        $wiki = $this->getWiki();
        $html = $wiki->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format($markdown);

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

    /**
     * @return array<string, array{string, string|null}>
     */
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
     * Regression test for a CPU-exhaustion DoS in the markdown-link branch of the wakka formatter's mega-regex: an unbounded `[^\]]+`/`[^\)]+` scan restarts at every `[` in the input, making a run of N `[` characters cost O(N^2).
     */
    public function testMarkdownLinkBracketRunIsNotQuadratic(): void
    {
        $wiki = $this->getWiki();
        $payload = str_repeat('[', 60000);

        $start = microtime(true);
        $wiki->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format($payload);
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(
            2.0,
            $elapsed,
            "Formatting a run of '[' characters took {$elapsed}s: "
            . 'the markdown-link regex looks unbounded again (algorithmic-complexity DoS).'
        );
    }

    /** @return list<array<string, string>> the attributes of each link in the formatted text, in document order */
    private function linkAttributes(string $markdown): array
    {
        $html = $this->getWiki()->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format($markdown);
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

    /** @return array<string, array{string}> */
    public static function hostileLinks(): array
    {
        return [
            'event handler attribute' => ['[x](https://example.com){onmouseover=alert(1)}'],
            'event handler on a wiki link' => ['[x](PagePrincipale){onfocus=alert(1)}'],
            'double quote inside a single-quoted value' => ["[x](https://example.com){title='a\" onmouseover=\"alert(1)'}"],
            'double quote inside a wiki link value' => ["[x](PagePrincipale){data-x='a\" onfocus=\"alert(1)'}"],
            'javascript destination' => ['[x](javascript:alert(1))'],
            'data destination' => ['[x](data:text/html,<script>alert(1)</script>)'],
        ];
    }

    #[DataProvider('hostileLinks')]
    public function testALinkCarriesNoScript(string $markdown): void
    {
        $links = $this->linkAttributes($markdown);
        $this->assertNotEmpty($links, $markdown);
        foreach ($links as $attrs) {
            foreach (array_keys($attrs) as $name) {
                $this->assertStringStartsNotWith('on', strtolower($name), "$markdown kept $name");
            }
            $this->assertDoesNotMatchRegularExpression('/^\s*(javascript|vbscript|data):/i', $attrs['href'] ?? '', $markdown);
        }
    }

    public function testAQuotedValueKeepsItsQuoteAsText(): void
    {
        $links = $this->linkAttributes("[x](https://example.com){title='a\"b' .btn target=_blank}");

        $this->assertSame('a"b', $links[0]['title']);
        $this->assertSame('btn', $links[0]['class']);
        $this->assertSame('_blank', $links[0]['target']);
    }
}
