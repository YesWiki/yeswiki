<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Content\Service\QrCodeService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for ticket 14 (qrcode absorbed into core): QrCodeService replaces
 * yeswiki-extension-qrcode's wiki.php global $GLOBALS['qrcode'] singleton, a thin
 * Laravel-facade wrapper (f9webltd/simple-qrcode) -- now a real service depending
 * directly on endroid/qr-code, with none of the facade's transitive illuminate/* packages.
 */
#[CoversMethod(QrCodeService::class, 'generateToFile')]
class QrCodeServiceTest extends YesWikiTestCase
{
    public function testGenerateToFileWritesAValidSvg()
    {
        $service = $this->getWiki()->services->get(QrCodeService::class);
        $path = sys_get_temp_dir() . '/QrCodeServiceTest-' . uniqid() . '.svg';

        try {
            $service->generateToFile('https://example.com/some-page', $path);

            $this->assertFileExists($path);
            $content = file_get_contents($path);
            $this->assertStringContainsString('<svg', $content);
            $this->assertGreaterThan(0, filesize($path));
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
