<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Identity\Exception\BadActivationKeyException;
use YesWiki\Identity\Exception\UserNameDoesNotExistException;
use YesWiki\Identity\Service\AccountActivationService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression/acceptance tests for ticket 07 (accountactivationbyemail absorbed into core):
 * activation status/key are fields on the user's Content body (not standalone triples),
 * hidden from every generic read path via Guard::checkUserAcls() -- the same "always
 * hidden, even from owner/admin" treatment as the password hash.
 */
class AccountActivationServiceTest extends YesWikiTestCase
{
    private const OTHER_VIEWER = 'AccountActivationServiceTestOtherViewer';

    private function cleanupUser(UserManager $userManager, string $name): void
    {
        $user = $userManager->getOneByName($name);
        if ($user) {
            $userManager->delete($user);
        }
    }

    /**
     * Plants a known activation key directly via PageManager (bypassing
     * sendActivationLink(), which would send a real email) so the key/issuedAt is
     * controllable for expiry tests.
     */
    private function plantActivationKey(PageManager $pageManager, string $name, string $key, int $issuedAt): void
    {
        $page = $pageManager->getOne($name, null, true, true);
        $body = json_decode($page['body'], true);
        $body[AccountActivationService::BODY_KEY_KEY] = $key . ':' . $issuedAt;
        $pageManager->save($name, json_encode($body), '', true);
    }

    public function testNewUserStartsNotActivated()
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $accountActivationService = $wiki->services->get(AccountActivationService::class);

        $name = 'AccountActivationServiceTestNewUser';
        $this->cleanupUser($userManager, $name);

