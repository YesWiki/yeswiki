<?php

namespace YesWiki\Test\Identity;

use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A remember-me sign-in stays a remember-me sign-in when the next request reconnects from the session. */
class RememberMeSessionTest extends YesWikiTestCase
{
    private const NAME = 'RememberMeSessionUser';

    private AuthenticationService $authenticationService;
    private UserManager $userManager;

    protected function setUp(): void
    {
        parent::setUp();
        $services = $this->getWiki()->services;
        $this->authenticationService = $services->get(AuthenticationService::class);
        $this->userManager = $services->get(UserManager::class);
        if (!$this->userManager->getOneByName(self::NAME)) {
            $this->userManager->create(self::NAME, 'remembermesessionuser@example.com', 'a-long-enough-password');
        }
    }

    protected function tearDown(): void
    {
        $this->authenticationService->logout();
        if ($user = $this->userManager->getOneByName(self::NAME)) {
            $this->userManager->delete($user);
        }
        parent::tearDown();
    }

    private function signIn(bool $remember): void
    {
        $user = $this->userManager->getOneByName(self::NAME);
        $this->assertNotNull($user);
        $this->authenticationService->login($user, $remember ? 1 : 0);
    }

    public function testReconnectingFromTheSessionKeepsRememberMe(): void
    {
        $this->signIn(true);
        $this->assertTrue($_SESSION['user']['remember']);

        $this->authenticationService->connectUser();

        $this->assertSame(self::NAME, $_SESSION['user']['name'] ?? null);
        $this->assertTrue($_SESSION['user']['remember']);
    }

    public function testReconnectingFromTheSessionKeepsAShortSignInShort(): void
    {
        $this->signIn(false);

        $this->authenticationService->connectUser();

        $this->assertSame(self::NAME, $_SESSION['user']['name'] ?? null);
        $this->assertFalse($_SESSION['user']['remember']);
    }

    public function testASessionOlderThanAnHourGoesBackToTheCookies(): void
    {
        $this->signIn(true);
        $_SESSION['user']['lastConnection'] = time() - 2 * 60 * 60;

        $this->authenticationService->connectUser();

        $this->assertEmpty($_SESSION['user']['name'] ?? null);
    }
}
