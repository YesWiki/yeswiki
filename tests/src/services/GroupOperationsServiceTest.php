<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Identity\Exception\GroupNameAlreadyUsedException;
use YesWiki\Identity\Exception\GroupNameDoesNotExistException;
use YesWiki\Identity\Exception\InvalidGroupNameException;
use YesWiki\Identity\Exception\UserNameDoesNotExistException;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\Service\UserOperationsService;
use YesWiki\Kernel\Exception\InvalidInputException;
use YesWiki\Kernel\Service\StringUtilService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(GroupOperationsService::class, '__construct')]
#[CoversMethod(GroupOperationsService::class, 'create')]
#[CoversMethod(GroupOperationsService::class, 'getMembers')]
#[CoversMethod(GroupOperationsService::class, 'delete')]
#[CoversMethod(GroupOperationsService::class, 'add')]
#[CoversMethod(GroupOperationsService::class, 'update')]
#[CoversMethod(GroupOperationsService::class, 'removeMembers')]
class GroupOperationsServiceTest extends YesWikiTestCase
{
    public const INVALID_CHAR = '+-_*=.:,?';
    public const CHARS_FOR_GROUP = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    /**
     * Every group and user this class invents, so that it can take them away again.
     *
     * @var list<string>
     */
    private static array $groupsToClean = [];

    /**
     * @var list<string>
     */
    private static array $usersToClean = [];

    /** A group name this class owns, and will delete when it is done with it. */
    private static function groupName(): string
    {
        self::$groupsToClean[] = $name = StringUtilService::generateRandomString(10, self::CHARS_FOR_GROUP);

        return $name;
    }

    /** Same, for the derived names the tests build out of a fixture name. */
    private static function alsoClean(string $groupName): string
    {
        self::$groupsToClean[] = $groupName;

        return $groupName;
    }

    private static function createUser(string $emailPrefix): string
    {
        self::$usersToClean[] = $name = StringUtilService::generateRandomString(10, self::CHARS_FOR_GROUP);
        static::getWiki()->services->get(UserOperationsService::class)->create([
            'name' => $name,
            'email' => $emailPrefix . '@example.com',
            'password' => $name,
        ]);

        return $name;
    }

    public static function tearDownAfterClass(): void
    {
        $services = static::getWiki()->services;
        $groups = $services->get(GroupOperationsService::class);
        foreach (array_unique(self::$groupsToClean) as $group) {
            try {
                $groups->delete($group);
            } catch (\Throwable) {
            }
        }
        $userManager = $services->get(UserManager::class);
        $userOperations = $services->get(UserOperationsService::class);
        foreach (array_unique(self::$usersToClean) as $userName) {
            try {
                $user = $userManager->getOneByName($userName);
                if ($user) {
                    $userOperations->delete($user);
                }
            } catch (\Throwable) {
            }
        }
        self::$groupsToClean = [];
        self::$usersToClean = [];

        parent::tearDownAfterClass();
    }

