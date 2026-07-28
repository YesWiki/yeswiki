<?php

namespace YesWiki\Test\Core\Field;

require_once 'tests/YesWikiTestCase.php';

use YesWiki\Content\Field\TextareaField;
use YesWiki\Test\Core\YesWikiTestCase;

/**
 * Regression test for ticket 16 (remove-bootstrap-jquery): TextareaField's 'html'
 * syntax mode used to load summernote (jQuery-hard-dependent); it now loads Vditor
 * instead. Confirms renderInput() doesn't fatal and emits the new vditor-html marker
 * class/assets for 'html' syntax, and stays free of them for other syntax modes.
 */
class TextareaFieldTest extends YesWikiTestCase
{
    private function buildTextareaField(string $syntax): TextareaField
    {
        $wiki = $this->getWiki();

        // BazarField's legacy positional array (FIELD_TYPE..FIELD_PLACEHOLDER)
        $values = [
            'textelong', // FIELD_TYPE (0)
            'bf_description', // FIELD_NAME (1)
            'Description field under test', // FIELD_LABEL (2)
            '', // FIELD_SIZE (3)
            4, // FIELD_NUM_ROWS (4)
            '', // 5
            '', // FIELD_MAX_CHARS (6)
            $syntax, // FIELD_SYNTAX (7)
            '0', // FIELD_REQUIRED (8)
            '0', // FIELD_SEARCHABLE (9)
            '', // FIELD_HINT (10)
            '', // FIELD_READ_ACCESS (11)
            '', // FIELD_WRITE_ACCESS (12)
            '', // 13
            '', // 14
            '', // FIELD_PLACEHOLDER (15)
        ];

        return new TextareaField($values, $wiki->services);
    }

    private function renderInput(TextareaField $field, array $entry): string
    {
        $reflection = new \ReflectionMethod($field, 'renderInput');

        return $reflection->invoke($field, $entry);
    }

    public function testHtmlSyntaxRendersVditorMarkerAndAssetsNotSummernote()
    {
        $GLOBALS['prefered_language'] = 'fr';

        $field = $this->buildTextareaField('html');
        $output = $this->renderInput($field, ['tag' => 'TextareaFieldTestEntry']);

        $this->assertStringContainsString('vditor-html', $output);
        $this->assertStringContainsString('data-vditor-lang="fr_FR"', $output);
        $this->assertStringNotContainsString('summernote', $output);
    }

    public function testPlainSyntaxDoesNotLoadVditor()
    {
        $field = $this->buildTextareaField('nohtml');
        $output = $this->renderInput($field, ['tag' => 'TextareaFieldTestEntry']);

        $this->assertStringNotContainsString('vditor-html', $output);
        $this->assertStringNotContainsString('data-vditor-lang', $output);
    }

    public function testUnsupportedLanguageFallsBackToEnglishVditorLocale()
    {
        $GLOBALS['prefered_language'] = 'eu';

        $field = $this->buildTextareaField('html');
        $output = $this->renderInput($field, ['tag' => 'TextareaFieldTestEntry']);

        $this->assertStringContainsString('data-vditor-lang="en_US"', $output);
    }
}
