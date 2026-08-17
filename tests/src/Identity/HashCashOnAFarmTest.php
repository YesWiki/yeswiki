<?php

namespace YesWiki\Test\Identity;

use YesWiki\Identity\Service\HashCashService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Where the hashcash secret lives, and what happens when it cannot be written. */
class HashCashOnAFarmTest extends YesWikiTestCase
{
    private function secretFile(HashCashService $service): string
    {
        $method = (new \ReflectionClass($service))->getMethod('secretFile');

        return (string)$method->invoke($service);
    }

    public function testTheSecretBelongsToTheWikiNotToTheSourceTree(): void
    {
        $wiki = $this->getWiki();
        $path = $this->secretFile($wiki->services->get(HashCashService::class));

        $this->assertStringStartsWith(\YESWIKI_INSTANCE_DIR . '/cache/', $path);

        if (realpath(\YESWIKI_SOURCE_DIR) !== realpath(\YESWIKI_INSTANCE_DIR)) {
            $this->assertStringStartsNotWith(\YESWIKI_SOURCE_DIR, $path);
        }
    }

    /** A key that cannot be written is a wiki without a puzzle, not a wiki with a fatal. */
    public function testAnUnwritableKeyDisablesTheCheckInsteadOfBreakingThePage(): void
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $service = $wiki->services->get(HashCashService::class);
        $path = $this->secretFile($service);

        $saved = is_file($path) ? (string)file_get_contents($path) : null;
        if ($saved !== null) {
            unlink($path);
        }

        if (!mkdir($path, 0755, true)) {
            $this->markTestSkipped('could not make the key unwritable');
        }

        $config = $wiki->services->get(RuntimeConfig::class);
        $before = $config['use_hashcash'] ?? null;
        $config['use_hashcash'] = true;

        try {
            $this->assertSame('', $service->getJavascriptCode(), 'no puzzle is posed');
            $this->assertTrue($service->checkHashcash(), '...so there is nothing to fail on');
        } finally {
            $config['use_hashcash'] = $before;
            rmdir($path);
            if ($saved !== null) {
                file_put_contents($path, $saved);
            }
        }
    }

    /** ...and with a writable one, the puzzle is posed and the answer is required. */
    public function testTheCheckStillWorksWhenTheKeyCanBeWritten(): void
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $service = $wiki->services->get(HashCashService::class);

        $config = $wiki->services->get(RuntimeConfig::class);
        $before = $config['use_hashcash'] ?? null;
        $config['use_hashcash'] = true;

        try {
            $this->assertStringContainsString('hashcash', $service->getJavascriptCode());
            $this->assertFileExists($this->secretFile($service));
            $this->assertFalse($service->checkHashcash(), 'a submission with no answer is refused');
        } finally {
            $config['use_hashcash'] = $before;
        }
    }
}
