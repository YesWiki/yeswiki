<?php

namespace YesWiki\Identity\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** `./yeswicli user:remove-from-group Name admins` -- refused when it would leave the admins group empty. */
class UserRemoveFromGroupCommand extends Command
{
    use ManagesAccounts;

    protected function configure(): void
    {
        $this
            ->setName('user:remove-from-group')
            ->setDescription('Take an account out of a group')
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
        if (!$this->groups()->groupExists($group)) {
            $this->fail($output, "No group called \"{$group}\".");

            return Command::FAILURE;
        }
        $members = $this->groups()->getMembers($group);
        if (!in_array($user->getName(), $members, true)) {
            $output->writeln('<comment>' . $user->getName() . " is not in group {$group}; nothing to do.</comment>");

            return Command::SUCCESS;
        }
        if (strtolower($group) === ADMIN_GROUP && count($members) === 1) {
            $this->fail($output, $user->getName() . ' is the only member of the admins group; add another admin first.');

            return Command::FAILURE;
        }

        try {
            $this->groups()->remove($group, [$user->getName()]);
        } catch (\Throwable $failed) {
            $this->fail($output, $failed->getMessage());

            return Command::FAILURE;
        }

        $output->writeln('<info>' . $user->getName() . "</info> removed from group <info>{$group}</info>.");

        return Command::SUCCESS;
    }
}