        try {
            $userManager->create($name, 'aast-new@example.tld', 'Aa1!aaaaRegression');
            $this->assertFalse($accountActivationService->isActivated($name));
        } finally {
            $this->cleanupUser($userManager, $name);
        }
    }

    public function testActivateWithCorrectKeySucceedsAndClearsTheKey()
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $pageManager = $wiki->services->get(PageManager::class);
        $accountActivationService = $wiki->services->get(AccountActivationService::class);

        $name = 'AccountActivationServiceTestActivate';
        $this->cleanupUser($userManager, $name);

        try {
            $userManager->create($name, 'aast-activate@example.tld', 'Aa1!aaaaRegression');
            $this->plantActivationKey($pageManager, $name, 'REALKEY123', time());

            $accountActivationService->activate($name, 'REALKEY123');

            $this->assertTrue($accountActivationService->isActivated($name));
        } finally {
            $this->cleanupUser($userManager, $name);
        }
    }

    public function testActivateWithWrongKeyThrows()
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $pageManager = $wiki->services->get(PageManager::class);
        $accountActivationService = $wiki->services->get(AccountActivationService::class);

        $name = 'AccountActivationServiceTestWrongKey';
        $this->cleanupUser($userManager, $name);

        try {
            $userManager->create($name, 'aast-wrongkey@example.tld', 'Aa1!aaaaRegression');
            $this->plantActivationKey($pageManager, $name, 'REALKEY123', time());

            $this->expectException(BadActivationKeyException::class);
            $accountActivationService->activate($name, 'WRONGKEY');
        } finally {
            $this->cleanupUser($userManager, $name);
        }
    }

    public function testActivateWithExpiredKeyThrows()
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $pageManager = $wiki->services->get(PageManager::class);
        $accountActivationService = $wiki->services->get(AccountActivationService::class);

        $name = 'AccountActivationServiceTestExpiredKey';
        $this->cleanupUser($userManager, $name);

        try {
            $userManager->create($name, 'aast-expiredkey@example.tld', 'Aa1!aaaaRegression');
            // issued further in the past than UserManager::KEY_TTL (1 hour)
            $this->plantActivationKey($pageManager, $name, 'REALKEY123', time() - UserManager::KEY_TTL - 60);

            $this->expectException(BadActivationKeyException::class);
            $accountActivationService->activate($name, 'REALKEY123');
        } finally {
            $this->cleanupUser($userManager, $name);
        }
    }

    public function testForceActivateBypassesKeyCheck()
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $accountActivationService = $wiki->services->get(AccountActivationService::class);

        $name = 'AccountActivationServiceTestForce';
        $this->cleanupUser($userManager, $name);

        try {
            $userManager->create($name, 'aast-force@example.tld', 'Aa1!aaaaRegression');

            $accountActivationService->activate($name, '', true);

            $this->assertTrue($accountActivationService->isActivated($name));
        } finally {
            $this->cleanupUser($userManager, $name);
        }
    }

    public function testInactivateResetsStatus()
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $accountActivationService = $wiki->services->get(AccountActivationService::class);

        $name = 'AccountActivationServiceTestInactivate';
        $this->cleanupUser($userManager, $name);

        try {
            $userManager->create($name, 'aast-inactivate@example.tld', 'Aa1!aaaaRegression');
            $accountActivationService->activate($name, '', true);
            $this->assertTrue($accountActivationService->isActivated($name));

            $accountActivationService->inactivate($name);

            $this->assertFalse($accountActivationService->isActivated($name));
        } finally {
            $this->cleanupUser($userManager, $name);
        }
    }

    public function testActivateOnNonExistentUserThrows()
    {
        $wiki = $this->getWiki();
        $accountActivationService = $wiki->services->get(AccountActivationService::class);

        $this->expectException(UserNameDoesNotExistException::class);
        $accountActivationService->activate('AccountActivationServiceTestNoSuchUser', '', true);
    }

    public function testActivationFieldsAreHiddenFromGenericPageReadEvenForTheOwner()
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $pageManager = $wiki->services->get(PageManager::class);
        $accountActivationService = $wiki->services->get(AccountActivationService::class);

        $name = 'AccountActivationServiceTestFieldAcl';
        $this->cleanupUser($userManager, $name);

        try {
            $userManager->create($name, 'aast-fieldacl@example.tld', 'Aa1!aaaaRegression');
            $this->plantActivationKey($pageManager, $name, 'REALKEY123', time());
            $accountActivationService->activate($name, '', true);

            // internal fetch (the service's own read) sees the true value
            $this->assertTrue($accountActivationService->isActivated($name));

            // generic page-read path, viewed as the owner: still hidden
            $asOwner = $pageManager->getOne($name, null, false, false, $name);
            $bodyAsOwner = json_decode($asOwner['body'], true);
            $this->assertSame('', $bodyAsOwner[AccountActivationService::BODY_KEY_STATUS]);

            // as an unrelated third party: also hidden
            $asOther = $pageManager->getOne($name, null, false, false, self::OTHER_VIEWER);
            $bodyAsOther = json_decode($asOther['body'], true);
            $this->assertSame('', $bodyAsOther[AccountActivationService::BODY_KEY_STATUS]);
        } finally {
            $this->cleanupUser($userManager, $name);
        }
    }

    public function testPurgeExpiredActivationKeysClearsOnlyExpiredOnes()
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $pageManager = $wiki->services->get(PageManager::class);
        $accountActivationService = $wiki->services->get(AccountActivationService::class);

        $expiredName = 'AccountActivationServiceTestPurgeExpired';
        $freshName = 'AccountActivationServiceTestPurgeFresh';
        $this->cleanupUser($userManager, $expiredName);
        $this->cleanupUser($userManager, $freshName);

        try {
            $userManager->create($expiredName, 'aast-purge1@example.tld', 'Aa1!aaaaRegression');
            $userManager->create($freshName, 'aast-purge2@example.tld', 'Aa1!aaaaRegression');
            $this->plantActivationKey($pageManager, $expiredName, 'OLDKEY', time() - UserManager::KEY_TTL - 60);
            $this->plantActivationKey($pageManager, $freshName, 'FRESHKEY', time());

            $accountActivationService->purgeExpiredActivationKeys();

            $this->assertSame('', $this->readRawActivationKey($pageManager, $expiredName), 'expired key must be cleared');
            $this->assertStringStartsWith('FRESHKEY:', $this->readRawActivationKey($pageManager, $freshName), 'non-expired key must be left alone');
        } finally {
            $this->cleanupUser($userManager, $expiredName);
            $this->cleanupUser($userManager, $freshName);
        }
    }

    private function readRawActivationKey(PageManager $pageManager, string $name): string
    {
        $page = $pageManager->getOne($name, null, true, true);
        $body = json_decode($page['body'], true);

        return $body[AccountActivationService::BODY_KEY_KEY] ?? '';
    }
}
