<?php

namespace YesWiki\Identity\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** `./yeswicli sign-in:enable local`: switches a sign-in provider on, the way back in when the provider in charge is down. */
class SignInEnableCommand extends Command
{
    use SwitchesAProvider;

    protected function configure(): void
    {
        $this
            ->setName('sign-in:enable')
            ->setDescription('Switch a sign-in provider on (sign-in:enable local brings back the wiki\'s own accounts)');
        $this->identifierArgument();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->switchTo(true, $input, $output);
    }
}
