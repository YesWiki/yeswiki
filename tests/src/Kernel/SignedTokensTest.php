<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\SignedTokens;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A signed token is the same for every visitor, so a cached page can carry it, and useless for any other call. */
class SignedTokensTest extends YesWikiTestCase
{
    public function testATokenIsStableAndBoundToItsCall(): void
    {
        $tokens = $this->getWiki()->services->get(SignedTokens::class);
        $crop = $tokens->sign('POST api/images/cache/300/300/crop');

        $this->assertSame($crop, $tokens->sign('POST api/images/cache/300/300/crop'));
        $this->assertTrue($tokens->verify('POST api/images/cache/300/300/crop', $crop));
        $this->assertFalse($tokens->verify('POST api/images/cache/3000/3000/crop', $crop), 'a size the page never rendered is refused');
        $this->assertFalse($tokens->verify('POST api/images/cache/300/300/crop', ''));
    }

    /** Replicas 10: with the key stated in the environment, every replica signs alike and none writes a key file. */
    public function testAKeyStatedInTheEnvironmentSignsAndIsNotWritten(): void
    {
        $root = sys_get_temp_dir() . '/signed-env-' . bin2hex(random_bytes(6));
        putenv(SignedTokens::KEY_ENV . '=' . str_repeat('cd', 32));
        try {
            $one = new SignedTokens(Storage::rootedAt($root));
            $other = new SignedTokens(Storage::rootedAt($root . '-other'));

            $this->assertSame($one->sign('GET x'), $other->sign('GET x'));
            $this->assertFileDoesNotExist($root . '/' . SignedTokens::KEY_FILE);
        } finally {
            putenv(SignedTokens::KEY_ENV);
        }
    }
}
