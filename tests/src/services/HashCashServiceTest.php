<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Identity\Service\HashCashService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 15 (security-core-split): HashCashService is the new,
 * standalone home for the hashcash proof-of-work anti-spam check, previously duplicated
 * inline in both the edit and add-comment flows under tools/security.
 */
#[CoversMethod(HashCashService::class, 'checkHashcash')]
class HashCashServiceTest extends YesWikiTestCase
{
    public function testCheckHashcashIsAlwaysTrueWhenDisabled()
    {
        $wiki = $this->getWiki();
        $wiki->config['use_hashcash'] = false;
        $wiki->request->request->remove('hashcash_value');

        $hashCashService = $wiki->services->get(HashCashService::class);

        $this->assertTrue($hashCashService->checkHashcash());
    }

    public function testCheckHashcashFailsWhenEnabledAndValueMissing()
    {
        $wiki = $this->getWiki();
        $wiki->config['use_hashcash'] = true;
        $wiki->request->request->remove('hashcash_value');

        $hashCashService = $wiki->services->get(HashCashService::class);

        $this->assertFalse($hashCashService->checkHashcash());
    }

    public function testCheckHashcashSucceedsWhenEnabledAndValueMatchesSecret()
    {
        $wiki = $this->getWiki();
        $wiki->config['use_hashcash'] = true;

        require_once YESWIKI_SOURCE_DIR . '/src/wp-hashcash.lib';
        $secretValue = 'test-secret-' . uniqid();
        file_put_contents(HASHCASH_SECRET_FILE, $secretValue);

        try {
            $wiki->request->request->set('hashcash_value', $secretValue);
            $hashCashService = $wiki->services->get(HashCashService::class);

            $this->assertTrue($hashCashService->checkHashcash());

            $wiki->request->request->set('hashcash_value', $secretValue . '-wrong');
            $this->assertFalse($hashCashService->checkHashcash());
        } finally {
            $wiki->request->request->remove('hashcash_value');
        }
    }
}
