<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Service\FormPropertiesService;
use YesWiki\Identity\Exception\UserFieldException;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Saving an entry never links it, nor its author's session, to an account that is not theirs. */
class EntryOwnershipTest extends YesWikiTestCase
{
    private const VICTIM = 'EntryOwnershipTestVictim';
    private const EMAIL = 'entryownershipvictim@example.com';

    private const FORM = [
        'id' => 'entry-ownership-test',
        'entry_creates_user' => ['name_field' => 'bf_titre', 'email_field' => 'bf_mail'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $services = $this->getWiki()->services;
        if (!$services->get(UserManager::class)->getOneByName(self::VICTIM)) {
            $services->get(UserManager::class)->create(self::VICTIM, self::EMAIL, 'a-long-enough-Passw0rd');
        }
        $services->get(AuthenticationService::class)->logout();
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        $services->get(AuthenticationService::class)->logout();
        if ($user = $services->get(UserManager::class)->getOneByName(self::VICTIM)) {
            $services->get(UserManager::class)->delete($user);
        }
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function apply(): array
    {
        return $this->getWiki()->services->get(FormPropertiesService::class)->applyUserCreation(self::FORM, [
            'bf_titre' => 'Entry ownership test',
            'bf_mail' => self::EMAIL,
            'nomwiki' => self::VICTIM,
        ]);
    }

    public function testAVisitorCannotTakeTheNameOfAnExistingAccount(): void
    {
        try {
            $this->apply();
            $this->fail('An anonymous visitor linked an entry to an existing account.');
        } catch (UserFieldException $exception) {
            $this->assertSame(_t('BAZ_USER_FIELD_NOT_YOUR_ACCOUNT'), $exception->getMessage());
            $this->assertEmpty($this->getWiki()->services->get(AuthenticationService::class)->getLoggedUser());
        }
    }

    public function testAnAccountCanLinkAnEntryToItself(): void
    {
        $services = $this->getWiki()->services;
        $services->get(AuthenticationService::class)->login(self::requireUser($services->get(UserManager::class)->getOneByName(self::VICTIM)));

        $this->assertSame(self::VICTIM, $this->apply()['nomwiki']);
    }
}
