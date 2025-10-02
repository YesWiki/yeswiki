<?php

namespace YesWiki\Test\Core\Controller;

use Throwable;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Controller\UserController;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Exception\DeleteUserException;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

class UserControllerTest extends YesWikiTestCase
{
    public const CHARS_FOR_EMAIL = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    public const CHARS_FOR_PASSWORD = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_';
    public const UPPER_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * @covers \UserController::__construct
     *
     * @return Wiki $wiki
     */
    public function testUserControllerExisting(): Wiki
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(UserController::class));
        $this->assertTrue($wiki->services->has(UserManager::class));

        return $wiki;
    }

    /**
     * @depends testUserControllerExisting
     * @covers \UserController::getFirstAdmin
     *
     * @return string $firstAdmin
     */
    public function testGetFirstAdmin(Wiki $wiki): string
    {
        $userController = $wiki->services->get(UserController::class);
        $firstAdmin = $userController->getFirstAdmin();
        $this->assertNotEmpty($firstAdmin);

        return $firstAdmin;
    }

    /**
     * @depends testUserControllerExisting
     * @depends testGetFirstAdmin
     * @covers \UserController::delete
     * @dataProvider dataProviderTestDelete
     */
    public function testDelete(string $connexionMode, bool $expectedResult, Wiki $wiki, string $firstAdmin)
    {
        $authController = $wiki->services->get(AuthController::class);
        $userController = $wiki->services->get(UserController::class);
        $userManager = $wiki->services->get(UserManager::class);

        // create a user
        do {
            $email = strtolower($wiki->generateRandomString(10, self::CHARS_FOR_EMAIL)) . '@example.com';
        } while (!empty($userManager->getOneByEmail($email)));
        do {
            $name = $wiki->generateRandomString(1, self::UPPER_CHARS)
                . $wiki->generateRandomString(25, self::CHARS_FOR_PASSWORD);
        } while (!empty($userManager->getOneByName($name)));

        $password = $wiki->generateRandomString(25, self::CHARS_FOR_PASSWORD);

        $userManager->create($name, $email, $password);
        $user = $userManager->getOneByName($name);

        switch ($connexionMode) {
            case '!@admins':
                $authController->login($user);
                break;
                // case '%':
                // not currently covered
                //     $authController->login($user);
                //     break;
            case '@admins':
                $adminUser = $userManager->getOneByName($firstAdmin);
                $authController->login($adminUser);
                break;
            case '!+':
            default:
                $authController->logout();
                break;
        }

        $exceptionThrown = false;
        try {
            $userController->delete($user);
        } catch (DeleteUserException $ex) {
            $exceptionThrown = true;
        }

        $userDeleted = $userManager->getOneByName($name);

        // delete it after call to UserController::delete
        if (!empty($userDeleted)) {
            $userManager->delete($userDeleted);
        }
        $authController->logout();

        // check tests
        if ($expectedResult) {
            $this->assertFalse($exceptionThrown);
            $this->assertNull($userDeleted);
        } else {
            $this->assertTrue($exceptionThrown);
            $this->assertInstanceOf(User::class, $userDeleted);
        }
    }

    public function dataProviderTestDelete()
    {
        // mode , mode, expected result
        return [
            'not connected' => ['!+', false],
            'not admin' => ['!@admins', false],
            'admin not current user' => ['@admins', true],
            // 'admin current user' => ['%',false], // not currently covered
        ];
    }

    public function dataProviderTestCreate()
    {
        // name, email, newValues, UserNameExist, EmailExist, Other Exception
        return [
            'email name all right' => ['newRandom', 'newRandom', [], false, false, false],
            'email with 5 chars ext' => ['newRandom', 'newRandom2', [], false, false, false],
            'name existing' => ['name of first user', 'newRandom', [], false, false, true],
            'empty name' => ['empty', 'newRandom', [], false, false, true],
            'email existing' => ['newRandom', 'email of first user', [], false, false, true],
            'empty email' => ['newRandom', 'empty', [], false, false, true],
        ];
    }

    /**
     * @depends testUserControllerExisting
     * @covers \UserController::create
     * @dataProvider dataProviderTestCreate
     */
    public function testCreate(
        string $name,
        string $email,
        array $newValues,
        bool $userNameExist,
        bool $emailExist,
        bool $otherException,
        Wiki $wiki
    ) {
        $userController = $wiki->services->get(UserController::class);
        $userManager = $wiki->services->get(UserManager::class);

        $users = $userManager->getAll();
        $firstUser = $users[array_key_first($users)];
        if ($name == 'newRandom') {
            do {
                $name = $wiki->generateRandomString(1, self::UPPER_CHARS)
                    . $wiki->generateRandomString(25, self::CHARS_FOR_PASSWORD);
            } while (!empty($userManager->getOneByName($name)));
        } elseif ($name == 'empty') {
            $name = '';
        } else {
            $name = $firstUser['name'];
        }
        if ($email == 'newRandom') {
            do {
                $email = strtolower($wiki->generateRandomString(10, self::CHARS_FOR_EMAIL)) . '@example.com';
            } while (!empty($userManager->getOneByEmail($email)));
        } elseif ($email == 'newRandom2') {
            do {
                $email = strtolower($wiki->generateRandomString(10, self::CHARS_FOR_EMAIL)) . '@xyz.earth';
            } while (!empty($userManager->getOneByEmail($email)));
        } elseif ($email == 'empty') {
            $email = '';
        } else {
            $email = $firstUser['email'];
        }
        $newValues['name'] = $name;
        $newValues['email'] = $email;
        $newValues['password'] = $wiki->generateRandomString(25, self::CHARS_FOR_PASSWORD);

        $exceptionThrown = false;
        $userNameAlreadyExist = false;
        $emailAlreadyExist = false;
        $exceptionMessage = '';
        try {
            $userController->create($newValues);
            $user = $userManager->getOneByName($name);
        } catch (UserNameAlreadyUsedException $ex) {
            $userNameAlreadyExist = true;
        } catch (UserEmailAlreadyUsedException $ex) {
            $emailAlreadyExist = true;
        } catch (Throwable $ex) {
            $exceptionThrown = true;
            $exceptionMessage = $ex->getMessage();
        }
        try {
            if (!empty($user)) {
                $userManager->delete($user);
            }
        } catch (Throwable $th) {
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

    public function dataProviderTestSanitizeName()
    {
        // name,char,length,Other Exception
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

    /**
     * @depends testUserControllerExisting
     * @depends testCreate
     * @depends testDelete
     * @covers \UserController::sanitizeName
     * @dataProvider dataProviderTestSanitizeName
     *
     * @param mixed $name
     */
    public function testSanitizeName($name, string $char, int $length, bool $otherException, Wiki $wiki)
    {
        $userController = $wiki->services->get(UserController::class);
        $userManager = $wiki->services->get(UserManager::class);
        switch ($name) {
            case 'newRandom':
                do {
                    $name = $wiki->generateRandomString(1, self::UPPER_CHARS)
                        . $wiki->generateRandomString(25, self::CHARS_FOR_PASSWORD);
                } while (!empty($userManager->getOneByName($name)));
                break;
            case 'random':
                $name = $wiki->generateRandomString($length, self::CHARS_FOR_EMAIL);
                break;
            case 'thirdplace':
                $name = $wiki->generateRandomString(2, self::CHARS_FOR_EMAIL) . $char .
                    $wiki->generateRandomString($length - 2, self::CHARS_FOR_EMAIL);
                break;
            case 'begin':
                $name = $char . $wiki->generateRandomString($length, self::CHARS_FOR_EMAIL);
                break;
            default:
                break;
        }
        do {
            $email = strtolower($wiki->generateRandomString(10, self::CHARS_FOR_EMAIL)) . '@example.com';
        } while (!empty($userManager->getOneByEmail($email)));
        $password = $wiki->generateRandomString(25, self::CHARS_FOR_PASSWORD);

        $exceptionThrown = false;
        $exceptionMessage = '';
        try {
            $userController->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);
            $user = $userManager->getOneByName($name);
        } catch (Throwable $ex) {
            $exceptionThrown = true;
            $exceptionMessage = $ex->getMessage();
        }
        try {
            if (!empty($user)) {
                $userManager->delete($user);
            }
        } catch (Throwable $th) {
        }

        if ($otherException) {
            $this->assertTrue($exceptionThrown);
        } else {
            $this->assertFalse($exceptionThrown);
            $this->assertEquals($exceptionMessage, '');
            $this->assertInstanceOf(User::class, $user);
        }
    }
}
