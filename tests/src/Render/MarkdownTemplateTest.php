<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Render\Twig\MarkdownTemplate;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A markdown template converts what its author wrote, and nothing of what it prints. */
class MarkdownTemplateTest extends YesWikiTestCase
{
    private const DIRECTORY = 'custom/templates/core';

    /** @var list<string> */
    private array $written = [];

    protected function tearDown(): void
    {
        array_map('unlink', $this->written);
        parent::tearDown();
    }

    /** @param array<string, mixed> $values */
    private function render(string $body, array $values = []): string
    {
        if (!is_dir(self::DIRECTORY)) {
            mkdir(self::DIRECTORY, 0777, true);
        }
        $name = 'markdown-template-probe-' . md5($body) . '.twig';
        $this->written[] = self::DIRECTORY . '/' . $name;
        file_put_contents(self::DIRECTORY . '/' . $name, MarkdownTemplate::mark($body));

        return $this->getWiki()->services->get(TemplateEngine::class)->render('@core/' . $name, $values);
    }

    public function testMarkdownIsConverted(): void
    {
        $html = $this->render('**fort** {{ value }}', ['value' => 'ok']);

        $this->assertStringContainsString('<strong>fort</strong> ok', $html);
    }

    public function testAPrintedValueIsNotReadAsMarkdownNorAsAnAction(): void
    {
        $html = $this->render('{{ html.bio|raw }}', ['html' => ['bio' => '**x** {{button link="Evil"}}']]);

        $this->assertStringContainsString('**x** {{button link="Evil"}}', $html);
        $this->assertStringNotContainsString('<strong>', $html);
    }

    public function testAnExpressionSurvivesATableCell(): void
    {
        $html = $this->render("| A | B |\n|---|---|\n| {{ a|raw }} | {{ b }} |", ['a' => '<i>1</i>', 'b' => '2']);

        $this->assertMatchesRegularExpression('~<td>\s*<i>1</i>\s*</td>\s*<td>\s*2\s*</td>~', $html);
    }

    public function testAHeadingGetsNoIdMadeOfAValue(): void
    {
        $html = $this->render('## {{ title|raw }}', ['title' => '" onclick="evil']);

        $this->assertStringNotContainsString('id="', $html);
    }

    public function testControlFlowLeavesNoEmptyParagraph(): void
    {
        $html = $this->render("{% if show %}\n\n{{ html.a|raw }}\n\n{% endif %}\n\n{{ text }}", ['show' => true, 'html' => ['a' => '<p>bloc</p>'], 'text' => 'mot']);

        $this->assertStringNotContainsString('<p></p>', $html);
        $this->assertStringNotContainsString('<p><p>', $html);
        $this->assertStringContainsString('<p>mot</p>', $html);
    }

    public function testAWikiActionStillRuns(): void
    {
        $html = $this->render('{{button text="Ok" link="PagePrincipale"}}');

        $this->assertMatchesRegularExpression('~<a[^>]*yw-btn[^>]*>.*Ok~s', $html);
    }
}
