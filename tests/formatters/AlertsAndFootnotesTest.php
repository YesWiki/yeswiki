<?php

namespace YesWiki\Test\Formatters;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Two additions to the page syntax, asked for in HedgeDoc's spelling: `:::info` callouts and
 * `[^1]` footnotes.
 *
 * Footnotes are CommonMark's own extension, so what is worth asserting is only that it is
 * switched on and produces the ids the backlinks point at. The alerts are ours
 * (`AlertExtension`), and every case below is a decision made in that parser rather than a
 * demonstration that markdown works.
 */
class AlertsAndFootnotesTest extends YesWikiTestCase
{
    private function format(string $markdown): string
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;

        return $wiki->services->get(MarkdownFormatterService::class)->format($markdown);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function alertTypeProvider(): array
    {
        return [
            'info' => ['info', 'yw-alert--info'],
            'success' => ['success', 'yw-alert--success'],
            'warning' => ['warning', 'yw-alert--warning'],
            'danger' => ['danger', 'yw-alert--danger'],
        ];
    }

    #[DataProvider('alertTypeProvider')]
    public function testEachTypeRendersTheWikisOwnAlertBox(string $type, string $class): void
    {
        $html = $this->format(":::{$type}\nCeci est un message\n:::");

        $this->assertStringContainsString('yw-alert', $html);
        $this->assertStringContainsString($class, $html);
        $this->assertStringContainsString('Ceci est un message', $html);
        $this->assertStringNotContainsString(':::', $html, 'the fences are markup, not content');
    }

    /**
     * A container, which is the whole reason for having this beside `{{panel}}`: you can put
     * one around prose that is already written, and what is inside stays markdown.
     */
    public function testWhatIsInsideIsStillMarkdown(): void
    {
        $html = $this->format(":::success\n- un\n- deux\n\n**gras**\n:::");

        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>un</li>', $html);
        $this->assertStringContainsString('<strong>gras</strong>', $html);
    }

    /**
     * The two whose meaning is carried by colour alone get told to a screen reader as well.
     */
    public function testTheAlarmingOnesAnnounceThemselves(): void
    {
        $this->assertStringContainsString('role="alert"', $this->format(":::danger\nx\n:::"));
        $this->assertStringContainsString('role="alert"', $this->format(":::warning\nx\n:::"));
        $this->assertStringNotContainsString('role="alert"', $this->format(":::info\nx\n:::"));
    }

    /**
     * An unknown type is left as the text it is.
     *
     * The alternative -- accept anything and emit `yw-alert--note` -- renders as an unstyled
     * block, which reads as a broken page rather than as a word nobody defined.
     */
    public function testAnUnknownTypeIsNotAnAlert(): void
    {
        $html = $this->format(":::note\npas une alerte\n:::");

        $this->assertStringNotContainsString('yw-alert', $html);
        $this->assertStringContainsString(':::note', $html);
    }

    /** `:::` mid-sentence is punctuation, not a fence. */
    public function testColonsInsideALineAreText(): void
    {
        $html = $this->format('du texte ::: encore du texte');

        $this->assertStringNotContainsString('yw-alert', $html);
    }

    /**
     * An alert nobody closed still renders everything after it.
     *
     * Requiring the closing fence would mean a missing `:::` silently swallows the rest of
     * the page -- the failure mode this parser must not have.
     */
    public function testAnUnclosedAlertStillRendersItsContent(): void
    {
        $html = $this->format(":::danger\nquand même affiché");

        $this->assertStringContainsString('yw-alert--danger', $html);
        $this->assertStringContainsString('quand même affiché', $html);
    }

    public function testTwoAlertsInOnePageDoNotRunTogether(): void
    {
        $html = $this->format(":::info\npremier\n:::\n\ntexte entre les deux\n\n:::warning\nsecond\n:::");

        $this->assertSame(2, substr_count($html, 'yw-alert yw-alert--'), 'two boxes, not one');
        $this->assertStringContainsString('texte entre les deux', $html);
        $this->assertStringNotContainsString(
            'yw-alert--warning',
            substr($html, 0, (int)strpos($html, 'texte entre les deux')),
            'the first alert closed where it was told to'
        );
    }

    // ------------------------------------------------------------------ footnotes

    public function testAFootnoteBecomesAReferenceAndANote(): void
    {
        $html = $this->format("Une affirmation[^1].\n\n[^1]: La preuve.");

        // the reference, and the note it points at -- ids matched, because a footnote whose
        // link goes nowhere is worse than no footnote
        $this->assertMatchesRegularExpression('/<sup[^>]*id="fnref:1"/', $html);
        $this->assertStringContainsString('href="#fn:1"', $html);
        $this->assertMatchesRegularExpression('/id="fn:1"/', $html);
        $this->assertStringContainsString('La preuve.', $html);
        // ...and back again
        $this->assertStringContainsString('href="#fnref:1"', $html);
    }

    public function testAFootnoteDefinitionIsNotLeftInTheProse(): void
    {
        $html = $this->format("Texte[^note].\n\n[^note]: Le détail.");

        $this->assertStringNotContainsString('[^note]', $html);
    }
}
