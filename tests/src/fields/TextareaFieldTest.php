<?php

namespace YesWiki\Test\Core\Field;

require_once 'tests/YesWikiTestCase.php';

use YesWiki\Content\Field\TextareaField;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Test\Core\YesWikiTestCase;

/**
 * Regression test for ticket 16 (remove-bootstrap-jquery): TextareaField's 'html' syntax mode used to load summernote (jQuery-hard-dependent); it now loads Vditor instead.
 */
class TextareaFieldTest extends YesWikiTestCase
{
    private function buildTextareaField(string $syntax): TextareaField
    {
        $wiki = $this->getWiki();

        $values = [
            'textelong',
            'bf_description',
            'Description field under test',
            '',
            4,
            '',
            '',
            $syntax,
            '0',
            '0',
            '',
            '',
            '',
            '',
            '',
            '',
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
        LanguageService::getInstance()->serveIn('fr');

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
        LanguageService::getInstance()->serveIn('eu');

        $field = $this->buildTextareaField('html');
        $output = $this->renderInput($field, ['tag' => 'TextareaFieldTestEntry']);

        $this->assertStringContainsString('data-vditor-lang="en_US"', $output);
    }
}
