<?php

namespace YesWiki\Identity\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** `./yeswicli user:delete Name` -- the account, its memberships, the groups it was alone in, what it owned. */
class UserDeleteCommand extends Command
{
    use ManagesAccounts;

    protected function configure(): void
    {
        $this
            ->setName('user:delete')
            ->setDescription('Delete an account and remove it from every group')
            ->addArgument('name', InputArgument::REQUIRED, 'The wiki name of the account');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = $this->account((string)$input->getArgument('name'), $output);
        if ($user === null) {
            return Command::FAILURE;
        }

        try {
            $this->accounts()->purge($user);
        } catch (\Throwable $failed) {
            $this->fail($output, $failed->getMessage());

            return Command::FAILURE;
        }

        $output->writeln('Account <info>' . $user->getName() . '</info> deleted.');

        return Command::SUCCESS;
    }
}