    public function testGroupOperationsServiceExisting(): GroupOperationsService
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(GroupOperationsService::class));

        return $wiki->services->get(GroupOperationsService::class);
    }

    public static function dataProviderTestCreate()
    {
        $wiki = static::getWiki();
        $invalid_group_name = StringUtilService::generateRandomString(5, self::INVALID_CHAR) . StringUtilService::generateRandomString(10);
        $valid_group_name = self::groupName();
        $new_valid_group = self::groupName();

        $user_name = self::createUser($valid_group_name);

        self::alsoClean($user_name . 'group');

        return [
            'correct group' => [$valid_group_name,  0, [$user_name]],
            'Invalid group name' => [$invalid_group_name, 1, [$user_name]],
            'already exist group' => [$valid_group_name, 2, [$user_name]],
            'user does not exist' => [$new_valid_group, 3, [$invalid_group_name]],
        ];
    }

    #[Depends('testGroupOperationsServiceExisting')]
    #[DataProvider('dataProviderTestCreate')]
    public function testCreate(
        string $groupname,
        int $result_type,
        array $members,
        GroupOperationsService $groupcontroller
    ) {
        if ($result_type == 0) {
            $groupcontroller->create($groupname, $members);
            $this->assertTrue($groupcontroller->groupExists($groupname));
        } elseif ($result_type == 1) {
            $this->expectException(InvalidGroupNameException::class);
            $groupcontroller->create($groupname, $members);
        } elseif ($result_type == 2) {
            $this->expectException(GroupNameAlreadyUsedException::class);
            $groupcontroller->create($groupname, $members);
        } else {
            $this->expectException(UserNameDoesNotExistException::class);
            $groupcontroller->create($groupname, $members);
        }
    }

    #[Depends('testGroupOperationsServiceExisting')]
    #[Depends('testCreate')]
    #[DataProvider('dataProviderTestCreate')]
    public function testGetMembers(string $groupname, int $result_type, array $members, GroupOperationsService $groupcontroller)
    {
        if ($result_type == 0) {
            $groupcontroller->create($groupname, $members);
            $groupcontroller->getMembers($groupname);
            $this->assertEquals($groupcontroller->getMembers($groupname), $members);
        } elseif ($result_type == 1) {
            $this->expectException(GroupNameDoesNotExistException::class);
            $groupcontroller->getMembers($groupname);
        } elseif ($result_type == 2) {
            $groupcontroller->getMembers($groupname);
            $this->assertEquals($groupcontroller->getMembers($groupname), $members);
        } else {
            $this->expectException(GroupNameDoesNotExistException::class);
            $groupcontroller->getMembers($groupname);
        }
    }

    #[Depends('testGroupOperationsServiceExisting')]
    #[Depends('testCreate')]
    #[DataProvider('dataProviderTestCreate')]
    public function testDelete(string $groupname, int $result_type, array $members, GroupOperationsService $groupcontroller)
    {
        if ($result_type == 0) {
            $groupcontroller->create($groupname, []);
            $groupcontroller->create($members[0] . 'group', ['@' . $groupname]);
            $groupcontroller->delete($groupname);
            $this->assertFalse($groupcontroller->groupExists($groupname));
            $this->assertNotContains($groupname, $groupcontroller->getMembers($members[0] . 'group'));
        } elseif ($result_type == 1) {
            $this->expectException(GroupNameDoesNotExistException::class);
            $groupcontroller->delete($groupname);
        } elseif ($result_type == 2) {
            $groupcontroller->create($groupname, $members);
            $groupcontroller->delete($groupname);
            $this->assertFalse($groupcontroller->groupExists($groupname));
        } else {
            $this->expectException(GroupNameDoesNotExistException::class);
            $groupcontroller->delete($groupname);
        }
    }

    public static function dataProviderTestAdd()
    {
        $wiki = static::getWiki();
        $valid_group_name = self::groupName();
        $new_valid_group = self::groupName();
        $third_valid_group = self::groupName();
        $fourth_valid_group = self::groupName();

        $not_existing_group = StringUtilService::generateRandomString(10, self::CHARS_FOR_GROUP);

        $user_name = self::createUser($valid_group_name);
        $user_name_1 = self::createUser($new_valid_group);

        $groupOperationsService = $wiki->services->get(GroupOperationsService::class);
        $groupOperationsService->create($valid_group_name, [$user_name_1]);
        $groupOperationsService->create($new_valid_group, [$user_name_1]);
        $groupOperationsService->create($third_valid_group, [$user_name_1, '@' . $valid_group_name]);
        $groupOperationsService->create($fourth_valid_group, [$user_name_1, '@' . $third_valid_group]);

        return [
            'valid scenario' => [$valid_group_name,  0, [$user_name]],
            'valid group add' => [$valid_group_name, 0, ['@' . $new_valid_group]],
            'user does not exist' => [$valid_group_name, 1, [$new_valid_group]],
            'group does not exist' => [$not_existing_group, 2, ['@' . $valid_group_name]],
            'included group does not exist' => [$valid_group_name, 2, ['@' . $not_existing_group]],
            'recursive group' => [$valid_group_name, 3, ['@' . $fourth_valid_group, $user_name]],
        ];
    }

    #[Depends('testGroupOperationsServiceExisting')]
    #[Depends('testCreate')]
    #[DataProvider('dataProviderTestAdd')]
    public function testAdd(string $groupname, int $result_type, array $members, GroupOperationsService $groupcontroller)
    {
        if ($result_type == 0) {
            $groupcontroller->add($groupname, $members);
            foreach ($members as $member) {
                $this->assertContains($member, $groupcontroller->getMembers($groupname));
            }
        } elseif ($result_type == 1) {
            $this->expectException(UserNameDoesNotExistException::class);
            $groupcontroller->add($groupname, $members);
        } elseif ($result_type == 2) {
            $this->expectException(GroupNameDoesNotExistException::class);
            $groupcontroller->add($groupname, $members);
        } else {
            $this->expectException(InvalidInputException::class);
            $groupcontroller->add($groupname, $members);
        }
    }

    public static function dataProviderTestRemoveMembers()
    {
        $wiki = static::getWiki();
        $valid_group_name = self::groupName();
        $new_valid_group = self::groupName();

        $not_existing_group = StringUtilService::generateRandomString(10, self::CHARS_FOR_GROUP);

        $user_name = self::createUser($valid_group_name);
        $user_name_1 = self::createUser($new_valid_group);

        $groupOperationsService = $wiki->services->get(GroupOperationsService::class);
        $groupOperationsService->create($new_valid_group, [$user_name_1, $user_name]);
        $groupOperationsService->create($valid_group_name, [$user_name_1, $user_name, '@' . $new_valid_group]);

        return [
            'remove one user' => [$new_valid_group,  0, [$user_name]],
            'remove one user and one group' => [$valid_group_name, 0, ['@' . $new_valid_group, $user_name_1]],
            'group does not exist' => [$not_existing_group, 2, ['@' . $valid_group_name]],
            'remove not existing user' => [$valid_group_name, 0, ['@' . $new_valid_group, $user_name]],
        ];
    }

    #[Depends('testGroupOperationsServiceExisting')]
    #[Depends('testCreate')]
    #[DataProvider('dataProviderTestRemoveMembers')]
    public function testRemoveMembers(string $groupname, int $result_type, array $members, GroupOperationsService $groupcontroller)
    {
        if ($result_type == 0) {
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
