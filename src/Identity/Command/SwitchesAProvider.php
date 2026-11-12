<?php

namespace YesWiki\Identity\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Identity\Service\SignIn\ProvidersEditor;

/**
 * What `sign-in:enable` and `sign-in:disable` share -- a trait, since the console instantiates every class it finds in a Command/
 * directory. The providers are a setting kept in the wiki's database, where editing the configuration file cannot reach them: this
 * is the way to switch one from a shell, local accounts above all when the provider in charge is down.
 */
trait SwitchesAProvider
{
    private ContainerInterface $services;

    public function __construct(ContainerInterface $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    private function identifierArgument(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'The provider\'s identifier, as Administration › Sign-in shows it (local for the wiki\'s own accounts)');
    }

    private function switchTo(bool $on, InputInterface $input, OutputInterface $output): int
    {
        $id = trim((string)$input->getArgument('id'));
        // nobody signed in acts: the screen's guard against locking its own user out does not apply
        $refused = $this->services->get(ProvidersEditor::class)->setEnabled($id, $on, null);
        if ($refused !== null) {
            $output->writeln("<error>{$id}: {$refused}</error>");

            return Command::FAILURE;
        }
        $output->writeln("{$id}: " . ($on ? 'on' : 'off'));

        return Command::SUCCESS;
    }
}
