<?php

namespace YesWiki\Identity\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** `./yeswicli user:update Name --email=... --password=... --motto=...`. */
class UserUpdateCommand extends Command
{
    use ManagesAccounts;

    protected function configure(): void
    {
        $this
            ->setName('user:update')
            ->setDescription('Change an account\'s email, password or motto')
            ->addArgument('name', InputArgument::REQUIRED, 'The wiki name of the account')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'A new email address')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'A new password (or YESWIKI_USER_PASSWORD, which keeps it out of ps)')
            ->addOption('ask-password', null, InputOption::VALUE_NONE, 'Type the new password in, without echo')
            ->addOption('motto', null, InputOption::VALUE_REQUIRED, 'A new motto');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = $this->account((string)$input->getArgument('name'), $output);
        if ($user === null) {
            return Command::FAILURE;
        }

        $changes = [];
        foreach (['email', 'motto'] as $option) {
            $value = $input->getOption($option);
            if (is_string($value)) {
                $changes[$option] = trim($value);
            }
        }
        $password = $this->password($input, $output, false);
        if ($password !== null) {
            $changes['password'] = $password;
        }
        if ($changes === []) {
            $this->fail($output, 'Nothing to change: give --email, --password, --ask-password or --motto.');

            return Command::INVALID;
        }

        try {
            $this->accounts()->update($user, $changes);
        } catch (\Throwable $failed) {
            $this->fail($output, $failed->getMessage());

            return Command::FAILURE;
        }

        $output->writeln('Account <info>' . $user->getName() . '</info> updated: ' . implode(', ', array_keys($changes)) . '.');

        return Command::SUCCESS;
    }
}
