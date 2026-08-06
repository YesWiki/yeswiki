<?php

namespace YesWiki\Test\Formatters;

use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A line holding more than one `{{action}}` tag.
 *
 * `ActionBlockStartParser` recognises a line that is nothing *but* an action tag, so it
 * becomes its own block rather than being wrapped in a `<p>`. Its pattern was greedy:
 * `/^\{\{(.*)\}\}[ \t]*$/`, which on a line holding a pair matched from the first `{{` to
 * the **last** `}}` and read the lot as one tag. `{{label}}texte{{end elem="label"}}` was
 * run as the action `label`, and the text between the tags and the closing tag were
 * swallowed — an unclosed `<span>` with nothing in it.
 *
 * Which is how anyone writes a row of labels. Six of them left six nested unclosed spans
 * with the rest of the document inside; found by looking at the Personnalisation screen's
 * component gallery, not by any test, because every unit test asserted on the *opening*
 * tag being present.
 */
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
     * The reason the block parser exists: one tag alone on a line is a block, so a
     * component that emits a `<div>` is not put inside a `<p>` it would break out of.
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
