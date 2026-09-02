<?php

namespace YesWiki\Test\Identity;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
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

    /**
     * @param array<string, mixed> $input
     */
    private function run_(Command $command, array $input): CommandTester
    {
        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->find($input['command']));
        $tester->execute($input, ['interactive' => false]);

        return $tester;
    }

    public function testAnAccountIsCreatedUpdatedGroupedAndDeleted(): void
    {
        $users = self::getWiki()->services->get(UserManager::class);
        $groups = self::getWiki()->services->get(GroupOperationsService::class);

        $created = $this->run_(new UserCreateCommand(self::getWiki()->services), [
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

        $listed = $this->run_(new UserListCommand(self::getWiki()->services), ['command' => 'user:list']);
        $this->assertStringContainsString(self::NAME, $listed->getDisplay());
        $this->assertStringContainsString(self::GROUP, $listed->getDisplay());

        $updated = $this->run_(new UserUpdateCommand(self::getWiki()->services), [
            'command' => 'user:update',
            'name' => self::NAME,
            '--email' => 'user-commands-2@xyz.earth',
            '--motto' => 'from the console',
        ]);
        $this->assertSame(0, $updated->getStatusCode(), $updated->getDisplay());
        $user = $users->getOneByName(self::NAME);
        $this->assertNotNull($user);
        $this->assertSame('user-commands-2@xyz.earth', $user->getEmail());

        $nothing = $this->run_(new UserUpdateCommand(self::getWiki()->services), ['command' => 'user:update', 'name' => self::NAME]);
        $this->assertNotSame(0, $nothing->getStatusCode(), 'an update with nothing to change is refused');

        $removed = $this->run_(new UserRemoveFromGroupCommand(self::getWiki()->services), [
            'command' => 'user:remove-from-group',
            'name' => self::NAME,
            'group' => '@' . self::GROUP,
        ]);
        $this->assertSame(0, $removed->getStatusCode(), $removed->getDisplay());
        $this->assertNotContains(self::NAME, $groups->getMembers(self::GROUP));

        $added = $this->run_(new UserAddToGroupCommand(self::getWiki()->services), [
            'command' => 'user:add-to-group',
            'name' => self::NAME,
            'group' => self::GROUP,
        ]);
        $this->assertSame(0, $added->getStatusCode(), $added->getDisplay());
        $this->assertContains(self::NAME, $groups->getMembers(self::GROUP));

        $deleted = $this->run_(new UserDeleteCommand(self::getWiki()->services), ['command' => 'user:delete', 'name' => self::NAME]);
        $this->assertSame(0, $deleted->getStatusCode(), $deleted->getDisplay());
        $this->assertNull($users->getOneByName(self::NAME));
        $this->assertFalse($groups->groupExists(self::GROUP), 'a group the account was alone in goes with it');

        $missing = $this->run_(new UserDeleteCommand(self::getWiki()->services), ['command' => 'user:delete', 'name' => self::NAME]);
        $this->assertNotSame(0, $missing->getStatusCode());
    }
}
