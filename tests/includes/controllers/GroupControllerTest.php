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
     * @covers GroupController::getMembers
     */
    public function getMembersFromNotExistingGroup(GroupController $groupcontroller) {
        $wiki = $this->getWiki();
        $group_name = $wiki->generateRandomString(10);
        
        $this->expectException(GroupNameDoesNotExistException::class);
        $groupcontroller->getMembers($$group_name);
    }
}

