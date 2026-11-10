<?php

namespace YesWiki\Test\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Csrf\CsrfToken;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Security\SignedCsrfTokenManager;
use YesWiki\Kernel\Service\SignedTokens;

/** Replicas 13: a form one replica showed posts to another, since a token is recomputed rather than looked up. */
#[CoversClass(SignedCsrfTokenManager::class)]
class SignedCsrfTokenManagerTest extends TestCase
{
    private string $root;

    private int $now = 1_800_000_000;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yw-csrf-' . bin2hex(random_bytes(4));
    }

    public function testATokenIsValidOnAnotherReplicaSharingTheKey(): void
    {
        $token = $this->manager('visitor:alice')->getToken('main');

        $this->assertTrue($this->manager('visitor:alice')->isTokenValid($token), 'nothing was stored, nothing is looked up');
    }

    public function testATokenOfOneVisitorIsRefusedForAnother(): void
    {
        $token = $this->manager('user:Alice:1')->getToken('main');

        $this->assertFalse($this->manager('user:Bob:1')->isTokenValid($token));
        $this->assertFalse($this->manager('user:Alice:2')->isTokenValid($token), 'a revoked generation');
    }

    public function testATokenIsBoundToItsIdAndItsDay(): void
    {
        $manager = $this->manager('visitor:alice');
        $token = $manager->getToken('main');

        $this->assertFalse($manager->isTokenValid(new CsrfToken('other', $token->getValue())), 'another form');
        $this->now += SignedCsrfTokenManager::LIFETIME + 2 * 3600;
        $this->assertFalse($manager->isTokenValid($token), 'a day later');
    }

    public function testAnEditedTokenIsRefused(): void
    {
        $manager = $this->manager('visitor:alice');
        [$expires, $signature] = explode('.', $manager->getToken('main')->getValue());

        $this->assertFalse($manager->isTokenValid(new CsrfToken('main', ((int)$expires + 3600) . '.' . $signature)), 'a later expiry');
        $this->assertFalse($manager->isTokenValid(new CsrfToken('main', $signature)), 'no expiry');
        $this->assertFalse($manager->isTokenValid(new CsrfToken('main', '')));
    }

    public function testTheSameTokenIsGivenWithinTheHour(): void
    {
        $manager = $this->manager('visitor:alice');

        $this->assertSame($manager->getToken('main')->getValue(), $manager->refreshToken('main')->getValue());
        $this->assertNull($manager->removeToken('main'));
    }

    private function manager(string $visitor): SignedCsrfTokenManager
    {
        return new SignedCsrfTokenManager(new SignedTokens(Storage::rootedAt($this->root)), static fn (): string => $visitor, fn (): int => $this->now);
    }
}
