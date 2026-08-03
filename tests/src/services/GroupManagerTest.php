<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Kernel\Service\StringUtilService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(GroupManager::class, '__construct')]
class GroupManagerTest extends YesWikiTestCase
{
    public const CHARS_FOR_GROUP = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    public function testGroupManagerExisting(): GroupManager
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(GroupManager::class));

        return $wiki->services->get(GroupManager::class);
    }

    /**
     * Every group this class creates, deleted again in tearDownAfterClass().
     *
     * @var list<string>
     */
    private static array $groupsToClean = [];

    /** A group name this class owns, and takes away again when it is done. */
    private static function groupName(): string
    {
        self::$groupsToClean[] = $name = StringUtilService::generateRandomString(10, self::CHARS_FOR_GROUP);

        return $name;
    }

    public static function tearDownAfterClass(): void
    {
        $groupManager = static::getWiki()->services->get(GroupManager::class);
        foreach (array_unique(self::$groupsToClean) as $group) {
            try {
                $groupManager->delete($group);
            } catch (\Throwable) {
            }
        }
        self::$groupsToClean = [];

        parent::tearDownAfterClass();
    }

    #[Depends('testGroupManagerExisting')]
    public function testCreate(GroupManager $groupManager)
    {
        $group_name = self::groupName();
        $groupManager->create($group_name, []);
        $this->assertTrue($groupManager->groupExists($group_name));

        return $group_name;
    }

    #[Depends('testGroupManagerExisting')]
    #[Depends('testCreate')]
    public function testaddMember(GroupManager $groupManager, string $group_name)
    {
        $user_name = $wiki = StringUtilService::generateRandomString(10, self::CHARS_FOR_GROUP);
        $groupManager->addMembers($group_name, [$user_name]);
        $this->assertContains($user_name, $groupManager->getMembers($group_name));
        $user_name = $wiki = StringUtilService::generateRandomString(10, self::CHARS_FOR_GROUP);
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
        $group_name = self::groupName();
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            array_push($users, StringUtilService::generateRandomString(10));
        }
        $groupManager->addMembers($group_name, $users);
        $this->assertEquals($groupManager->getMembers($group_name), $users);
        $users = [];
        for ($i = 0; $i < 2; $i++) {
            array_push($users, StringUtilService::generateRandomString(10));
        }
        $groupManager->updateMembers($group_name, $users);
        $this->assertEquals($groupManager->getMembers($group_name), $users);
    }
}
