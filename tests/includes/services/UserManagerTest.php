<?php

namespace YesWiki\Test\Core\Service;

use Throwable;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Exception\UserEmailAlreadyUsedException;
use YesWiki\Core\Exception\UserNameAlreadyUsedException;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class UserManagerTest extends YesWikiTestCase
{
    /**
     * @covers \UserManager::__construct
     *
     * @return UserManager $userManager
     */
    public function testUserManagerExisting(): UserManager
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(UserManager::class));

        return $wiki->services->get(UserManager::class);
    }

    /**
     * @depends testUserManagerExisting
     * @covers \UserManager::getAll
     *
     * @return array $users
     */
    public function testGetAll(UserManager $userManager): array
    {
        $users = $userManager->getAll();
        $this->assertTrue(is_array($users));
        $this->assertGreaterThan(0, count($users));

        return $users;
    }

    /**
     * @depends testUserManagerExisting
     * @depends testGetAll
     * @covers \UserManager::getOneByName
     */
    public function testGetOneByName(UserManager $userManager, array $users)
    {
        $firstUser = $users[array_key_first($users)];
        $user = $userManager->getOneByName($firstUser['name']);
        $this->assertSame($user['name'], $firstUser['name']);
        $this->assertSame($user['password'], $firstUser['password']);
        $this->assertSame($user['email'], $firstUser['email']);
    }

    /**
     * @depends testUserManagerExisting
     * @depends testGetAll
     * @covers \UserManager::getOneByEmail
     */
    public function testGetOneByEmail(UserManager $userManager, array $users)
    {
        $firstUser = $users[array_key_first($users)];
        $user = $userManager->getOneByEmail($firstUser['email']);
        $this->assertSame($user['name'], $firstUser['name']);
        $this->assertSame($user['password'], $firstUser['password']);
        $this->assertSame($user['email'], $firstUser['email']);
    }

    public function dataProviderTestCreate()
    {
        // name, email, UserNameExist, EmailExist, Other Exception
        return [
            'all right' => ['newRandom', 'newRandom', false, false, false],
            'email with 5 chars ext' => ['newRandom', 'newRandom2', false, false, false],
            'name existing' => ['name of first user', 'newRandom', true, false, false],
            'empty name' => ['empty', 'newRandom', false, false, true],
            'email existing' => ['newRandom', 'email of first user', false, true, false],
            'empty email' => ['newRandom', 'empty', false, false, true],
        ];
    }

    /**
     * @covers \UserManager::create
     * @depends testUserManagerExisting
     * @depends testGetOneByName
     * @depends testGetOneByEmail
     * @dataProvider dataProviderTestCreate
     */
    public function testCreate(
        string $name,
        string $email,
        bool $userNameExist,
        bool $emailExist,
        bool $otherException,
        UserManager $userManager
    ) {
        $users = $userManager->getAll();
        $firstUser = $users[array_key_first($users)];
        if ($name == 'newRandom') {
            do {
                $name = $this->randomString(1, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')
                    . $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');
            } while (!empty($userManager->getOneByName($name)));
        } elseif ($name == 'empty') {
            $name = '';
        } else {
            $name = $firstUser['name'];
        }
        if ($email == 'newRandom') {
            do {
                $email = strtolower($this->randomString(10)) . '@example.com';
            } while (!empty($userManager->getOneByEmail($email)));
        } elseif ($email == 'newRandom2') {
            do {
                $email = strtolower($this->randomString(10)) . '@xyz.earth';
            } while (!empty($userManager->getOneByEmail($email)));
        } elseif ($email == 'empty') {
            $email = '';
        } else {
            $email = $firstUser['email'];
        }

        $password = $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');
        $exceptionThrown = false;
        $userNameAlreadyExist = false;
        $emailAlreadyExist = false;
        try {
            $userManager->create($name, $email, $password);
            $user = $userManager->getOneByName($name);
        } catch (UserNameAlreadyUsedException $ex) {
            $userNameAlreadyExist = true;
        } catch (UserEmailAlreadyUsedException $ex) {
            $emailAlreadyExist = true;
        } catch (Throwable $ex) {
            $exceptionThrown = true;
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
            $this->assertFalse($exceptionThrown);
            $this->assertInstanceOf(User::class, $user);
            $this->assertNotEmpty($user['name']);
            $this->assertEquals($user['name'], $name);
            $this->assertNotEmpty($user['email']);
            $this->assertEquals($user['email'], $email);
        }
    }

    /**
     * @return User $createdUser
     */
    private function createRandomUser(UserManager $userManager)
    {
        do {
            $email = strtolower($this->randomString(10)) . '@example.com';
        } while (!empty($userManager->getOneByEmail($email)));
        do {
            $name = $this->randomString(1, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')
                . $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');
        } while (!empty($userManager->getOneByName($name)));

        $password = $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');
        $userManager->create($name, $email, $password);
        $user = $userManager->getOneByName($name);

        return $user;
    }

    /**
     * @depends testUserManagerExisting
     * @depends testCreate
     * @covers \UserManager::delete
     */
    public function testDelete(UserManager $userManager)
    {
        $user = $this->createRandomUser($userManager);
        $exceptionThrown = false;
        try {
            $userManager->delete($user);
            $createdUser = $userManager->getOneByName($user['name']);
        } catch (Throwable $th) {
            $exceptionThrown = true;
        }

        $this->assertFalse($exceptionThrown);
        $this->assertNotInstanceOf(User::class, $createdUser);
        $this->assertNull($createdUser);
    }

    public function dataProviderTestUpdate()
    {
        // newValues, email, UserNameExist, EmailExist, Other Exception
        return [
            'motto update ok' => [[
                'motto' => $this->randomString(50),
            ], '', false, false, false],
            'revisioncount update ok' => [[
                'revisioncount' => rand(1, 60),
            ], '', false, false, false],
            'revisioncount update to 0 ok' => [[
                'revisioncount' => 0,
            ], '', false, false, false],
            'changescount update ok' => [[
                'changescount' => rand(1, 60),
            ], '', false, false, false],
            'changescount update to 0 ok' => [[
                'changescount' => 0,
            ], '', false, false, false],
            'show_comments update to Y ok' => [[
                'show_comments' => 'Y',
            ], '', false, false, false],
            'show_comments update to N ok' => [[
                'show_comments' => 'N',
            ], '', false, false, false],
            'doubleclickedit update to Y ok' => [[
                'doubleclickedit' => 'Y',
            ], '', false, false, false],
            'doubleclickedit update to N ok' => [[
                'doubleclickedit' => 'N',
            ], '', false, false, false],
            'email update ok' => [[], 'newRandom', false, false, false],
            'email update with 5 chars domain ext ok' => [[], 'newRandom2', false, false, false],
            'email update empty' => [[], 'empty', false, false, true],
            'email update existing' => [[], 'firstUser', false, true, false],
            'update all ok' => [[
                'motto' => $this->randomString(50),
                'revisioncount' => rand(1, 60),
                'changescount' => rand(1, 60),
                'show_comments' => 'Y',
                'doubleclickedit' => 'Y',
            ], 'newRandom', false, false, false],
        ];
    }

    /**
     * @depends testUserManagerExisting
     * @depends testCreate
     * @depends testDelete
     * @dataProvider dataProviderTestUpdate
     * @covers \UserManager::update
     */
    public function testUpdate(
        array $newValues,
        string $email,
        bool $userNameExist,
        bool $emailExist,
        bool $otherException,
        UserManager $userManager
    ) {
        $users = $userManager->getAll();
        $firstUser = $users[array_key_first($users)];

        $user = $this->createRandomUser($userManager);
        if (!empty($email)) {
            if ($email == 'newRandom') {
                do {
                    $email = strtolower($this->randomString(10)) . '@example.com';
                } while (!empty($userManager->getOneByEmail($email)));
            } elseif ($email == 'newRandom2') {
                do {
                    $email = strtolower($this->randomString(10)) . '@xyz.earth';
                } while (!empty($userManager->getOneByEmail($email)));
            } elseif ($email == 'empty') {
                $email = '';
            } else {
                $email = $firstUser['email'];
            }
            $newValues['email'] = $email;
        }

        $exceptionThrown = false;
        $userNameAlreadyExist = false;
        $emailAlreadyExist = false;
        $exceptionMessage = '';
        try {
            $userManager->update($user, $newValues);
            $user = $userManager->getOneByName($user['name']);
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
            $this->assertNotEmpty($user['email']);
            foreach ([
                'changescount',
                'doubleclickedit',
                'email',
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
     * gives a random string with ascii characters.
     *
     * @param string $charset optional list of chars
     */
    private function randomString(
        int $length,
        string $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
    ): string {
        $output = '';
        $maxIndex = strlen($charset) - 1;

        for ($i = 0; $i < (max(1, $length)); $i++) {
            $output .= substr($charset, rand(0, $maxIndex), 1);
        }

        return $output;
    }
}
