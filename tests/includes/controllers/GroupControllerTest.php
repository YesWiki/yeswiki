<?php

namespace YesWiki\Test\Core\Controller;

use Throwable;
use YesWiki\Core\Controller\GroupController;
use YesWiki\Core\Controller\UserController;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;
use YesWiki\Core\Exception\InvalidGroupNameException;
use YesWiki\Core\Exception\GroupNameDoesNotExistException;
use YesWiki\Core\Exception\GroupNameAlreadyUsedException;
use YesWiki\Core\Exception\UserNameDoesNotExistException;
use YesWiki\Core\Exception\InvalidInputException;
use function PHPUnit\Framework\matches;

require_once 'tests/YesWikiTestCase.php';

class GroupControllerTest extends YesWikiTestCase
{
    public const INVALID_CHAR = '+-_*=.:,?';
    public const CHARS_FOR_GROUP = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    /**
     * @covers GroupController::__construct
     * @return GroupController
     */
    public function testGroupControllerExisting(): GroupController
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(GroupController::class));
        
        return $wiki->services->get(GroupController::class);
    }
    
    public function dataProviderTestCreate()
    {
        $wiki = $this->getWiki();
        $invalid_group_name = $wiki->generateRandomString(5, self::INVALID_CHAR).$wiki->generateRandomString(10);
        $valid_group_name = $wiki->generateRandomString(10, self::CHARS_FOR_GROUP);
        $new_valid_group = $wiki->generateRandomString(10, self::CHARS_FOR_GROUP);

        $userController = $wiki->services->get(UserController::class);
        $user_name = $wiki->generateRandomString(10);
        $userController->create(['name' => $user_name, 'email' => $valid_group_name.'@example.com', 'password' => $user_name]); 
        
        // groupname, error type, members
        return [
            'correct group' => [$valid_group_name,  0, [$user_name]],
            'Invalid group name' => [$invalid_group_name, 1, [$user_name]],
            'already exist group' => [$valid_group_name, 2 , [$user_name]],
            'user does not exist' => [$new_valid_group,3, [$invalid_group_name]],
            ];
    }
    
    /**
     * @depends testGroupControllerExisting
     * @covers GroupController::create
     * @dataProvider dataProviderTestCreate
     * 
     */
    public function testCreate(
        string $groupname,
        int  $result_type,
        array $members,
        GroupController $groupcontroller) {
        
            if ($result_type == 0 ) {
               $groupcontroller->create($groupname, $members);
               $this->assertTrue($groupcontroller->groupExists($groupname));
            } else if ($result_type == 1) {
                $this->expectException(InvalidGroupNameException::class);
                $groupcontroller->create($groupname, $members);
            } else if ($result_type == 2) {
                $this->expectException(GroupNameAlreadyUsedException::class);
                $groupcontroller->create($groupname, $members);
            } else {
                $this->expectException(UserNameDoesNotExistException::class);
                $groupcontroller->create($groupname, $members);
            }
    }

    
    /**
     * 
     * @depends testGroupControllerExisting
     * @depends testCreate
     * @covers GroupController::getMembers
     * @dataProvider dataProviderTestCreate
     */
    public function testGetMembers(string $groupname, int $result_type, array $members, GroupController $groupcontroller) {
            if ($result_type == 0 ) {
               $groupcontroller->create($groupname, $members);
               $groupcontroller->getMembers($groupname);
               $this->assertEquals($groupcontroller->getMembers($groupname), $members);
            } else if ($result_type == 1) {
                $this->expectException(GroupNameDoesNotExistException::class);
                $groupcontroller->getMembers($groupname);
            } else if ($result_type == 2) {
               $groupcontroller->getMembers($groupname);
               $this->assertEquals($groupcontroller->getMembers($groupname), $members);
            } else {
                $this->expectException(GroupNameDoesNotExistException::class);
                $groupcontroller->getMembers($groupname);
            }
    }

    /**
     * 
     * @depends testGroupControllerExisting
     * @depends testCreate
     * @covers GroupController::delete
     * @dataProvider dataProviderTestCreate
     */
    public function testDelete(string $groupname, int $result_type, array $members, GroupController $groupcontroller) {
            if ($result_type == 0 ) {
               $groupcontroller->create($groupname, $members);
               $groupcontroller->delete($groupname);
               $this->assertFalse($groupcontroller->groupExists($groupname));
            } else if ($result_type == 1) {
                $this->expectException(GroupNameDoesNotExistException::class);
                $groupcontroller->delete($groupname);
            } else if ($result_type == 2) {
               $groupcontroller->create($groupname, $members);
               $groupcontroller->delete($groupname);
               $this->assertFalse($groupcontroller->groupExists($groupname));
            } else {
                $this->expectException(GroupNameDoesNotExistException::class);
                $groupcontroller->delete($groupname);
            }
    }
    
    
    
    public function dataProviderTestAdd()
    {
        $wiki = $this->getWiki();
        $valid_group_name = $wiki->generateRandomString(10, self::CHARS_FOR_GROUP);
        $new_valid_group = $wiki->generateRandomString(10, self::CHARS_FOR_GROUP);
        $third_valid_group = $wiki->generateRandomString(10, self::CHARS_FOR_GROUP);
        $fourth_valid_group = $wiki->generateRandomString(10, self::CHARS_FOR_GROUP);
        $not_existing_group = $wiki->generateRandomString(10, self::CHARS_FOR_GROUP);

        $user_name = $wiki->generateRandomString(10);
        $user_name_1 = $wiki->generateRandomString(10);
        
        $userController = $wiki->services->get(UserController::class);
        $userController->create(['name' => $user_name, 'email' => $valid_group_name.'@example.com', 'password' => $user_name]); 
        $userController->create(['name' => $user_name_1, 'email' => $new_valid_group.'@example.com', 'password' => $user_name]); 
        
        $groupController = $wiki->services->get(GroupController::class);
        $groupController->create($valid_group_name, [$user_name_1]);
        $groupController->create($new_valid_group, [$user_name_1]);
        $groupController->create($third_valid_group, [$user_name_1,'@'.$valid_group_name]);
        $groupController->create($fourth_valid_group, [$user_name_1,'@'.$third_valid_group]);
        
        // groupname, error type, members
        return [
            'valid scenario' => [$valid_group_name,  0, [$user_name]],
            'valid group add' => [$valid_group_name, 0, ['@'.$new_valid_group]],
            'user does not exist' => [$valid_group_name, 1, [$new_valid_group]],
            'group does not exist' => [$not_existing_group, 2 , ['@'.$valid_group_name]],
            'included group does not exist' => [$valid_group_name, 2 , ['@'.$not_existing_group]],
            'recursive group' => [$valid_group_name,3, ['@'.$fourth_valid_group, $user_name]],
            ];
    }
    
    
    /**
     * 
     * @depends testGroupControllerExisting
     * @depends testCreate
     * @covers GroupController::add
     * @covers GroupController::update
     * @dataProvider dataProviderTestAdd
     */
    public function testAdd(string $groupname, int $result_type, array $members, GroupController $groupcontroller) {
            if ($result_type == 0 ) {
               $groupcontroller->add($groupname, $members);
               foreach ($members as $member) {
                $this->assertContains($member, $groupcontroller->getMembers($groupname));
               }
            } else if ($result_type == 1) {
                $this->expectException(UserNameDoesNotExistException::class);
                $groupcontroller->add($groupname, $members);
            } else if ($result_type == 2) {
                $this->expectException(GroupNameDoesNotExistException::class);
                $groupcontroller->add($groupname, $members);
            } else {
                $this->expectException(InvalidInputException::class);
                $groupcontroller->add($groupname, $members);
            }
    }
    
     public function dataProviderTestRemoveMembers()
    {
        $wiki = $this->getWiki();
        $valid_group_name = $wiki->generateRandomString(10, self::CHARS_FOR_GROUP);
        $new_valid_group = $wiki->generateRandomString(10, self::CHARS_FOR_GROUP);
        $not_existing_group = $wiki->generateRandomString(10, self::CHARS_FOR_GROUP);

        $user_name = $wiki->generateRandomString(10);
        $user_name_1 = $wiki->generateRandomString(10);
        
        $userController = $wiki->services->get(UserController::class);
        $userController->create(['name' => $user_name, 'email' => $valid_group_name.'@example.com', 'password' => $user_name]); 
        $userController->create(['name' => $user_name_1, 'email' => $new_valid_group.'@example.com', 'password' => $user_name]); 
        
        $groupController = $wiki->services->get(GroupController::class);
        $groupController->create($new_valid_group, [$user_name_1, $user_name]);
        $groupController->create($valid_group_name, [$user_name_1, $user_name, '@'.$new_valid_group]);

        // groupname, error type, members
        return [
            'remove one user' => [$new_valid_group,  0, [$user_name]],
            'remove one user and one group' => [$valid_group_name, 0, ['@'.$new_valid_group, $user_name_1]],
            'group does not exist' => [$not_existing_group, 2 , ['@'.$valid_group_name]],
            'remove not existing user' => [$valid_group_name,0, ['@'.$new_valid_group, $user_name]],
            ];
    }
    
    /**
     * 
     * @depends testGroupControllerExisting
     * @depends testCreate
     * @covers GroupController::removeMembers
     * @dataProvider dataProviderTestRemoveMembers
     */
    public function testRemoveMembers(string $groupname, int $result_type, array $members, GroupController $groupcontroller)
    {
         if ($result_type == 0 ) {
               $groupcontroller->remove($groupname, $members);
               foreach ($members as $member) {
                $this->assertNotContains($member, $groupcontroller->getMembers($groupname));
               }
            } else {
                $this->expectException(GroupNameDoesNotExistException::class);
                $groupcontroller->remove($groupname, $members);
        }
    }
}

