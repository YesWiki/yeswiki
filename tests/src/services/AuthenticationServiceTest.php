<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\AccountActivationService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\PasswordHasherFactory;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\StringUtilService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(AuthenticationService::class, 'login')]
#[CoversMethod(AuthenticationService::class, 'logout')]
#[CoversMethod(AuthenticationService::class, 'checkPassword')]
#[CoversMethod(AuthenticationService::class, 'requiresPasswordReset')]
#[CoversMethod(AuthenticationService::class, 'setPassword')]
#[CoversMethod(AuthenticationService::class, 'getLoggedUser')]
#[CoversMethod(AuthenticationService::class, 'getLoggedUserName')]
class AuthenticationServiceTest extends YesWikiTestCase
{
    public const CHARS_FOR_EMAIL = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    public const CHARS_FOR_PASSWORD = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_';
    public const UPPER_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function testAuthenticationServiceExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(AuthenticationService::class));

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $wiki;
    }

    /**
     * @return array{user: User, password: string}
     */
    private function createRandomUser(YesWikiRuntime $wiki): array
    {
        $userManager = $wiki->services->get(UserManager::class);
        do {
            $email = strtolower(StringUtilService::generateRandomString(10, self::CHARS_FOR_EMAIL)) . '@example.com';
        } while (!empty($userManager->getOneByEmail($email)));
        do {
            $name = trim(StringUtilService::generateRandomString(1, self::UPPER_CHARS)
                . StringUtilService::generateRandomString(25, self::CHARS_FOR_PASSWORD));
        } while (!empty($userManager->getOneByName($name)));
        $password = StringUtilService::generateRandomString(25, self::CHARS_FOR_PASSWORD);

        $userManager->create($name, $email, $password);
        $user = $userManager->getOneByName($name);

        self::assertNotNull($user, "the fixture user '$name' was not created");

        return [
            'user' => $user,
            'password' => $password,
        ];
    }

    #[Depends('testAuthenticationServiceExisting')]
    public function testCheckPassword(YesWikiRuntime $wiki): void
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

    /** md5 is out as a credential, and stays in the row as the marker that says "reset me". */
    #[Depends('testAuthenticationServiceExisting')]
    public function testAnMd5PasswordCannotSignInButSurvivesForTheResetFlow(YesWikiRuntime $wiki): void
    {
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        ['user' => $user, 'password' => $password] = $this->createRandomUser($wiki);

        try {
            $legacyHash = md5($password);
            $userManager->upgradePassword($user, $legacyHash);
            $legacyUser = self::requireUser($userManager->getOneByName($user['name']));
            $this->assertSame($legacyHash, $legacyUser->getPassword(), 'fixture: the account now holds an md5');

            $this->assertTrue($authenticationService->requiresPasswordReset($legacyUser));
            $this->assertFalse(
                $authenticationService->checkPassword($password, $legacyUser),
                'the CORRECT password must not get in either -- otherwise a leaked md5 table is still usable'
            );

            $this->assertSame(
                $legacyHash,
                self::requireUser($userManager->getOneByName($user['name']))->getPassword(),
                'the stored md5 must be left exactly as it was'
            );

            $authenticationService->setPassword($legacyUser, 'a-brand-new-passphrase');
            $resetUser = self::requireUser($userManager->getOneByName($user['name']));
            $this->assertFalse($authenticationService->requiresPasswordReset($resetUser));
            $this->assertTrue($authenticationService->checkPassword('a-brand-new-passphrase', $resetUser));
        } finally {
            $userManager->delete(self::requireUser($userManager->getOneByName($user['name'])));
        }
    }

    /**
     * Refusing md5 at the sign-in form is not enough on its own: connectUser() re-hydrates an identity every request from the session and then from a remember-me cookie, and neither calls checkPassword().
     */
    #[Depends('testAuthenticationServiceExisting')]
    public function testAnMd5AccountIsNotKeptSignedInByItsExistingSession(YesWikiRuntime $wiki): void
    {
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        ['user' => $user] = $this->createRandomUser($wiki);

        try {
            $authenticationService->login($user);
            $this->assertSame($user['name'], $_SESSION['user']['name'] ?? null, 'fixture: signed in');

            $userManager->upgradePassword($user, md5('whatever was stored before'));

            $authenticationService->connectUser();

            $this->assertArrayNotHasKey(
                'user',
                $_SESSION,
                'a live session must not carry an md5 account past the sign-in refusal'
            );
        } finally {
            $authenticationService->logout();
            $userManager->delete(self::requireUser($userManager->getOneByName($user['name'])));
        }
    }

    /**
     * connectUser()'s own comment calls the session the fast path -- "faster to connect from session" -- and it had never run.
     */
    #[Depends('testAuthenticationServiceExisting')]
    public function testTheSessionFastPathActuallyResolvesAUser(YesWikiRuntime $wiki): void
    {
        $userManager = $wiki->services->get(UserManager::class);
        ['user' => $user] = $this->createRandomUser($wiki);

        $exposed = new class($wiki->services->get(AccountActivationService::class), $wiki->services->get(HibernationService::class), $wiki->services->get(ParameterBagInterface::class), $wiki->services->get(PasswordHasherFactory::class), $userManager, $wiki->services) extends AuthenticationService {
            /**
             * @return array<string, mixed> the parent declares a bare `array`, so no shape to narrow to
             */
            public function resolveFromSession(): array
            {
                return $this->connectUserFromSession();
            }
        };

        try {
            $wiki->services->get(AuthenticationService::class)->login($user);

            $data = $exposed->resolveFromSession();

            $this->assertSame($user['name'], $data['user']['name']);
            $this->assertInstanceOf(\DateTime::class, $data['lastConnectionDate']);
        } finally {
            $wiki->services->get(AuthenticationService::class)->logout();
            $userManager->delete(self::requireUser($userManager->getOneByName($user['name'])));
        }
    }

    #[Depends('testAuthenticationServiceExisting')]
    public function testLoginSetsSessionAndLogoutClearsIt(YesWikiRuntime $wiki): void
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
    public function testSwitchingIdentityCleansSensitiveSessionDataButSameUserReloginDoesNot(YesWikiRuntime $wiki): void
    {
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        ['user' => $userA] = $this->createRandomUser($wiki);
        ['user' => $userB] = $this->createRandomUser($wiki);

        try {
            $authenticationService->login($userA);
            $_SESSION['_csrf'] = ['dummy-token-id' => 'dummy-token-value'];

            $authenticationService->login($userA);
            $this->assertArrayHasKey('_csrf', $_SESSION);

            $authenticationService->login($userB);
            $this->assertArrayNotHasKey('_csrf', $_SESSION);
        } finally {
            $authenticationService->logout();
            $userManager->delete($userA);
            $userManager->delete($userB);
        }
    }

    /**
     * Regression test for the session-fixation fix: the session ID must be regenerated whenever the authenticated identity actually changes (anonymous -> user, or user A -> user B), but must stay stable across repeated calls for the same already-logged-in user, as happens on every request via AuthenticationService::connectUser().
     */
    #[Depends('testAuthenticationServiceExisting')]
    public function testLoginRegeneratesSessionIdOnlyOnIdentityChange(YesWikiRuntime $wiki): void
    {
        $userManager = $wiki->services->get(UserManager::class);
        ['user' => $userA] = $this->createRandomUser($wiki);
        ['user' => $userB] = $this->createRandomUser($wiki);

        $authenticationService = new class($wiki->services->get(AccountActivationService::class), $wiki->services->get(HibernationService::class), $wiki->services->get(ParameterBagInterface::class), $wiki->services->get(PasswordHasherFactory::class), $userManager, $wiki->services) extends AuthenticationService {
            protected function isCli(): bool
            {
                return false;
            }
        };

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
            $wiki->services->get(AuthenticationService::class)->logout();
            $userManager->delete($userA);
            $userManager->delete($userB);
        }
    }

    #[Depends('testAuthenticationServiceExisting')]
    public function testLoginDoesNotTouchSessionIdInCliMode(YesWikiRuntime $wiki): void
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
     * Regression test for ticket 07 (accountactivationbyemail absorbed into core): login() must block an unactivated user's login when signup_email_activation is on, and let an activated one through.
     */
    #[Depends('testAuthenticationServiceExisting')]
    public function testLoginIsBlockedForAnUnactivatedUserWhenActivationIsOn(YesWikiRuntime $wiki): void
    {
        $userManager = $wiki->services->get(UserManager::class);
        $accountActivationService = $wiki->services->get(AccountActivationService::class);
        ['user' => $user] = $this->createRandomUser($wiki);

        $forcedParams = new class($wiki->services->get(ParameterBagInterface::class)) implements ParameterBagInterface {
            public function __construct(private ParameterBagInterface $real)
            {
            }

            /**
             * @return \UnitEnum|array<mixed>|string|int|float|bool|null
             */
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

            /**
             * @param array<string, mixed> $parameters
             */
            public function add(array $parameters): void
            {
                $this->real->add($parameters);
            }

            /**
             * @return array<string, mixed>
             */
            public function all(): array
            {
                return $this->real->all();
            }

            public function remove(string $name): void
            {
                $this->real->remove($name);
            }

            public function set(string $name, mixed $value): void
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
            $wiki->services
        );

        try {
            $this->assertFalse($accountActivationService->isActivated($user['name']));

            try {
                $authenticationService->login($user);
                $this->fail('login() must throw BadLoginException for an unactivated user when signup_email_activation is on');
            } catch (\YesWiki\Identity\Exception\BadLoginException $th) {
            }

            $accountActivationService->activate($user['name'], '', true);
            $authenticationService->login($user);
            $this->assertSame($user['name'], $_SESSION['user']['name'] ?? null);
        } finally {
            $authenticationService->logout();
            $userManager->delete(self::requireUser($userManager->getOneByName($user['name'])));
        }
    }
}
