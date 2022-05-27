<?php

namespace YesWiki\Test\Core\Service;

use Throwable;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class UserManagerTest extends YesWikiTestCase
{
    /**
     * @covers UserManager::__construct
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
     * @covers UserManager::getAll
     * @param UserManager $userManager
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
     * @covers UserManager::getOneByName
     * @param UserManager $userManager
     * @param array $users
     */
    public function testGetOneByName(UserManager $userManager, array $users)
    {
        $firstUser = $users[array_key_first($users)] ;
        $user = $userManager->getOneByName($firstUser['name']);
        $this->assertSame($user['name'], $firstUser['name']);
        $this->assertSame($user['password'], $firstUser['password']);
        $this->assertSame($user['email'], $firstUser['email']);
    }

    /**
     * @depends testUserManagerExisting
     * @depends testGetAll
     * @covers UserManager::getOneByEmail
     * @param UserManager $userManager
     * @param array $users
     */
    public function testGetOneByEmail(UserManager $userManager, array $users)
    {
        $firstUser = $users[array_key_first($users)] ;
        $user = $userManager->getOneByEmail($firstUser['email']);
        $this->assertSame($user['name'], $firstUser['name']);
        $this->assertSame($user['password'], $firstUser['password']);
        $this->assertSame($user['email'], $firstUser['email']);
    }

    /**
     * @depends testUserManagerExisting
     * @covers UserManager::create
     * @depends testGetOneByName
     * @depends testGetOneByEmail
     * @param UserManager $userManager
     * @return User $createdUser
     */
    public function testCreate(UserManager $userManager)
    {
        do {
            $email = strtolower($this->randomString(10)).'@example.com';
        } while (!empty($userManager->getOneByEmail($email)));
        do {
            $name= $this->randomString(1, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')
                .$this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');
        } while (!empty($userManager->getOneByName($name)));
        
        $password= $this->randomString(25, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -_');
        $exceptionThrown = false;
        try {
            $userManager->create($name, $email, $password);
            $user = $userManager->getOneByName($name);
        } catch (Throwable $th) {
            $exceptionThrown = true;
        }

        $this->assertFalse($exceptionThrown);
        $this->assertInstanceOf(User::class, $user);
        $this->assertNotEmpty($user['name']);
        $this->assertEquals($user['name'], $name);

        return $user;
    }
    
    /**
     * @depends testUserManagerExisting
     * @depends testCreate
     * @covers UserManager::delete
     * @param UserManager $userManager
     * @param User $createdUser
     */
    public function testDelete(UserManager $userManager, User $user)
    {
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
    
    /**
     * gives a random string with ascii characters
     * @param int $length
     * @param string $charset optional list of chars
     * @return string
     */
    private function randomString(
        int $length,
        string $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
    ): string {
        $output = "";
        $maxIndex = strlen($charset) -1;

        for ($i=0; $i < (max(1, $length)); $i++) {
            $output .= substr($charset, rand(0, $maxIndex), 1);
        }
        return $output;
    }
}
