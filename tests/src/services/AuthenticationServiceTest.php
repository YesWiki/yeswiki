<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\AccountActivationService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Identity\Service\PasswordHasherFactory;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(AuthenticationService::class, 'login')]
#[CoversMethod(AuthenticationService::class, 'logout')]
#[CoversMethod(AuthenticationService::class, 'checkPassword')]
#[CoversMethod(AuthenticationService::class, 'getLoggedUser')]
#[CoversMethod(AuthenticationService::class, 'getLoggedUserName')]
class AuthenticationServiceTest extends YesWikiTestCase
{
    public const CHARS_FOR_EMAIL = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    public const CHARS_FOR_PASSWORD = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_';
    public const UPPER_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function testAuthenticationServiceExisting(): Wiki
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(AuthenticationService::class));

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
            // trim: UserManager::create() trims the name, so a random name with a trailing
            // space would be stored trimmed and no longer be found by getOneByName()
            $name = trim($wiki->generateRandomString(1, self::UPPER_CHARS)
                . $wiki->generateRandomString(25, self::CHARS_FOR_PASSWORD));
        } while (!empty($userManager->getOneByName($name)));
        $password = $wiki->generateRandomString(25, self::CHARS_FOR_PASSWORD);

        $userManager->create($name, $email, $password);

        return [
            'user' => $userManager->getOneByName($name),
            'password' => $password,
        ];
    }

    #[Depends('testAuthenticationServiceExisting')]
    public function testCheckPassword(Wiki $wiki)
    {
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        ['user' => $user, 'password' => $password] = $this->createRandomUser($wiki);

        try {
            $this->assertTrue($authenticationService->checkPassword($password, $user));
            $this->assertFalse($authenticationService->checkPassword($password . 'wrong', $user));
        } finally {
            $userManager->delete($user);
        }
    }

    #[Depends('testAuthenticationServiceExisting')]
    public function testLoginSetsSessionAndLogoutClearsIt(Wiki $wiki)
    {
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        ['user' => $user] = $this->createRandomUser($wiki);

        try {
            $authenticationService->login($user);

            $this->assertSame($user['name'], $_SESSION['user']['name'] ?? null);
            $this->assertNotEmpty($_SESSION['user']['lastConnection'] ?? null);
            $this->assertSame($user['name'], $authenticationService->getLoggedUserName());
            $loggedUser = $authenticationService->getLoggedUser();
            $this->assertIsArray($loggedUser);
            $this->assertSame($user['name'], $loggedUser['name']);

            $authenticationService->logout();

            $this->assertArrayNotHasKey('user', $_SESSION);
            $this->assertSame('', $authenticationService->getLoggedUser());
        } finally {
            $authenticationService->logout();
            $userManager->delete($user);
        }
    }

    #[Depends('testAuthenticationServiceExisting')]
    public function testSwitchingIdentityCleansSensitiveSessionDataButSameUserReloginDoesNot(Wiki $wiki)
    {
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        ['user' => $userA] = $this->createRandomUser($wiki);
        ['user' => $userB] = $this->createRandomUser($wiki);

        try {
            $authenticationService->login($userA);
            $_SESSION['_csrf'] = ['dummy-token-id' => 'dummy-token-value'];

            // re-logging in as the SAME user must NOT wipe unrelated session data
            $authenticationService->login($userA);
            $this->assertArrayHasKey('_csrf', $_SESSION);

            // logging in as a DIFFERENT user must clear it
            $authenticationService->login($userB);
            $this->assertArrayNotHasKey('_csrf', $_SESSION);
        } finally {
            $authenticationService->logout();
            $userManager->delete($userA);
            $userManager->delete($userB);
        }
    }

    /**
     * Regression test for the session-fixation fix: the session ID must be
     * regenerated whenever the authenticated identity actually changes
     * (anonymous -> user, or user A -> user B), but must stay stable across
     * repeated calls for the same already-logged-in user, as happens on
     * every request via AuthenticationService::connectUser().
     *
     * AuthenticationService::login() only regenerates the session id when
     * `!$this->wiki->isCli()`, and phpunit always runs under the CLI SAPI,
     * so this test builds a minimal non-CLI stand-in for `$wiki` (skipping
     * its heavy real constructor) to reach that branch, while every other
     * collaborator (UserManager, PasswordHasherFactory, ...) is the real,
     * DI-provided one from the bootstrapped test wiki.
     */
    #[Depends('testAuthenticationServiceExisting')]
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
        $authenticationService = new AuthenticationService(
            $wiki->services->get(AccountActivationService::class),
            $wiki->services->get(HibernationService::class),
            $wiki->services->get(ParameterBagInterface::class),
            $wiki->services->get(PasswordHasherFactory::class),
            $userManager,
            $nonCliWiki
        );

        try {
            unset($_SESSION['user']);

            $idAnonymous = session_id();
            $authenticationService->login($userA);
            $idAfterFirstLogin = session_id();
            $this->assertNotSame($idAnonymous, $idAfterFirstLogin, 'logging in from anonymous must regenerate the session id');

            $authenticationService->login($userA);
            $idAfterSameUserRelogin = session_id();
            $this->assertSame($idAfterFirstLogin, $idAfterSameUserRelogin, 're-confirming the same logged-in user must not regenerate the session id');

            $authenticationService->login($userB);
            $idAfterSwitch = session_id();
            $this->assertNotSame($idAfterSameUserRelogin, $idAfterSwitch, 'switching to a different user must regenerate the session id');
        } finally {
            // logout() (unlike login()) touches $this->wiki->request via cleanOldFormatCookie(),
            // which the non-CLI stand-in above does not have, so clean up through the real,
            // fully-wired AuthenticationService instance instead - $_SESSION is a shared superglobal.
            $wiki->services->get(AuthenticationService::class)->logout();
            $userManager->delete($userA);
            $userManager->delete($userB);
        }
    }

    #[Depends('testAuthenticationServiceExisting')]
    public function testLoginDoesNotTouchSessionIdInCliMode(Wiki $wiki)
    {
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        ['user' => $user] = $this->createRandomUser($wiki);

        try {
            unset($_SESSION['user']);
            $idBefore = session_id();
            $authenticationService->login($user);
            $idAfter = session_id();

            $this->assertSame($idBefore, $idAfter, 'phpunit runs under the CLI SAPI, so login() must not touch the session id');
        } finally {
            $authenticationService->logout();
            $userManager->delete($user);
        }
    }

    /**
     * Regression test for ticket 07 (accountactivationbyemail absorbed into core):
     * login() must block an unactivated user's login when signup_email_activation is on,
     * and let an activated one through. The compiled container's ParameterBag is frozen
     * (can't be mutated at runtime), so this constructs an AuthenticationService with a decorator
     * that only overrides signup_email_activation, delegating everything else to the real
     * bag -- the same "construct AuthenticationService directly with a stand-in dependency"
     * approach testLoginRegeneratesSessionIdOnlyOnIdentityChange already uses.
     */
    #[Depends('testAuthenticationServiceExisting')]
    public function testLoginIsBlockedForAnUnactivatedUserWhenActivationIsOn(Wiki $wiki)
    {
        $userManager = $wiki->services->get(UserManager::class);
        $accountActivationService = $wiki->services->get(AccountActivationService::class);
        ['user' => $user] = $this->createRandomUser($wiki);

        $forcedParams = new class ($wiki->services->get(ParameterBagInterface::class)) implements ParameterBagInterface {
            public function __construct(private ParameterBagInterface $real)
            {
            }

            public function get(string $name): \UnitEnum|array|string|int|float|bool|null
            {
                return $name === 'signup_email_activation' ? true : $this->real->get($name);
            }

            public function has(string $name): bool
            {
                return $name === 'signup_email_activation' ? true : $this->real->has($name);
            }

            public function clear(): void
            {
                $this->real->clear();
            }
            public function add(array $parameters): void
            {
                $this->real->add($parameters);
            }
            public function all(): array
            {
                return $this->real->all();
            }
            public function remove(string $name): void
            {
                $this->real->remove($name);
            }
            public function set(string $name, $value): void
            {
                $this->real->set($name, $value);
            }
            public function resolve(): void
            {
                $this->real->resolve();
            }
            public function resolveValue(mixed $value): mixed
            {
                return $this->real->resolveValue($value);
            }
            public function escapeValue(mixed $value): mixed
            {
                return $this->real->escapeValue($value);
            }
            public function unescapeValue(mixed $value): mixed
            {
                return $this->real->unescapeValue($value);
            }
        };

        $authenticationService = new AuthenticationService(
            $accountActivationService,
            $wiki->services->get(HibernationService::class),
            $forcedParams,
            $wiki->services->get(PasswordHasherFactory::class),
            $userManager,
            $wiki
        );

        try {
            $this->assertFalse($accountActivationService->isActivated($user['name']));

            try {
                $authenticationService->login($user);
                $this->fail('login() must throw BadLoginException for an unactivated user when signup_email_activation is on');
            } catch (\YesWiki\Identity\Exception\BadLoginException $th) {
                // expected
            }

            $accountActivationService->activate($user['name'], '', true);
            $authenticationService->login($user); // must not throw now
            $this->assertSame($user['name'], $_SESSION['user']['name'] ?? null);
        } finally {
            $authenticationService->logout();
            $userManager->delete($userManager->getOneByName($user['name']));
        }
    }
}
