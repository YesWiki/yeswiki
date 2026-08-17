<?php

namespace YesWiki\Test\Actions;

use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for ticket 14 (qrcode absorbed into core): {{qrcode}} renders an <img> pointing at a generated SVG via the new QrCodeService, and its missing-param error path uses the core yw-alert classes (Bootstrap's alert/alert-danger is gone).
 */
class QrcodeActionTest extends YesWikiTestCase
{
    public function testQrcodeTagRendersImgWithGeneratedFile()
    {
        $wiki = $this->getWiki();
        $html = $wiki->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format('{{qrcode text="hello world"}}');

        $this->assertMatchesRegularExpression('/<img src="cache[^"]+\.svg" alt="hello world" class="qrcode-img"/', $html);

        preg_match('/<img src="([^"]+)"/', $html, $matches);
        $this->assertFileExists($matches[1]);
        unlink($matches[1]);
    }

    public function testQrcodeTagWithoutTextRendersCoreAlert()
    {
        $wiki = $this->getWiki();
        $html = $wiki->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format('{{qrcode}}');

        $this->assertStringContainsString('yw-alert', $html);
        $this->assertStringContainsString('yw-alert--danger', $html);
        $this->assertStringNotContainsString('alert-danger"', $html);
    }
}
