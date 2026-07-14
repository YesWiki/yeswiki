<?php

namespace YesWiki\Test\Core\Controller;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Service\PasswordHasherFactory;
use YesWiki\Core\Service\UserManager;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(AuthController::class, 'login')]
#[CoversMethod(AuthController::class, 'logout')]
#[CoversMethod(AuthController::class, 'checkPassword')]
#[CoversMethod(AuthController::class, 'getLoggedUser')]
#[CoversMethod(AuthController::class, 'getLoggedUserName')]
class AuthControllerTest extends YesWikiTestCase
{
    public const CHARS_FOR_EMAIL = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    public const CHARS_FOR_PASSWORD = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_';
    public const UPPER_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function testAuthControllerExisting(): Wiki
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(AuthController::class));

        // a real PHP session is needed to exercise the session-id side effects of login();
        // YesWikiLoader deliberately skips session_start() when bootstrapping for tests
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $wiki;
    }

    /**
     * @return array{user: User, password: string}
     */
    private function createRandomUser(Wiki $wiki): array
    {
        $userManager = $wiki->services->get(UserManager::class);
        do {
            $email = strtolower($wiki->generateRandomString(10, self::CHARS_FOR_EMAIL)) . '@example.com';
        } while (!empty($userManager->getOneByEmail($email)));
        do {
            $name = $wiki->generateRandomString(1, self::UPPER_CHARS)
                . $wiki->generateRandomString(25, self::CHARS_FOR_PASSWORD);
        } while (!empty($userManager->getOneByName($name)));
        $password = $wiki->generateRandomString(25, self::CHARS_FOR_PASSWORD);

        $userManager->create($name, $email, $password);

        return [
            'user' => $userManager->getOneByName($name),
            'password' => $password,
        ];
    }

    #[Depends('testAuthControllerExisting')]
    public function testCheckPassword(Wiki $wiki)
    {
        $authController = $wiki->services->get(AuthController::class);
        $userManager = $wiki->services->get(UserManager::class);
        ['user' => $user, 'password' => $password] = $this->createRandomUser($wiki);

        try {
            $this->assertTrue($authController->checkPassword($password, $user));
            $this->assertFalse($authController->checkPassword($password . 'wrong', $user));
        } finally {
            $userManager->delete($user);
        }
    }

    #[Depends('testAuthControllerExisting')]
    public function testLoginSetsSessionAndLogoutClearsIt(Wiki $wiki)
    {
        $authController = $wiki->services->get(AuthController::class);
        $userManager = $wiki->services->get(UserManager::class);
        ['user' => $user] = $this->createRandomUser($wiki);

        try {
            $authController->login($user);

            $this->assertSame($user['name'], $_SESSION['user']['name'] ?? null);
            $this->assertNotEmpty($_SESSION['user']['lastConnection'] ?? null);
            $this->assertSame($user['name'], $authController->getLoggedUserName());
            $loggedUser = $authController->getLoggedUser();
            $this->assertIsArray($loggedUser);
            $this->assertSame($user['name'], $loggedUser['name']);

            $authController->logout();

            $this->assertArrayNotHasKey('user', $_SESSION);
            $this->assertSame('', $authController->getLoggedUser());
        } finally {
            $authController->logout();
            $userManager->delete($user);
        }
    }

    #[Depends('testAuthControllerExisting')]
    public function testSwitchingIdentityCleansSensitiveSessionDataButSameUserReloginDoesNot(Wiki $wiki)
    {
        $authController = $wiki->services->get(AuthController::class);
        $userManager = $wiki->services->get(UserManager::class);
        ['user' => $userA] = $this->createRandomUser($wiki);
        ['user' => $userB] = $this->createRandomUser($wiki);

        try {
            $authController->login($userA);
            $_SESSION['_csrf'] = ['dummy-token-id' => 'dummy-token-value'];

            // re-logging in as the SAME user must NOT wipe unrelated session data
            $authController->login($userA);
            $this->assertArrayHasKey('_csrf', $_SESSION);

            // logging in as a DIFFERENT user must clear it
            $authController->login($userB);
            $this->assertArrayNotHasKey('_csrf', $_SESSION);
        } finally {
            $authController->logout();
            $userManager->delete($userA);
            $userManager->delete($userB);
        }
    }

    /**
     * Regression test for the session-fixation fix: the session ID must be
     * regenerated whenever the authenticated identity actually changes
     * (anonymous -> user, or user A -> user B), but must stay stable across
     * repeated calls for the same already-logged-in user, as happens on
     * every request via AuthController::connectUser().
     *
     * AuthController::login() only regenerates the session id when
     * `!$this->wiki->isCli()`, and phpunit always runs under the CLI SAPI,
     * so this test builds a minimal non-CLI stand-in for `$wiki` (skipping
     * its heavy real constructor) to reach that branch, while every other
     * collaborator (UserManager, PasswordHasherFactory, ...) is the real,
     * DI-provided one from the bootstrapped test wiki.
     */
    #[Depends('testAuthControllerExisting')]
    public function testLoginRegeneratesSessionIdOnlyOnIdentityChange(Wiki $wiki)
    {
        $userManager = $wiki->services->get(UserManager::class);
        ['user' => $userA] = $this->createRandomUser($wiki);
        ['user' => $userB] = $this->createRandomUser($wiki);

        $nonCliWiki = new class extends Wiki {
            public function __construct()
            {
                // deliberately skip the real bootstrap: this stand-in is only
                // ever used through login(), which does not touch any other
                // Wiki state than isCli()
            }

            public function isCli(): bool
            {
                return false;
            }
        };
        $authController = new AuthController(
            $wiki->services->get(ParameterBagInterface::class),
            $wiki->services->get(PasswordHasherFactory::class),
            $wiki->services->get(SecurityController::class),
            $userManager,
            $nonCliWiki
        );

        try {
            unset($_SESSION['user']);

            $idAnonymous = session_id();
            $authController->login($userA);
            $idAfterFirstLogin = session_id();
            $this->assertNotSame($idAnonymous, $idAfterFirstLogin, 'logging in from anonymous must regenerate the session id');

            $authController->login($userA);
            $idAfterSameUserRelogin = session_id();
            $this->assertSame($idAfterFirstLogin, $idAfterSameUserRelogin, 're-confirming the same logged-in user must not regenerate the session id');

            $authController->login($userB);
            $idAfterSwitch = session_id();
            $this->assertNotSame($idAfterSameUserRelogin, $idAfterSwitch, 'switching to a different user must regenerate the session id');
        } finally {
            // logout() (unlike login()) touches $this->wiki->request via cleanOldFormatCookie(),
            // which the non-CLI stand-in above does not have, so clean up through the real,
            // fully-wired AuthController instance instead - $_SESSION is a shared superglobal.
            $wiki->services->get(AuthController::class)->logout();
            $userManager->delete($userA);
            $userManager->delete($userB);
        }
    }

    #[Depends('testAuthControllerExisting')]
    public function testLoginDoesNotTouchSessionIdInCliMode(Wiki $wiki)
    {
        $authController = $wiki->services->get(AuthController::class);
        $userManager = $wiki->services->get(UserManager::class);
        ['user' => $user] = $this->createRandomUser($wiki);

        try {
            unset($_SESSION['user']);
            $idBefore = session_id();
            $authController->login($user);
            $idAfter = session_id();

            $this->assertSame($idBefore, $idAfter, 'phpunit runs under the CLI SAPI, so login() must not touch the session id');
        } finally {
            $authController->logout();
            $userManager->delete($user);
        }
    }
}
