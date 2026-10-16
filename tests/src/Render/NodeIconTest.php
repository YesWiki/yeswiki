<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A node's `{source, value}` icon draws as a sprite symbol, an emoji or an image of the wiki's files. */
class NodeIconTest extends YesWikiTestCase
{
    private function engine(): TemplateEngine
    {
        return $this->getWiki()->services->get(TemplateEngine::class);
    }

    public function testAnUploadedFileIsShownThroughTheDownloadRoute(): void
    {
        $html = (string)$this->engine()->renderNodeIcon('file', 'mon-logo');
        $this->assertStringContainsString('class="yw-icon-image"', $html);
        $this->assertMatchesRegularExpression('#src="[^"]*api/files/mon-logo/download[^"]*width=64#', $html);
    }

    public function testAFileUrlIsKeptAsWritten(): void
    {
        $html = (string)$this->engine()->renderNodeIcon('file', 'https://example.org/a.png');
        $this->assertStringContainsString('src="https://example.org/a.png"', $html);
    }

    public function testAPathOutsideThePublicTiersDrawsNothing(): void
    {
        $this->assertNull($this->engine()->renderNodeIcon('file', 'private/files/a.png'));
    }

    public function testAnEmojiAndASymbol(): void
    {
        $this->assertStringContainsString('class="yw-icon-emoji"', (string)$this->engine()->renderNodeIcon('emoji', '🌿'));
        $this->assertStringContainsString('#leaf"', (string)$this->engine()->renderNodeIcon('sprite', 'leaf'));
    }
}
