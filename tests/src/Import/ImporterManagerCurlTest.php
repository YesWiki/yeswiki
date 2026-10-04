<?php

namespace YesWiki\Test\Import;

use YesWiki\Import\Service\ImporterManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Logging in to a source wiki and reading its form only reach addresses the SSRF guard allows, this wiki's own origin included. */
class ImporterManagerCurlTest extends YesWikiTestCase
{
    private function importerManager(): ImporterManager
    {
        return $this->getWiki()->services->get(ImporterManager::class);
    }

    /** @return array{0: string|false, 1: string} what curl() returned, and what it printed */
    private function curl(string $url, bool $isPost, bool $showHeader): array
    {
        ob_start();
        $response = $this->importerManager()->curl($url, ['Cookie: session=1'], $isPost, $isPost ? 'username=a&password=b' : null, true, $showHeader, 5);

        return [$response, (string)ob_get_clean()];
    }

    public function testReadingAFormOnAPrivateAddressIsRefused(): void
    {
        [$response, $output] = $this->curl('http://169.254.169.254/?api/forms/1', false, false);

        $this->assertFalse($response);
        $this->assertStringContainsString('private or reserved', $output);
    }

    public function testLoggingInOnAPrivateAddressIsRefused(): void
    {
        [$response, $output] = $this->curl('http://10.0.0.1/?api/login', true, true);

        $this->assertFalse($response);
        $this->assertStringContainsString('private or reserved', $output);
    }

    public function testASourceOnThisWikisOwnOriginIsStillReached(): void
    {
        $baseUrl = (string)$this->getWiki()->config['base_url'];
        $root = rtrim((string)preg_replace('/\?.*$/', '', $baseUrl), '/');
        $probe = @fsockopen((string)parse_url($root, PHP_URL_HOST), (int)(parse_url($root, PHP_URL_PORT) ?: (str_starts_with($root, 'https') ? 443 : 80)), $errno, $errstr, 2);
        if ($probe === false) {
            $this->markTestSkipped("this wiki is not served at {$root}");
        }
        fclose($probe);

        [, $loginOutput] = $this->curl($root . '/?api/login', true, true);
        [, $readOutput] = $this->curl($root . '/?api/forms/1', false, false);

        $this->assertStringNotContainsString('private or reserved', $loginOutput);
        $this->assertStringNotContainsString('private or reserved', $readOutput);
    }
}
