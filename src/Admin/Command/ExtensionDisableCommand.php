<?php

namespace YesWiki\Admin\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Admin\Service\ExtensionActivation;

/** `./yeswicli extension:disable <name>` -- switch an extension off for this wiki (ADR-0029). */
class ExtensionDisableCommand extends Command
{
    public function __construct(private readonly ContainerInterface $services)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('extension:disable')
            ->setDescription('Switch an extension off for this wiki')
            ->addArgument('name', InputArgument::REQUIRED, 'The extension, by folder name')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Even if another active extension needs it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string)$input->getArgument('name');
        $problems = $this->services->get(ExtensionActivation::class)->deactivate($name, (bool)$input->getOption('force'));
        foreach ($problems as $problem) {
            $output->writeln("<error>{$problem}</error>");
        }
        if ($problems === []) {
            $output->writeln("{$name}: off");
        }

        return $problems === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
