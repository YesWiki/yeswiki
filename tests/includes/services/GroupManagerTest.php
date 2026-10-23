<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Core\Service\GroupManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(GroupManager::class, '__construct')]
class GroupManagerTest extends YesWikiTestCase
{
    public const CHARS_FOR_GROUP = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    private static array $groupNames = [];

    public static function tearDownAfterClass(): void
    {
        $groupManager = static::getWiki()->services->get(GroupManager::class);
        foreach (self::$groupNames as $group) {
            if ($groupManager->groupExists($group)) {
                $groupManager->delete($group);
            }
        }
        self::$groupNames = [];
    }

    public function testGroupManagerExisting(): GroupManager
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(GroupManager::class));

        return $wiki->services->get(GroupManager::class);
    }

    #[Depends('testGroupManagerExisting')]
    public function testCreate(GroupManager $groupManager)
    {
        $group_name = $this->getWiki()->generateRandomString(10, self::CHARS_FOR_GROUP);
        self::$groupNames[] = $group_name;
        $groupManager->create($group_name, []);
        $this->assertTrue($groupManager->groupExists($group_name));

        return $group_name;
    }

    #[Depends('testGroupManagerExisting')]
    #[Depends('testCreate')]
    public function testaddMember(GroupManager $groupManager, string $group_name)
    {
        $user_name = $wiki = $this->getWiki()->generateRandomString(10, self::CHARS_FOR_GROUP);
        $groupManager->addMembers($group_name, [$user_name]);
        $this->assertContains($user_name, $groupManager->getMembers($group_name));
        $user_name = $wiki = $this->getWiki()->generateRandomString(10, self::CHARS_FOR_GROUP);
        $groupManager->addMembers($group_name, [$user_name]);
        $this->assertContains($user_name, $groupManager->getMembers($group_name));

        return $user_name;
    }

    #[Depends('testGroupManagerExisting')]
    #[Depends('testCreate')]
    #[Depends('testaddMember')]
    public function testDeleteMember(GroupManager $groupManager, string $group_name, string $user_name)
    {
        $groupManager->removeMembers($group_name, [$user_name]);
        $this->assertNotContains($user_name, $groupManager->getMembers($group_name));
    }

    #[Depends('testGroupManagerExisting')]
    public function testUpdateMember(GroupManager $groupManager)
    {
        $group_name = $this->getWiki()->generateRandomString(10, self::CHARS_FOR_GROUP);
        self::$groupNames[] = $group_name;
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            array_push($users, $this->getWiki()->generateRandomString(10));
        }
        $groupManager->addMembers($group_name, $users);
        $this->assertEquals($groupManager->getMembers($group_name), $users);
        $users = [];
        for ($i = 0; $i < 2; $i++) {
            array_push($users, $this->getWiki()->generateRandomString(10));
        }
        $groupManager->updateMembers($group_name, $users);
        $this->assertEquals($groupManager->getMembers($group_name), $users);
    }
}
