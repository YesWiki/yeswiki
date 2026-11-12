<?php

namespace YesWiki\Identity\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** `./yeswicli sign-in:disable local`: switches a sign-in provider off, never the last one on. */
class SignInDisableCommand extends Command
{
    use SwitchesAProvider;

    protected function configure(): void
    {
        $this
            ->setName('sign-in:disable')
            ->setDescription('Switch a sign-in provider off, unless it is the last one on');
        $this->identifierArgument();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->switchTo(false, $input, $output);
    }
}
