<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Core\Service\GroupManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(UserManager::class, 'groupsWhereIsMember')]
class UserGroupsTest extends YesWikiTestCase
{
    private string $suffix;
    private array $userNames = [];
    private array $groupNames = [];

    protected function setUp(): void
    {
        $this->suffix = bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $userManager = $this->getWiki()->services->get(UserManager::class);
        $groupManager = $this->getWiki()->services->get(GroupManager::class);
        foreach ($this->groupNames as $group) {
            if ($groupManager->groupExists($group)) {
                $groupManager->delete($group);
            }
        }
        foreach ($this->userNames as $name) {
            $user = $userManager->getOneByName($name);
            if ($user) {
                $userManager->delete($user);
            }
        }
    }

    public function testAUserIsNotListedInAGroupWhereOnlyALongerNameContainsTheirs(): void
    {
        $userManager = $this->getWiki()->services->get(UserManager::class);
        $short = $this->createUser('Bell' . $this->suffix);
        $this->createUser('Bell' . $this->suffix . 'aMartin');
        $this->createUser('Xbell' . $this->suffix);

        $own = $this->createGroup('own', [$short]);
        $this->createGroup('longer', ['Bell' . $this->suffix . 'aMartin']);
        $this->createGroup('otherCase', ['Xbell' . $this->suffix]);
        $both = $this->createGroup('both', ['Bell' . $this->suffix . 'aMartin', $short]);

        $groups = $userManager->groupsWhereIsMember($userManager->getOneByName($short));
        sort($groups);
        $expected = [$own, $both];
        sort($expected);

        $this->assertSame($expected, $groups);
    }

    private function createUser(string $name): string
    {
        $userManager = $this->getWiki()->services->get(UserManager::class);
        $userManager->create($name, strtolower($name) . '@example.com', bin2hex(random_bytes(12)));
        $this->userNames[] = $name;

        return $name;
    }

    private function createGroup(string $label, array $members): string
    {
        $name = 'TestGroup' . $label . $this->suffix;
        $this->getWiki()->services->get(GroupManager::class)->create($name, $members);
        $this->groupNames[] = $name;

        return $name;
    }
}
