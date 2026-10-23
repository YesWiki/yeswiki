<?php

namespace YesWiki\Test\Bazar\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Bazar\Service\BazarListService;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A bazar list may name another wiki to read entries from. That address is somebody
 * else's choice, so it must never send the server at its own network.
 */
class BazarListExternalUrlTest extends YesWikiTestCase
{
    private $bazarListService;
    private $formManager;

    protected function setUp(): void
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $this->bazarListService = $wiki->services->get(BazarListService::class);
        $this->formManager = $wiki->services->get(FormManager::class);
    }

    #[DataProvider('refusedUrlProvider')]
    public function testAnAddressBehindTheWikiIsRefused(string $url)
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid URL');

        $this->bazarListService->getForms(['idtypeannonce' => $url . '|1']);
    }

    public static function refusedUrlProvider(): array
    {
        return [
            'loopback' => ['http://127.0.0.1:9999'],
            'loopback by name' => ['http://localhost:9999'],
            'loopback in ipv6' => ['http://[::1]:9999'],
            'a private range' => ['http://192.168.32.1:9999'],
            'another private range' => ['http://10.0.0.1'],
            'link-local metadata' => ['http://169.254.169.254'],
            'a decimal address' => ['http://2130706433:9999'],
            'a file path' => ['file:///etc/passwd'],
            'a gopher address' => ['gopher://127.0.0.1:9999'],
        ];
    }

    public function testALocalFormIsStillRead()
    {
        $formId = $this->formManager->create([
            'bn_label_nature' => 'External url test form',
            'bn_template' => 'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
        ]);

        try {
            $forms = $this->bazarListService->getForms(['idtypeannonce' => $formId]);

            $this->assertSame('External url test form', $forms[$formId]['bn_label_nature'] ?? null);
        } finally {
            $this->formManager->delete($formId);
        }
    }
}
