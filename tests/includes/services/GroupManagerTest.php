<?php

namespace YesWiki\Test\Core\Service;

use Throwable;
use YesWiki\Core\Exception\GroupEmailAlreadyUsedException;
use YesWiki\Core\Exception\GroupNameAlreadyUsedException;
use YesWiki\Core\Entity\Group;
use YesWiki\Core\Service\GroupManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class GroupManagerTest extends YesWikiTestCase
{
    
     public const CHARS_FOR_GROUP = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    /**
     * @covers GroupManager::__construct
     * @return GroupManager $groupManager
     */
    public function testGroupManagerExisting(): GroupManager
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(GroupManager::class));
        return $wiki->services->get(GroupManager::class);
    }
    

    /**
     * @depends testGroupManagerExisting
     * @cover GroupManager::create
     * @param GroupManager groupmanager
     */
    public function testCreate(GroupManager $groupManager) {
        $group_name = $wiki = $this->getWiki()->generateRandomString(10, self::CHARS_FOR_GROUP);
        $groupManager->create($group_name, []);
        $this-> assertTrue($groupManager->groupExists($group_name));
        return $group_name;
    }

    /**
     * @depends testGroupManagerExisting
     * @depends testCreate
     * @cover GroupManager::add
     * @param GroupManager groupmanager
     * @param string group_name
     */
    public function testaddMember(GroupManager $groupManager, string $group_name) {
        $user_name = $wiki = $this->getWiki()->generateRandomString(10, self::CHARS_FOR_GROUP);
        $groupManager->addMembers($group_name, array($user_name));
        $this-> assertContains($user_name, $groupManager->getMembers($group_name));
        $user_name = $wiki = $this->getWiki()->generateRandomString(10, self::CHARS_FOR_GROUP);
        $groupManager->addMembers($group_name, array($user_name));
        $this-> assertContains($user_name, $groupManager->getMembers($group_name));
        return $user_name;
    }
    
    /**
     * @depends testGroupManagerExisting
     * @depends testCreate
     * @depends testaddMember
     * @cover GroupManager::removeMembers
     * @param GroupManager groupmanager
     * @param string group_name
     */
    public function testDeleteMember(GroupManager $groupManager, string $group_name, string $user_name) {      
        $groupManager->removeMembers($group_name, array($user_name));
        $this->assertNotContains($user_name, $groupManager->getMembers($group_name));
    }
        
         /**
     * @depends testGroupManagerExisting
     * @cover GroupManager::updateMembers
     * @param GroupManager groupmanager
     * @param string group_name
     */
    public function testUpdateMember(GroupManager $groupManager) {   
        $group_name = $wiki = $this->getWiki()->generateRandomString(10, self::CHARS_FOR_GROUP);
        $users = array();
        for ($i=0; $i<5 ; $i++) {
            array_push($users,  $this->getWiki()->generateRandomString(10));
        }
        $groupManager->addMembers($group_name, $users);
        $this-> assertEquals($groupManager->getMembers($group_name),$users);
        $users = array();
        for ($i=0; $i<2 ; $i++) {
            array_push($users,  $this->getWiki()->generateRandomString(10));
        }
        $groupManager->updateMembers($group_name, $users);
        $this-> assertEquals($groupManager->getMembers($group_name), $users);

    }
}