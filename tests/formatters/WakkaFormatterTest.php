<?php

namespace YesWiki\Test\Formatters;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for the markdown-image-compatibility XSS in the wakka formatter : an unescaped/unvalidated src attribute allowed breaking out of the src="..." HTML attribute (e.g.
 */
class WakkaFormatterTest extends YesWikiTestCase
{
    #[DataProvider('dataProviderTestMarkdownImageIsSafe')]
    public function testMarkdownImageIsSafe(string $markdown, ?string $expectedSrc)
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
    public function testMarkdownLinkBracketRunIsNotQuadratic()
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
}
