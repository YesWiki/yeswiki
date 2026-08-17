<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Exception\DeleteUserException;
use YesWiki\Identity\Exception\UserEmailAlreadyUsedException;
use YesWiki\Identity\Exception\UserNameAlreadyUsedException;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\Service\UserOperationsService;
use YesWiki\Kernel\Service\StringUtilService;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(UserOperationsService::class, '__construct')]
#[CoversMethod(UserOperationsService::class, 'getFirstAdmin')]
#[CoversMethod(UserOperationsService::class, 'delete')]
#[CoversMethod(UserOperationsService::class, 'deleteGroupsWhereUserIsAlone')]
#[CoversMethod(UserOperationsService::class, 'create')]
#[CoversMethod(UserOperationsService::class, 'sanitizeName')]
class UserOperationsServiceTest extends YesWikiTestCase
{
    public const CHARS_FOR_EMAIL = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    public const CHARS_FOR_PASSWORD = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_';
    public const UPPER_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function testUserOperationsServiceExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(UserOperationsService::class));
        $this->assertTrue($wiki->services->has(UserManager::class));

        return $wiki;
    }

    #[Depends('testUserOperationsServiceExisting')]
    public function testGetFirstAdmin(YesWikiRuntime $wiki): string
    {
        $userOperationsService = $wiki->services->get(UserOperationsService::class);
        $firstAdmin = $userOperationsService->getFirstAdmin();
        $this->assertNotEmpty($firstAdmin);

        return $firstAdmin;
    }

    #[Depends('testUserOperationsServiceExisting')]
    #[Depends('testGetFirstAdmin')]
    #[DataProvider('dataProviderTestDelete')]
    public function testDelete(string $connexionMode, bool $expectedResult, YesWikiRuntime $wiki, string $firstAdmin)
    {
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userOperationsService = $wiki->services->get(UserOperationsService::class);
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
        $user = self::requireUser($userManager->getOneByName($name));

        switch ($connexionMode) {
            case '!@admins':
                $authenticationService->login($user);
                break;
            case '@admins':
                $adminUser = self::requireUser($userManager->getOneByName($firstAdmin));
                $authenticationService->login($adminUser);
                break;
            case '!+':
            default:
                $authenticationService->logout();
                break;
        }

        $exceptionThrown = false;
        try {
            $userOperationsService->delete($user);
        } catch (DeleteUserException $ex) {
            $exceptionThrown = true;
        }

        $userDeleted = $userManager->getOneByName($name);

        if (!empty($userDeleted)) {
            $userManager->delete($userDeleted);
        }
        $authenticationService->logout();

        if ($expectedResult) {
            $this->assertFalse($exceptionThrown);
            $this->assertNull($userDeleted);
        } else {
            $this->assertTrue($exceptionThrown);
            $this->assertInstanceOf(User::class, $userDeleted);
        }
    }

    public static function dataProviderTestDelete()
    {
        return [
            'not connected' => ['!+', false],
            'not admin' => ['!@admins', false],
            'admin not current user' => ['@admins', true],
        ];
    }

    public static function dataProviderTestCreate()
    {
        return [
            'email name all right' => ['newRandom', 'newRandom', [], false, false, false],
            'email with 5 chars ext' => ['newRandom', 'newRandom2', [], false, false, false],
            'name existing' => ['name of first user', 'newRandom', [], false, false, true],
            'empty name' => ['empty', 'newRandom', [], false, false, true],
            'email existing' => ['newRandom', 'email of first user', [], false, false, true],
            'empty email' => ['newRandom', 'empty', [], false, false, true],
        ];
    }

    #[Depends('testUserOperationsServiceExisting')]
    #[DataProvider('dataProviderTestCreate')]
    public function testCreate(
        string $name,
        string $email,
        array $newValues,
        bool $userNameExist,
        bool $emailExist,
        bool $otherException,
        YesWikiRuntime $wiki
    ) {
        $userOperationsService = $wiki->services->get(UserOperationsService::class);
        $userManager = $wiki->services->get(UserManager::class);

        $users = $userManager->getAll();
        $firstUser = $users[array_key_first($users)];
        if ($name == 'newRandom') {
            do {
                $name = trim(StringUtilService::generateRandomString(1, self::UPPER_CHARS)
                    . StringUtilService::generateRandomString(25, self::CHARS_FOR_PASSWORD));
            } while (!empty($userManager->getOneByName($name)));
        } elseif ($name == 'empty') {
            $name = '';
        } else {
            $name = $firstUser['name'];
        }
        if ($email == 'newRandom') {
            do {
                $email = strtolower(StringUtilService::generateRandomString(10, self::CHARS_FOR_EMAIL)) . '@example.com';
            } while (!empty($userManager->getOneByEmail($email)));
        } elseif ($email == 'newRandom2') {
            do {
                $email = strtolower(StringUtilService::generateRandomString(10, self::CHARS_FOR_EMAIL)) . '@xyz.earth';
            } while (!empty($userManager->getOneByEmail($email)));
        } elseif ($email == 'empty') {
            $email = '';
        } else {
            $email = $firstUser['email'];
        }
        $newValues['name'] = $name;
        $newValues['email'] = $email;
        $newValues['password'] = StringUtilService::generateRandomString(25, self::CHARS_FOR_PASSWORD);

        $exceptionThrown = false;
        $userNameAlreadyExist = false;
        $emailAlreadyExist = false;
        $exceptionMessage = '';
        $user = null;
        try {
            $userOperationsService->create($newValues);
            $user = $userManager->getOneByName($name);
        } catch (UserNameAlreadyUsedException $ex) {
            $userNameAlreadyExist = true;
        } catch (UserEmailAlreadyUsedException $ex) {
            $emailAlreadyExist = true;
        } catch (\Throwable $ex) {
            $exceptionThrown = true;
            $exceptionMessage = $ex->getMessage();
        }
        try {
            if (!empty($user)) {
                $userManager->delete($user);
            }
        } catch (\Throwable $th) {
        }

        if ($userNameExist) {
            $this->assertTrue($userNameAlreadyExist);
        } elseif ($emailExist) {
            $this->assertTrue($emailAlreadyExist);
        } elseif ($otherException) {
            $this->assertTrue($exceptionThrown);
        } else {
            $this->assertFalse($userNameAlreadyExist);
            $this->assertFalse($emailAlreadyExist);
            $this->assertEquals($exceptionMessage, '');
            $this->assertFalse($exceptionThrown);
            $this->assertInstanceOf(User::class, $user);
            $this->assertNotEmpty($user['name']);
            $this->assertEquals($user['name'], $name);
            $this->assertNotEmpty($user['email']);
            $this->assertEquals($user['email'], $email);
            foreach ([
                'changescount',
                'doubleclickedit',
                'motto',
                'revisioncount',
                'show_comments',
            ] as $propName) {
                if (isset($newValues[$propName])) {
                    $this->assertEquals($user[$propName], $newValues[$propName]);
                }
            }
        }
    }

    /**
     * Deleting a user who is the sole member of a non-admin group must automatically delete that group and then delete the user.
     */
    #[Depends('testUserOperationsServiceExisting')]
    #[Depends('testGetFirstAdmin')]
    public function testDeleteUserAloneInNonAdminGroupDeletesGroupToo(YesWikiRuntime $wiki, string $firstAdmin)
    {
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $groupOperationsService = $wiki->services->get(GroupOperationsService::class);
        $userOperationsService = $wiki->services->get(UserOperationsService::class);
        $userManager = $wiki->services->get(UserManager::class);

        do {
            $email = strtolower(StringUtilService::generateRandomString(10, self::CHARS_FOR_EMAIL)) . '@example.com';
        } while (!empty($userManager->getOneByEmail($email)));
        do {
            $name = trim(StringUtilService::generateRandomString(1, self::UPPER_CHARS)
                . StringUtilService::generateRandomString(25, self::CHARS_FOR_PASSWORD));
        } while (!empty($userManager->getOneByName($name)));
        $userManager->create($name, $email, StringUtilService::generateRandomString(25, self::CHARS_FOR_PASSWORD));
        $user = self::requireUser($userManager->getOneByName($name));

        do {
            $groupName = StringUtilService::generateRandomString(8, self::UPPER_CHARS);
        } while ($groupOperationsService->groupExists($groupName));
        $groupOperationsService->create($groupName, [$name]);

        $adminUser = self::requireUser($userManager->getOneByName($firstAdmin));
        $authenticationService->login($adminUser);

        $exceptionThrown = false;
        try {
            $userOperationsService->delete($user);
        } catch (DeleteUserException $ex) {
            $exceptionThrown = true;
        } finally {
            $authenticationService->logout();

            if (!empty($userManager->getOneByName($name))) {
                $userManager->delete($userManager->getOneByName($name));
            }
            if ($groupOperationsService->groupExists($groupName)) {
                $groupOperationsService->delete($groupName);
            }
        }

        $this->assertFalse($exceptionThrown, 'delete() should not throw when user is sole member of a non-admin group');
        $this->assertNull($userManager->getOneByName($name), 'User should have been deleted');
        $this->assertFalse($groupOperationsService->groupExists($groupName), 'Group should have been auto-deleted');
    }

    /**
     * Deleting a user who is the sole member of the admins group must still throw DeleteUserException to prevent admin lockout.
     */
    #[Depends('testUserOperationsServiceExisting')]
    #[Depends('testGetFirstAdmin')]
    public function testDeleteUserAloneInAdminsGroupThrows(YesWikiRuntime $wiki, string $firstAdmin)
    {
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userOperationsService = $wiki->services->get(UserOperationsService::class);
        $userManager = $wiki->services->get(UserManager::class);

        do {
            $email = strtolower(StringUtilService::generateRandomString(10, self::CHARS_FOR_EMAIL)) . '@example.com';
        } while (!empty($userManager->getOneByEmail($email)));
        do {
            $name = trim(StringUtilService::generateRandomString(1, self::UPPER_CHARS)
                . StringUtilService::generateRandomString(25, self::CHARS_FOR_PASSWORD));
        } while (!empty($userManager->getOneByName($name)));
        $userManager->create($name, $email, StringUtilService::generateRandomString(25, self::CHARS_FOR_PASSWORD));
        $targetUser = $userManager->getOneByName($name);

        $adminsAcl = $wiki->services->get(GroupOperationsService::class)->getMembersText(ADMIN_GROUP);
        $adminsAcl = array_unique(array_filter(array_map('trim', explode("\n", str_replace(["\r\n", "\r"], "\n", $adminsAcl)))));
        if (count($adminsAcl) !== 1) {
            $this->markTestSkipped('Cannot safely test: admins group has more than one member.');
        }

        $adminUser = self::requireUser($userManager->getOneByName($firstAdmin));

        $authenticationService->login($adminUser);

        $exceptionThrown = false;
        try {
            $userOperationsService->delete($adminUser);
        } catch (DeleteUserException $ex) {
            $exceptionThrown = true;
        } finally {
            $authenticationService->logout();
            if (!empty($userManager->getOneByName($name))) {
                $userManager->delete($userManager->getOneByName($name));
            }
        }

        $this->assertTrue($exceptionThrown, 'delete() should throw when user cannot be safely deleted');
    }

    public static function dataProviderTestSanitizeName()
    {
        return [
            'random string' => ['newRandom', '', 0, false],
            'empty string' => ['', '', 0, true],
            'not string' => [false, '', 0, true],
            'too long string' => ['random', '', 400, true],
            'too long short' => ['random', '', 2, true],
            'forbidden \\' => ['thirdplace', '\\', 10, true],
            'forbidden /' => ['thirdplace', '/', 10, true],
            'forbidden <' => ['thirdplace', '<', 10, true],
            'forbidden >' => ['thirdplace', '>', 10, true],
            'forbidden begin !' => ['begin', '!', 10, true],
            'forbidden begin #' => ['begin', '#', 10, true],
            'forbidden begin @' => ['begin', '@', 10, true],
            'contain @' => ['thirdplace', '@', 10, false],
        ];
    }

    #[Depends('testUserOperationsServiceExisting')]
    #[Depends('testCreate')]
    #[Depends('testDelete')]
    #[DataProvider('dataProviderTestSanitizeName')]
    public function testSanitizeName($name, string $char, int $length, bool $otherException, YesWikiRuntime $wiki)
    {
        $userOperationsService = $wiki->services->get(UserOperationsService::class);
        $userManager = $wiki->services->get(UserManager::class);
        switch ($name) {
            case 'newRandom':
                do {
                    $name = trim(StringUtilService::generateRandomString(1, self::UPPER_CHARS)
                        . StringUtilService::generateRandomString(25, self::CHARS_FOR_PASSWORD));
                } while (!empty($userManager->getOneByName($name)));
                break;
            case 'random':
                $name = StringUtilService::generateRandomString($length, self::CHARS_FOR_EMAIL);
                break;
            case 'thirdplace':
                $name = StringUtilService::generateRandomString(2, self::CHARS_FOR_EMAIL) . $char .
                    StringUtilService::generateRandomString($length - 2, self::CHARS_FOR_EMAIL);
                break;
            case 'begin':
                $name = $char . StringUtilService::generateRandomString($length, self::CHARS_FOR_EMAIL);
                break;
            default:
                break;
        }
        do {
            $email = strtolower(StringUtilService::generateRandomString(10, self::CHARS_FOR_EMAIL)) . '@example.com';
        } while (!empty($userManager->getOneByEmail($email)));
        $password = StringUtilService::generateRandomString(25, self::CHARS_FOR_PASSWORD);

        $exceptionThrown = false;
        $exceptionMessage = '';
        $user = null;
        try {
            $userOperationsService->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);
            $user = $userManager->getOneByName($name);
        } catch (\Throwable $ex) {
            $exceptionThrown = true;
            $exceptionMessage = $ex->getMessage();
        }
        try {
            if (!empty($user)) {
                $userManager->delete($user);
            }
        } catch (\Throwable $th) {
        }

        if ($otherException) {
            $this->assertTrue($exceptionThrown);
        } else {
            $this->assertFalse($exceptionThrown);
            $this->assertEquals($exceptionMessage, '');
            $this->assertInstanceOf(User::class, $user);
        }
    }

    /**
     * Setting a motto used to assign the sanitised value to `doubleclickedit` instead -- so it left the motto unsanitised, overwrote an unrelated preference, and threw outright when a submission carried a motto but no doubleclickedit (ticket 13).
     */
    #[Depends('testUserOperationsServiceExisting')]
    public function testUpdatingOnlyTheMottoTouchesOnlyTheMotto(YesWikiRuntime $wiki): void
    {
        $userOperationsService = $wiki->services->get(UserOperationsService::class);
        $userManager = $wiki->services->get(UserManager::class);

        $name = $this->freeUserName($userManager, 'MottoTest');
        $email = strtolower(StringUtilService::generateRandomString(10, self::CHARS_FOR_EMAIL)) . '@example.com';

        $user = $userOperationsService->create([
            'name' => $name,
            'email' => $email,
            'password' => StringUtilService::generateRandomString(25, self::CHARS_FOR_PASSWORD),
        ]);
        $this->assertInstanceOf(User::class, $user);

        try {
            $stored = $userManager->getOneByName($name);
            $this->assertNotNull($stored);
            $before = $stored['doubleclickedit'];
            $userOperationsService->update($user, ['motto' => 'ma devise']);

            $reloaded = $userManager->getOneByName($name);
            $this->assertNotNull($reloaded);
            $this->assertSame('ma devise', $reloaded['motto']);
            $this->assertSame($before, $reloaded['doubleclickedit'], 'an unrelated preference must not move');
        } finally {
            $leftover = $userManager->getOneByName($name);
            if ($leftover !== null) {
                $userManager->delete($leftover);
            }
        }
    }

    /** A user name nothing has taken yet. */
    private function freeUserName(UserManager $userManager, string $prefix): string
    {
        do {
            $candidate = $prefix . StringUtilService::generateRandomString(12, self::UPPER_CHARS);
        } while (!empty($userManager->getOneByName($candidate)));

        return $candidate;
    }
}
