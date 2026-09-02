<?php

namespace YesWiki\Identity\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** `./yeswicli user:add-to-group Name admins` -- the group is created when it does not exist. */
class UserAddToGroupCommand extends Command
{
    use ManagesAccounts;

    protected function configure(): void
    {
        $this
            ->setName('user:add-to-group')
            ->setDescription('Put an account in a group, creating the group when missing')
            ->addArgument('name', InputArgument::REQUIRED, 'The wiki name of the account')
            ->addArgument('group', InputArgument::REQUIRED, 'The group, with or without its leading @');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = $this->account((string)$input->getArgument('name'), $output);
        if ($user === null) {
            return Command::FAILURE;
        }
        $group = ltrim(trim((string)$input->getArgument('group')), '@');
        if ($group === '') {
            $this->fail($output, 'The group name is empty.');

            return Command::INVALID;
        }

        try {
            $this->join($group, $user->getName(), $output);
        } catch (\Throwable $failed) {
            $this->fail($output, $failed->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
