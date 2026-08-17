<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `{{panel}}` renders three different shapes, and each closes what it opened. */
class PanelAccordionShapeTest extends YesWikiTestCase
{
    private function render(string $wikiSource): string
    {
        return $this->getWiki()->services->get(MarkdownFormatterService::class)->format($wikiSource);
    }

    private function assertBalanced(string $html, string $message): void
    {
        $this->assertSame(substr_count($html, '<div'), substr_count($html, '</div>'), "{$message}: unbalanced <div>");
        $this->assertSame(substr_count($html, '<details'), substr_count($html, '</details>'), "{$message}: unbalanced <details>");
    }

    public function testAPanelInsideAnAccordionWearsOnlyTheAccordionClothes(): void
    {
        $html = $this->render(
            "{{accordion}}\n{{panel title=\"Un\" type=\"collapsible\"}}\ncorps\n{{end elem=\"panel\"}}\n{{end elem=\"accordion\"}}"
        );

        $this->assertStringContainsString('yw-accordion__summary', $html);
        $this->assertStringNotContainsString(
            'yw-panel__heading',
            $html,
            'the grey panel bar must not come with it -- display:block drops the title below the chevron'
        );
        $this->assertBalanced($html, 'accordion item');
    }

    public function testAStandaloneCollapsiblePanelKeepsItsOwnLook(): void
    {
        $html = $this->render("{{panel title=\"Seul\" type=\"collapsible\"}}\ncorps\n{{end elem=\"panel\"}}");

        $this->assertStringContainsString('yw-panel__heading', $html);
        $this->assertStringContainsString('<details', $html);
        $this->assertBalanced($html, 'standalone collapsible panel');
    }

    public function testAPlainPanelHasNoDisclosureAtAll(): void
    {
        $html = $this->render("{{panel title=\"Simple\"}}\ncorps\n{{end elem=\"panel\"}}");

        $this->assertStringNotContainsString('<details', $html);
        $this->assertStringContainsString('yw-panel__heading', $html);
        $this->assertBalanced($html, 'plain panel');
    }

    /** Mixed shapes in one accordion: each end() has to close its own opener, not the last one. */
    public function testMixedShapesStayBalanced(): void
    {
        $html = $this->render(
            "{{accordion}}\n{{panel title=\"A\"}}\na\n{{end elem=\"panel\"}}\n"
            . "{{panel title=\"B\" type=\"collapsed\"}}\nb\n{{end elem=\"panel\"}}\n{{end elem=\"accordion\"}}"
        );

        $this->assertBalanced($html, 'mixed accordion');
    }
}
