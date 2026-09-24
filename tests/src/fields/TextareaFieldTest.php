<?php

namespace YesWiki\Test\Core\Field;

require_once 'tests/YesWikiTestCase.php';

use YesWiki\Content\Field\TextareaField;
use YesWiki\Kernel\Service\HtmlPurifierService;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\RuntimeConfig;
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

    /**
     * @param array<string, mixed> $entry
     */
    private function renderInput(TextareaField $field, array $entry): string
    {
        $reflection = new \ReflectionMethod($field, 'renderInput');

        return $reflection->invoke($field, $entry);
    }

    public function testHtmlSyntaxRendersVditorMarkerAndAssetsNotSummernote(): void
    {
        LanguageService::getInstance()->serveIn('fr');

        $field = $this->buildTextareaField('html');
        $output = $this->renderInput($field, ['tag' => 'TextareaFieldTestEntry']);

        $this->assertStringContainsString('vditor-html', $output);
        $this->assertStringContainsString('data-vditor-lang="fr_FR"', $output);
        $this->assertStringNotContainsString('summernote', $output);
    }

    public function testPlainSyntaxDoesNotLoadVditor(): void
    {
        $field = $this->buildTextareaField('nohtml');
        $output = $this->renderInput($field, ['tag' => 'TextareaFieldTestEntry']);

        $this->assertStringNotContainsString('vditor-html', $output);
        $this->assertStringNotContainsString('data-vditor-lang', $output);
    }

    public function testUnsupportedLanguageFallsBackToEnglishVditorLocale(): void
    {
        $served = LanguageService::getInstance()->preferredLanguage();
        LanguageService::getInstance()->serveIn('eu');

        try {
            $field = $this->buildTextareaField('html');
            $output = $this->renderInput($field, ['tag' => 'TextareaFieldTestEntry']);

            $this->assertStringContainsString('data-vditor-lang="en_US"', $output);
        } finally {
            LanguageService::getInstance()->serveIn($served);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function syntaxesThatRenderMarkup(): array
    {
        return ['wiki' => [TextareaField::SYNTAX_WIKI], 'html' => [TextareaField::SYNTAX_HTML]];
    }

    /** Entries migrated from Doryphore keep their `<style>` and `<script>`, like pages do, and the rest is still purified. */
    #[\PHPUnit\Framework\Attributes\DataProvider('syntaxesThatRenderMarkup')]
    public function testAnEntryKeepsItsStyleAndScriptBlocks(string $syntax): void
    {
        $config = $this->getWiki()->services->get(RuntimeConfig::class);
        $was = $config['disallowed_html_tags'] ?? null;
        $config['disallowed_html_tags'] = HtmlPurifierService::DISALLOWED_HTML_TAGS;

        $field = $this->buildTextareaField($syntax);
        $value = "<style>.migrated { color: red; }</style>\n<script>window.migrated = 1 < 2;</script>\n<p onclick=\"steal()\">texte</p>";

        try {
            $saved = $field->formatValuesBeforeSave(['bf_description' => $value])['bf_description'];
        } finally {
            $config['disallowed_html_tags'] = $was;
        }

        $this->assertStringContainsString('<style>.migrated { color: red; }</style>', $saved);
        $this->assertStringContainsString('<script>window.migrated = 1 < 2;</script>', $saved);
        $this->assertStringNotContainsString('onclick', $saved, 'the purifier still runs on everything else');
    }
}
