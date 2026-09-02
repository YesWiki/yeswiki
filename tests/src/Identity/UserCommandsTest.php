<?php

namespace YesWiki\Test\Identity;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use YesWiki\Identity\Command\UserAddToGroupCommand;
use YesWiki\Identity\Command\UserCreateCommand;
use YesWiki\Identity\Command\UserDeleteCommand;
use YesWiki\Identity\Command\UserListCommand;
use YesWiki\Identity\Command\UserRemoveFromGroupCommand;
use YesWiki\Identity\Command\UserUpdateCommand;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The `user:*` console commands, run against the wiki the way `./yeswicli` runs them. */
class UserCommandsTest extends YesWikiTestCase
{
    private const NAME = 'UserCommandsTestAccount';
    private const GROUP = 'usercommandstestgroup';

    protected function tearDown(): void
    {
        $wiki = self::getWiki();
        $groups = $wiki->services->get(GroupOperationsService::class);
        if ($groups->groupExists(self::GROUP)) {
            $groups->delete(self::GROUP);
        }
        $user = $wiki->services->get(UserManager::class)->getOneByName(self::NAME);
        if ($user !== null) {
            $wiki->services->get(UserManager::class)->delete($user);
        }
        parent::tearDown();
    }

    private function run_(string $class, array $input): CommandTester
    {
        $application = new Application();
        $application->add(new $class(self::getWiki()->services));
        $tester = new CommandTester($application->find($input['command']));
        $tester->execute($input, ['interactive' => false]);

        return $tester;
    }

    public function testAnAccountIsCreatedUpdatedGroupedAndDeleted(): void
    {
        $users = self::getWiki()->services->get(UserManager::class);
        $groups = self::getWiki()->services->get(GroupOperationsService::class);

        $created = $this->run_(UserCreateCommand::class, [
            'command' => 'user:create',
            'name' => self::NAME,
            'email' => 'user-commands@xyz.earth',
            '--password' => 'Passw0rd!123',
            '--group' => [self::GROUP],
        ]);
        $this->assertSame(0, $created->getStatusCode(), $created->getDisplay());
        $user = $users->getOneByName(self::NAME);
        $this->assertNotNull($user);
        $this->assertContains(self::NAME, $groups->getMembers(self::GROUP), 'the group was created with the account in it');

        $listed = $this->run_(UserListCommand::class, ['command' => 'user:list']);
        $this->assertStringContainsString(self::NAME, $listed->getDisplay());
        $this->assertStringContainsString(self::GROUP, $listed->getDisplay());

        $updated = $this->run_(UserUpdateCommand::class, [
            'command' => 'user:update',
            'name' => self::NAME,
            '--email' => 'user-commands-2@xyz.earth',
            '--motto' => 'from the console',
        ]);
        $this->assertSame(0, $updated->getStatusCode(), $updated->getDisplay());
        $this->assertSame('user-commands-2@xyz.earth', $users->getOneByName(self::NAME)->getEmail());

        $nothing = $this->run_(UserUpdateCommand::class, ['command' => 'user:update', 'name' => self::NAME]);
        $this->assertNotSame(0, $nothing->getStatusCode(), 'an update with nothing to change is refused');

        $removed = $this->run_(UserRemoveFromGroupCommand::class, [
            'command' => 'user:remove-from-group',
            'name' => self::NAME,
            'group' => '@' . self::GROUP,
        ]);
        $this->assertSame(0, $removed->getStatusCode(), $removed->getDisplay());
        $this->assertNotContains(self::NAME, $groups->getMembers(self::GROUP));

        $added = $this->run_(UserAddToGroupCommand::class, [
            'command' => 'user:add-to-group',
            'name' => self::NAME,
            'group' => self::GROUP,
        ]);
        $this->assertSame(0, $added->getStatusCode(), $added->getDisplay());
        $this->assertContains(self::NAME, $groups->getMembers(self::GROUP));

        $deleted = $this->run_(UserDeleteCommand::class, ['command' => 'user:delete', 'name' => self::NAME]);
        $this->assertSame(0, $deleted->getStatusCode(), $deleted->getDisplay());
        $this->assertNull($users->getOneByName(self::NAME));
        $this->assertFalse($groups->groupExists(self::GROUP), 'a group the account was alone in goes with it');

        $missing = $this->run_(UserDeleteCommand::class, ['command' => 'user:delete', 'name' => self::NAME]);
        $this->assertNotSame(0, $missing->getStatusCode());
    }
}
