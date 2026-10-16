<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Field\CheckboxEntryField;
use YesWiki\Content\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A checkbox field may set its own threshold for the filter, select-all and scrolling. */
class CheckboxFilterLimitTest extends YesWikiTestCase
{
    private function render(string $filterLimit): string
    {
        $definition = ['type' => 'checkboxfiche', 'linked_object' => '5', 'name' => 'bf_pages', 'label' => 'Pages'];
        if ($filterLimit !== '') {
            $definition['filter_limit'] = $filterLimit;
        }
        $prepared = $this->getWiki()->services->get(FormManager::class)->prepareData(['template' => [$definition]]);
        $field = reset($prepared);
        $this->assertInstanceOf(CheckboxEntryField::class, $field);
        $html = (string)$field->renderInputIfPermitted([]);
        $this->assertStringContainsString('list-bazar-entries', $html);

        return $html;
    }

    public function testTheNamedAttributeReachesItsPosition(): void
    {
        $template = $this->getWiki()->services->get(FormManager::class)
            ->parseTemplate('[{"type":"checkboxfiche","linked_object":"5","name":"bf_pages","filter_limit":"12"}]');

        $this->assertSame('12', $template[0][19]);
    }

    public function testAHighLimitShowsEveryOption(): void
    {
        $html = $this->render('500');

        $this->assertStringNotContainsString('filter-entries', $html);
        $this->assertStringNotContainsString('list-bazar-entries--scroll', $html);
    }

    public function testALowLimitAddsTheFilterAndScrolls(): void
    {
        $html = $this->render('1');

        $this->assertStringContainsString('filter-entries', $html);
        $this->assertStringContainsString('selectall', $html);
        $this->assertStringContainsString('list-bazar-entries--scroll', $html);
    }

    public function testZeroNeverFilters(): void
    {
        $html = $this->render('0');

        $this->assertStringNotContainsString('filter-entries', $html);
        $this->assertStringNotContainsString('list-bazar-entries--scroll', $html);
    }
}
