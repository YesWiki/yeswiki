<?php

namespace YesWiki\Identity\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Identity\Service\SignIn\ProvidersEditor;

/** What `sign-in:enable` and `sign-in:disable` share. */
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
        $refused = $this->services->get(ProvidersEditor::class)->setEnabled($id, $on, null);
        if ($refused !== null) {
            $output->writeln("<error>{$id}: {$refused}</error>");

            return Command::FAILURE;
        }
        $output->writeln("{$id}: " . ($on ? 'on' : 'off'));

        return Command::SUCCESS;
    }
}
