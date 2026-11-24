<?php

namespace YesWiki\Test\Content;

use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The value list editor is drawn on the server, each value with its own fields and its children inside it. */
class ListFormTest extends YesWikiTestCase
{
    /** @param array<string, mixed> $list */
    private function form(array $list): string
    {
        return $this->getWiki()->services->get(TemplateEngine::class)->render('@core/lists/list_form.twig', ['list' => $list]);
    }

    public function testEachValueIsDrawnWithItsChildrenInside(): void
    {
        $html = $this->form(['title' => 'Produits', 'nodes' => [
            ['id' => 'legumes', 'label' => 'Légumes', 'color' => '#2e7d32', 'icon' => ['source' => 'emoji', 'value' => '🥕'], 'children' => [
                ['id' => 'tomate', 'label' => 'Tomate', 'children' => []],
            ]],
        ]]);

        $this->assertMatchesRegularExpression('/data-list-id value="legumes".*?data-list-nodes>\s*<div class="list-node-container" data-list-node\s*>.*?data-list-id value="tomate"/s', $html);
        $this->assertStringContainsString('value="#2e7d32" data-set="1"', $html);
        $this->assertStringContainsString('🥕', $html);
        $this->assertStringContainsString('value="Produits"', $html);
        $this->assertStringNotContainsString('v-model', $html);
    }

    public function testATranslatorSeesTheSourceAndExtraKeysAreKept(): void
    {
        $html = $this->form(['title' => '', 'nodes' => [
            ['id' => 'rouge', 'label' => '', 'sourceLabel' => 'Rouge', 'note' => 'gardée', 'children' => []],
        ]]);

        $this->assertStringContainsString('placeholder="Rouge"', $html);
        $this->assertSame(1, preg_match('/data-extra="([^"]*)"/', $html, $extra));
        $this->assertSame(['note' => 'gardée'], json_decode(html_entity_decode($extra[1] ?? ''), true), 'a key the editor does not know goes back as it came, the source text does not');
        $this->assertStringContainsString('value="#000000" data-set="0"', $html, 'a value without colour is not given black');
    }
}
