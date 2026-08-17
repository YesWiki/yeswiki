<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A long-text field must render what it was given, quotes included. */
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

    /**
     * @param array<string,mixed> $extra
     */
    private function render(BazarField $field, string $value, array $extra = []): string
    {
        return (string)$field->renderStaticIfPermitted(array_merge([
            'tag' => 'BacASable',
            'bf_probe' => $value,
        ], $extra));
    }

    public function testAnEmptyAttributeSurvivesWikiSyntax(): void
    {
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

    /** HTML syntax is emitted as-is and never formatted, so it had even less reason to be rewritten. */
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
