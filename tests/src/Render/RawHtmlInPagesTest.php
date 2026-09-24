<?php

namespace YesWiki\Test\Render;

use YesWiki\Kernel\Service\HtmlPurifierService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\ContentAssetScanner;
use YesWiki\Render\Service\LinkRenderer;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Pages migrated from Doryphore carry their own `<style>` and `<script>`, and keep them until rights are hardened. */
class RawHtmlInPagesTest extends YesWikiTestCase
{
    private function renderWithTheShippedDefaults(string $source): string
    {
        $services = $this->getWiki()->services;
        $config = $services->get(RuntimeConfig::class);
        $was = $config['disallowed_html_tags'] ?? null;
        $config['disallowed_html_tags'] = HtmlPurifierService::DISALLOWED_HTML_TAGS;

        try {
            $formatter = new MarkdownFormatterService(
                $services,
                $services->get(ContentAssetScanner::class),
                $services->get(ActionRunner::class),
                $services->get(LinkRenderer::class)
            );

            return $formatter->format($source);
        } finally {
            $config['disallowed_html_tags'] = $was;
        }
    }

    public function testAStyleBlockReachesThePage(): void
    {
        $html = $this->renderWithTheShippedDefaults("<style>.migrated { color: red; }</style>\n\ntexte");

        $this->assertStringContainsString('<style>.migrated { color: red; }</style>', $html);
    }

    public function testAScriptBlockReachesThePage(): void
    {
        $html = $this->renderWithTheShippedDefaults("<script>window.migrated = true;</script>\n\ntexte");

        $this->assertStringContainsString('<script>window.migrated = true;</script>', $html);
    }

    public function testTheOtherTagsAreStillFilteredOut(): void
    {
        $html = $this->renderWithTheShippedDefaults("<textarea>brut</textarea>\n\ntexte");

        $this->assertStringNotContainsString('<textarea>', $html);
    }
}
