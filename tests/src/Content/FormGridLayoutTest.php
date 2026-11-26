<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Field\ColumnsBalancer;
use YesWiki\Content\Field\ColumnsField;
use YesWiki\Content\Service\ConditionsChecker;
use YesWiki\Content\Service\FormManager;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Field widths, form widths, card and pill choices, columns blocks and the opt-in entry view grid. */
class FormGridLayoutTest extends YesWikiTestCase
{
    private const FORM_ID = '999961';

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        $formManager = $services->get(FormManager::class);
        if ($formManager->getOne(self::FORM_ID)) {
            $formManager->delete(self::FORM_ID);
        }
        parent::tearDown();
    }

    public function testFieldWidthAndNewRowRenderInInputAndStaticTemplates(): void
    {
        $services = $this->getWiki()->services;
        $formManager = $services->get(FormManager::class);

        $prepared = $formManager->prepareData(['template' => [
            ['type' => 'texte', 'name' => 'bf_half', 'label' => 'Moitié', 'width' => '6', 'new_row' => '1'],
            ['type' => 'texte', 'name' => 'bf_invalid', 'label' => 'Invalide', 'width' => '5'],
        ]]);

        $half = $prepared[0];
        $invalid = $prepared[1];
        $this->assertInstanceOf(BazarField::class, $half);
        $this->assertInstanceOf(BazarField::class, $invalid);

        $this->assertSame(6, $half->getWidth());
        $this->assertTrue($half->isNewRow());
        $this->assertSame(12, $invalid->getWidth());
        $this->assertFalse($invalid->isNewRow());

        $inputHtml = $half->renderInputIfPermitted(['bf_half' => 'abc']);
        $this->assertStringContainsString('yw-col-6', $inputHtml);
        $this->assertStringContainsString('yw-new-row', $inputHtml);

        $staticHtml = $half->renderStaticIfPermitted(['bf_half' => 'abc']);
        $this->assertStringContainsString('yw-col-6', $staticHtml);
        $this->assertStringContainsString('yw-new-row', $staticHtml);
    }

    public function testLabelHtmlWrapsOnlyBalancedBlocksWithGridSettings(): void
    {
        $services = $this->getWiki()->services;
        $formManager = $services->get(FormManager::class);

        $prepared = $formManager->prepareData(['template' => [
            ['type' => 'labelhtml', 'form_text' => '<p>Bloc colonne</p>', 'width' => '6', 'new_row' => '1'],
            ['type' => 'labelhtml', 'form_text' => '</div><!-- Fin condition -->', 'width' => '6'],
            ['type' => 'labelhtml', 'form_text' => '<h4>Titre</h4>'],
            ['type' => 'labelhtml', 'form_text' => '<div class="well">', 'width' => '6'],
        ]]);

        $colBlock = $prepared[0]->renderInputIfPermitted(null);
        $this->assertStringContainsString('<div class="yw-form-labelhtml yw-col-6 yw-new-row"><p>Bloc colonne</p></div>', $colBlock);

        $closingTag = $prepared[1]->renderInputIfPermitted(null);
        $this->assertSame('</div><!-- Fin condition -->', $closingTag);

        $fullBlock = $prepared[2]->renderInputIfPermitted(null);
        $this->assertSame('<h4>Titre</h4>', $fullBlock);

        $openingWrapper = $prepared[3]->renderInputIfPermitted(null);
        $this->assertSame('<div class="well">', $openingWrapper);
    }

    public function testRadioAndCheckboxSupportCardsAndPillsVariants(): void
    {
        $services = $this->getWiki()->services;
        if ($services->get(AuthenticationService::class)->connectFirstAdmin() === null) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
        try {
            $formManager = $services->get(FormManager::class);
            $formManager->create([
                'id' => self::FORM_ID,
                'label' => 'Grid variants test form',
                'template' => json_encode([
                    ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre', 'width' => '6'],
                ]),
                'entry_form_width' => 'wide',
                'entry_template_grid' => true,
            ]);

            $saved = $formManager->getOne(self::FORM_ID);
            $this->assertNotNull($saved);
            $this->assertSame('wide', $saved['entry_form_width'] ?? null);
            $this->assertTrue((bool)($saved['entry_template_grid'] ?? false));

            $templates = $services->get(TemplateEngine::class);
            $formHtml = $templates->render('@core/entries/form.twig', [
                'form' => $saved,
                'formAction' => '/test',
                'renderedInputs' => [$saved['prepared'][0]->renderInputIfPermitted(null)],
                'passwordForEditing' => '',
                'incomingUrl' => '',
                'cancelUrl' => '',
                'error' => '',
            ]);
            $this->assertStringContainsString('yw-entry-width--wide', $formHtml);
            $this->assertStringContainsString('yw-col-6', $formHtml);
            $this->assertStringNotContainsString('col-sm-9', $formHtml);

            $viewHtml = $templates->render('@core/entries/view.twig', [
                'form' => $saved,
                'renderedEntry' => $saved['prepared'][0]->renderStaticIfPermitted(['bf_titre' => 'Hello']),
                'showFooter' => false,
                'canShow' => true,
            ]);
            $this->assertStringContainsString('yw-entry-grid', $viewHtml);
            $this->assertStringContainsString('yw-entry-width--wide', $viewHtml);

            $defaultViewHtml = $templates->render('@core/entries/view.twig', [
                'form' => ['id' => self::FORM_ID],
                'renderedEntry' => '',
                'showFooter' => false,
                'canShow' => true,
            ]);
            $this->assertStringNotContainsString('yw-entry-grid', $defaultViewHtml);

            $disabledViewHtml = $templates->render('@core/entries/view.twig', [
                'form' => ['id' => self::FORM_ID, 'entry_template_grid' => '0'],
                'renderedEntry' => '',
                'showFooter' => false,
                'canShow' => true,
            ]);
            $this->assertStringNotContainsString('yw-entry-grid', $disabledViewHtml);

            $radioHtml = $templates->render('@core/inputs/radio.twig', [
                'field' => $saved['prepared'][0],
                'options' => ['a' => 'Alpha', 'b' => 'Beta'],
                'value' => 'a',
                'displayFilterLimit' => false,
                'displayVariant' => 'cards',
            ]);
            $this->assertStringContainsString('yw-choice--cards', $radioHtml);

            $checkboxHtml = $templates->render('@core/inputs/checkbox.twig', [
                'field' => $saved['prepared'][0],
                'options' => ['a' => 'Alpha', 'b' => 'Beta'],
                'values' => ['a'],
                'displaySelectAllLimit' => false,
                'displayFilterLimit' => false,
                'oldValue' => '',
                'displayVariant' => 'pills',
            ]);
            $this->assertStringContainsString('yw-choice--pills', $checkboxHtml);
        } finally {
            $services->get(AuthenticationService::class)->logout();
        }
    }

    public function testIndependentColumnsRenderAndBalanceSafely(): void
    {
        $services = $this->getWiki()->services;
        $formManager = $services->get(FormManager::class);

        $prepared = $formManager->prepareData(['template' => [
            ['type' => 'columns', 'layout' => '4-8'],
            ['type' => 'texte', 'name' => 'bf_a', 'label' => 'A'],
            ['type' => 'texte', 'name' => 'bf_b', 'label' => 'B'],
            ['type' => 'columnchange'],
            ['type' => 'textelong', 'name' => 'bf_bio', 'label' => 'Bio'],
            ['type' => 'columnsend'],
        ]]);

        $this->assertInstanceOf(ColumnsField::class, $prepared[0]);
        $this->assertSame('4-8', $prepared[0]->getLayout());
        $this->assertSame(ColumnsBalancer::open('4-8'), $prepared[0]->renderInputIfPermitted(null));
        $this->assertSame(ColumnsBalancer::BREAK, $prepared[3]->renderInputIfPermitted(null));
        $this->assertSame(ColumnsBalancer::CLOSE, $prepared[5]->renderInputIfPermitted(null));

        $states = $services->get(ConditionsChecker::class)->states(['prepared' => $prepared], []);
        $this->assertTrue($states[4]['visible']);

        $joined = $this->renderedInputs($formManager->prepareData(['template' => [
            ['type' => 'columnchange'],
            ['type' => 'columns', 'layout' => '6-6'],
            ['type' => 'texte', 'name' => 'bf_a', 'label' => 'A'],
        ]]));
        $this->assertStringStartsWith('<div class="yw-form-columns yw-form-columns--6-6">', $joined);
        $this->assertStringEndsWith(ColumnsBalancer::CLOSE, $joined);
        $this->assertWellFormed($joined);
    }

    public function testAConditionCrossingAColumnBreakKeepsTheMarkupWellFormed(): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);

        $html = $this->renderedInputs($formManager->prepareData(['template' => [
            ['type' => 'columns', 'layout' => '6-6'],
            ['type' => 'conditionschecking', 'condition' => 'bf_a==oui'],
            ['type' => 'texte', 'name' => 'bf_a', 'label' => 'A'],
            ['type' => 'columnchange'],
            ['type' => 'texte', 'name' => 'bf_b', 'label' => 'B'],
            ['type' => 'labelhtml', 'form_text' => '</div><!-- Fin condition -->'],
            ['type' => 'columnsend'],
            ['type' => 'texte', 'name' => 'bf_c', 'label' => 'C'],
        ]]));

        $this->assertWellFormed($html);
        $this->assertSame(1, $this->countNodes($html, '//input[@name="bf_b"]/ancestor::div[@data-conditionschecking]'));
        $this->assertSame(0, $this->countNodes($html, '//input[@name="bf_c"]/ancestor::div[contains(@class, "yw-form-columns")]'));
    }

    public function testAConditionOpenedBeforeTheColumnsAndClosedInsideThemClosesTheColumnsFirst(): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);

        $html = $this->renderedInputs($formManager->prepareData(['template' => [
            ['type' => 'conditionschecking', 'condition' => 'bf_a==oui'],
            ['type' => 'columns', 'layout' => '6-6'],
            ['type' => 'texte', 'name' => 'bf_a', 'label' => 'A'],
            ['type' => 'labelhtml', 'form_text' => '</div><!-- Fin condition -->'],
            ['type' => 'columnchange'],
            ['type' => 'texte', 'name' => 'bf_b', 'label' => 'B'],
            ['type' => 'columnsend'],
        ]]));

        $this->assertWellFormed($html);
        $this->assertSame(0, $this->countNodes($html, '//input[@name="bf_b"]/ancestor::div[@data-conditionschecking]'));
    }

    /** @param array<int, mixed> $prepared */
    private function renderedInputs(array $prepared): string
    {
        $controller = $this->getWiki()->services->get(EntryController::class);
        $method = new \ReflectionMethod($controller, 'getRenderedInputs');

        return implode('', $method->invoke($controller, ['prepared' => $prepared], null));
    }

    private function assertWellFormed(string $html): void
    {
        $depth = 0;
        preg_match_all('#<div\b|</div\b#i', $html, $matches);
        foreach ($matches[0] as $tag) {
            $depth += $tag[1] === '/' ? -1 : 1;
            $this->assertGreaterThanOrEqual(0, $depth, $html);
        }
        $this->assertSame(0, $depth, $html);
    }

    private function countNodes(string $html, string $query): int
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>');
        libxml_clear_errors();
        $nodes = (new \DOMXPath($document))->query($query);

        return $nodes === false ? 0 : $nodes->length;
    }
}
