<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A long-text field must render what it was given, quotes included.
 *
 * It used to run `str_replace('""', "''")` over its *formatted* output — final HTML — on the
 * theory that a `""` surviving the formatter came from an action and would be re-read by
 * wakka as a raw-HTML delimiter. Nothing re-parses that string, and `""` in final HTML is
 * almost always an empty attribute or an empty JSON string. Rewriting them is invisible in
 * markup and fatal inside a `<script>`: it turned
 *
 *     var blank = '<option value="">' + …
 *
 * into two adjacent string literals, which killed every script in the block and with it the
 * whole admin-content UI. Page bodies reach this field since ticket 10 made a page a form
 * whose `content` is a textelong, so the blast radius was every page, not just bazar entries.
 */
class TextareaFieldQuotesTest extends YesWikiTestCase
{
    private function textarea(string $syntax): BazarField
    {
        $prepared = $this->getWiki()->services->get(FormManager::class)->prepareData([
            'template' => [[
                'type' => 'textelong',
                'name' => 'bf_probe',
                'label' => 'Probe',
                'syntax' => $syntax,
                'rows' => '5',
                'cols' => '40',
            ]],
        ]);
        $field = reset($prepared);
        $this->assertInstanceOf(BazarField::class, $field);

        return $field;
    }

    /** @param array<string,mixed> $extra */
    private function render(BazarField $field, string $value, array $extra = []): string
    {
        return (string)$field->renderStaticIfPermitted(array_merge([
            'tag' => 'BacASable',
            'bf_probe' => $value,
        ], $extra));
    }

    public function testAnEmptyAttributeSurvivesWikiSyntax(): void
    {
        // `""…""` is the wiki syntax for a raw HTML block
        $html = $this->render($this->textarea('wiki'), '""<input type="hidden" value="">""');

        $this->assertStringContainsString('value=""', $html);
        $this->assertStringNotContainsString("value=''", $html);
    }

    /** The exact shape that broke: an empty attribute inside a script, in single-quoted JS. */
    public function testAnEmptyAttributeInsideAScriptSurvives(): void
    {
        $script = '""<script>var blank = \'<option value="">\';</script>""';
        $html = $this->render($this->textarea('wiki'), $script);

        $this->assertStringContainsString('\'<option value="">\'', $html);
        $this->assertStringNotContainsString("value=''", $html);
    }

    /**
     * HTML syntax is emitted as-is and never formatted, so it had even less reason to be
     * rewritten. An empty JSON string is checked here rather than in wiki syntax because the
     * legacy `""…""` raw-HTML delimiter cannot express content that itself contains `""`.
     */
    public function testHtmlSyntaxIsLeftAloneToo(): void
    {
        $html = $this->render(
            $this->textarea('html'),
            '<input value=""><script>var a = ""; var o = {"k":""};</script>'
        );

        $this->assertStringContainsString('value=""', $html);
        $this->assertStringContainsString('var a = "";', $html);
        $this->assertStringContainsString('{"k":""}', $html);
        $this->assertStringNotContainsString("value=''", $html);
    }
}
