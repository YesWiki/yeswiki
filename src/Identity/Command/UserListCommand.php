<?php

namespace YesWiki\Identity\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** `./yeswicli user:list` -- every account, with its email and groups. */
class UserListCommand extends Command
{
    use ManagesAccounts;

    protected function configure(): void
    {
        $this
            ->setName('user:list')
            ->setDescription('List the accounts, with their email and groups');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];
        foreach ($this->users()->getAll(['name', 'email', 'signuptime']) as $row) {
            $user = $this->users()->getOneByName((string)$row['name']);
            $groups = $user === null ? [] : $this->users()->groupsWhereIsMember($user, false);
            $rows[] = [(string)$row['name'], (string)($row['email'] ?? ''), implode(', ', $groups), (string)($row['signuptime'] ?? '')];
        }
        usort($rows, fn (array $a, array $b) => strcasecmp($a[0], $b[0]));

        (new Table($output))
            ->setHeaders(['Name', 'Email', 'Groups', 'Signed up'])
            ->setRows($rows)
            ->render();

        return Command::SUCCESS;
    }
}
