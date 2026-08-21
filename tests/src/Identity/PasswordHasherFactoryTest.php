<?php

namespace YesWiki\Test\Identity;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Security\LegacyPasswordHash;
use YesWiki\Identity\Service\PasswordHasherFactory;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The factory's configuration is the load-bearing part of "md5 is out", and it is the part a reader cannot see by looking at behaviour: `md5` used to sit in `migrate_from`, and Symfony's MigratingPasswordHasher tries every hasher in that chain on verify().
 */
#[CoversMethod(PasswordHasherFactory::class, '__construct')]
class PasswordHasherFactoryTest extends YesWikiTestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function hasherProvider(): array
    {
        return [
            'user accounts' => [User::class],
            'form password fields' => [PasswordHasherFactory::BAZAR_FIELD],
        ];
    }

    #[DataProvider('hasherProvider')]
    public function testNoHasherWillVerifyAnMd5(string $hasherName): void
    {
        $hasher = $this->getWiki()->services->get(PasswordHasherFactory::class)->getPasswordHasher($hasherName);

        $this->assertFalse(
            $hasher->verify(md5('the original password'), 'the original password'),
            "the '{$hasherName}' hasher must not accept an md5 -- has md5 been put back in migrate_from?"
        );
    }

    /**
     * The other half of keeping the hash rather than blanking it: a stored md5 must still be reported as needing a rehash, so the first legitimate pass of a plain password (the reset flow) replaces it instead of leaving it lying there forever.
     */
    #[DataProvider('hasherProvider')]
    public function testAnMd5IsStillReportedAsNeedingRehash(string $hasherName): void
    {
        $hasher = $this->getWiki()->services->get(PasswordHasherFactory::class)->getPasswordHasher($hasherName);

        $this->assertTrue($hasher->needsRehash(md5('the original password')));
        $this->assertFalse($hasher->needsRehash($hasher->hash('a freshly hashed password')));
    }

    #[DataProvider('hasherProvider')]
    public function testWhatTheHashersWriteIsNeverAnMd5(string $hasherName): void
    {
        $hasher = $this->getWiki()->services->get(PasswordHasherFactory::class)->getPasswordHasher($hasherName);

        $hashed = $hasher->hash('correct horse battery staple');

        $this->assertFalse(LegacyPasswordHash::isMd5($hashed));
        $this->assertTrue($hasher->verify($hashed, 'correct horse battery staple'));
    }

    /** There is no md5 hasher left to configure, and nothing may reintroduce one. */
    public function testTheMd5HasherClassIsGone(): void
    {
        $this->assertFileDoesNotExist(
            YESWIKI_PROGRAM_DIR . '/src/Identity/Security/MD5PasswordHasher.php',
            'MD5PasswordHasher was deleted: nothing in this codebase may hash with md5'
        );
    }

    /**
     * @return array<string, array{string|null, bool}>
     */
    public static function hashShapeProvider(): array
    {
        return [
            'md5 output' => [md5('x'), true],
            'md5 uppercased' => [strtoupper(md5('x')), true],
            '31 hex chars' => [substr(md5('x'), 1), false],
            '33 hex chars' => [md5('x') . 'a', false],
            'not hex' => [str_repeat('z', 32), false],
            'a bcrypt hash' => ['$2y$13$abcdefghijklmnopqrstuv', false],
            'empty' => ['', false],
            'null' => [null, false],
        ];
    }

    #[DataProvider('hashShapeProvider')]
    public function testIsMd5RecognisesExactlyMd5Output(?string $candidate, bool $expected): void
    {
        $this->assertSame($expected, LegacyPasswordHash::isMd5($candidate));
    }
}
