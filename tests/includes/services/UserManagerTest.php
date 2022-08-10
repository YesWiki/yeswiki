<?php

namespace YesWiki\Test\Core\Service;

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
}
