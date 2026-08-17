<?php

namespace YesWiki\Test\Formatters;

use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A line holding more than one `{{action}}` tag. */
class ActionTagsOnOneLineTest extends YesWikiTestCase
{
    private function format(string $markdown): string
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;

        return $wiki->services->get(MarkdownFormatterService::class)->format($markdown);
    }

    public function testAPairOnOneLineKeepsItsTextAndCloses(): void
    {
        $html = $this->format('{{label}}texte{{end elem="label"}}');

        $this->assertStringContainsString('>texte</span>', $html, 'the body must survive, inside the label');
    }

    public function testEachPairOnConsecutiveLinesIsItsOwn(): void
    {
        $html = $this->format(
            '{{label class="yw-label--warning"}}un{{end elem="label"}}' . "\n"
            . '{{label class="yw-label--danger"}}deux{{end elem="label"}}'
        );

        $this->assertSame(2, substr_count($html, '</span>'), 'both labels close');
        $this->assertStringContainsString('>un</span>', $html);
        $this->assertStringContainsString('>deux</span>', $html);
    }

    /**
     * The reason the block parser exists: one tag alone on a line is a block, so a component that emits a `<div>` is not put inside a `<p>` it would break out of.
     */
    public function testASingleTagOnALineIsStillABlock(): void
    {
        $html = $this->format('{{button text="x" link="PagePrincipale"}}');

        $this->assertStringNotContainsString('<p>', $html);
    }

    /** A pair written across several lines is the other spelling, and was never affected. */
    public function testAPairSpanningLinesStillWorks(): void
    {
        $html = $this->format("{{panel title=\"T\"}}\ncorps\n{{end elem=\"panel\"}}");

        $this->assertStringContainsString('yw-panel', $html);
        $this->assertStringContainsString('end of panel', $html);
    }

    /** A pair mid-sentence goes through the inline parser, and always did work. */
    public function testAPairInASentenceIsUnchanged(): void
    {
        $html = $this->format('avant {{label}}texte{{end elem="label"}} apres');

        $this->assertStringContainsString('>texte</span>', $html);
        $this->assertStringContainsString('apres', $html);
    }
}
