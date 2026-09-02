<?php

namespace YesWiki\Identity\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** `./yeswicli user:create Name mail@example.org --password=... --group=admins`. */
class UserCreateCommand extends Command
{
    use ManagesAccounts;

    protected function configure(): void
    {
        $this
            ->setName('user:create')
            ->setDescription('Create an account, optionally in one or more groups')
            ->addArgument('name', InputArgument::REQUIRED, 'The wiki name of the account')
            ->addArgument('email', InputArgument::REQUIRED, 'Its email address')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Its password (or YESWIKI_USER_PASSWORD, which keeps it out of ps)')
            ->addOption('ask-password', null, InputOption::VALUE_NONE, 'Type the password in, without echo')
            ->addOption('group', 'g', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A group to put the account in, created when missing (repeatable, or comma separated)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = trim((string)$input->getArgument('name'));
        $email = trim((string)$input->getArgument('email'));
        $password = $this->password($input, $output, true);
        if ($password === null) {
            return Command::INVALID;
        }

        try {
            $user = $this->accounts()->create(['name' => $name, 'email' => $email, 'password' => $password]);
            if ($user === null) {
                $this->fail($output, "Account \"{$name}\" was not created.");

                return Command::FAILURE;
            }
            $output->writeln("Account <info>{$user->getName()}</info> created ({$user->getEmail()}).");
            foreach ($this->groupNames($input, 'group') as $group) {
                $this->join($group, $user->getName(), $output);
            }
        } catch (\Throwable $failed) {
            $this->fail($output, $failed->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
