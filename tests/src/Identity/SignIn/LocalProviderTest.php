<?php

namespace YesWiki\Test\Identity\SignIn;

use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\SignIn\AccountLinks;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\Provider\LocalProvider;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class LocalProviderTest extends YesWikiTestCase
{
    private const PASSWORD = 'a-long-enough-password';
    private const TAGS = ['LocalA', 'Md5User', 'LinkedA'];

    private LocalProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $services = self::getWiki()->services;
        $this->cleanUp();
        $users = $services->get(UserManager::class);
        $users->create('LocalA', 'local-a@example.com', self::PASSWORD);
        $md5 = $users->create('Md5User', 'local-md5@example.com', self::PASSWORD);
        $this->assertNotNull($md5);
        $users->upgradePassword($md5, md5('whatever'));
        $linked = $users->create('LinkedA', 'local-linked@example.com', self::PASSWORD);
        $this->assertNotNull($linked);
        $services->get(AccountLinks::class)->link($linked, 'acme', 'subject-linked-a');

        $this->provider = new LocalProvider(ProviderConfig::fromArray('local', ['type' => 'local']), $services->get(AuthenticationService::class), $users);
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        $services = self::getWiki()->services;
        $users = $services->get(UserManager::class);
        foreach (self::TAGS as $tag) {
            if ($user = $users->getOneByName($tag)) {
                $users->delete($user);
            }
            $services->get(\YesWiki\Content\Service\PageManager::class)->deleteOrphaned($tag);
        }
    }

    private function failureFor(string $login, string $password): ProviderFailure
    {
        try {
            $this->provider->authenticate($login, $password);
        } catch (ProviderFailure $failure) {
            return $failure;
        }
        $this->fail('the sign-in should have been refused');
    }

    private function assertReason(FailureReason $reason, string $login, string $password): void
    {
        $this->assertSame($reason, $this->failureFor($login, $password)->reason);
    }

    public function testGoodPasswordReturnsTheAccount(): void
    {
        $this->assertSame('LocalA', $this->provider->authenticate('LocalA', self::PASSWORD)->attributes['local_account']);
    }

    public function testByEmailToo(): void
    {
        $this->assertSame('LocalA', $this->provider->authenticate('local-a@example.com', self::PASSWORD)->attributes['local_account']);
    }

    public function testWrongPassword(): void
    {
        $this->assertReason(FailureReason::WrongCredentials, 'LocalA', 'nope');
    }

    public function testUnknownUser(): void
    {
        $this->assertReason(FailureReason::WrongCredentials, 'Nobody', 'x');
    }

    public function testObsoleteMd5KeepsItsOwnMessage(): void
    {
        $failure = $this->failureFor('Md5User', 'whatever');
        $this->assertSame(FailureReason::WrongCredentials, $failure->reason);
        $this->assertSame(_t('LOGIN_PASSWORD_FORMAT_OBSOLETE'), $failure->employeeMessage);
    }

    public function testALinkedAccountCannotUseThePasswordForm(): void
    {
        $this->assertReason(FailureReason::LinkedElsewhere, 'LinkedA', 'x');
    }

    public function testAWrongPasswordKeepsTheMessageOfTheOldForm(): void
    {
        $this->assertSame(_t('LOGIN_WRONG_PASSWORD'), $this->failureFor('LocalA', 'nope')->employeeMessage);
        $this->assertSame(_t('LOGIN_WRONG_USER'), $this->failureFor('Nobody', 'x')->employeeMessage);
    }
}
