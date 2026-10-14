<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A section's text colour follows the background it actually shows. */
class SectionInkTest extends YesWikiTestCase
{
    private function openingTag(string $wikiSource): string
    {
        $html = $this->getWiki()->services->get(MarkdownFormatterService::class)->format($wikiSource);
        preg_match('/<section[^>]*>/', $html, $m);

        return $m[0] ?? '';
    }

    public function testAFilledSectionTakesTheInkOfItsColour(): void
    {
        $tag = $this->openingTag("{{section bgcolor=\"#2b6d4f\"}}\ntexte\n{{end elem=\"section\"}}");

        $this->assertStringContainsString('color:var(--yw-ink-on-dark)', $tag);
    }

    public function testABorderedSectionKeepsThePageInkOnThePageSurface(): void
    {
        $tag = $this->openingTag("{{section bgcolor=\"#2b6d4f\" pattern=\"border-solid\"}}\ntexte\n{{end elem=\"section\"}}");

        $this->assertStringContainsString('background-color: var(--yw-surface)', $tag);
        $this->assertStringNotContainsString('--yw-ink-on', $tag);
    }
}
