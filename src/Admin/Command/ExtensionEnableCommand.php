<?php

namespace YesWiki\Admin\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Admin\Service\ExtensionActivation;

/** `./yeswicli extension:enable <name>` -- switch an extension on for this wiki (ADR-0029). */
class ExtensionEnableCommand extends Command
{
    public function __construct(private readonly ContainerInterface $services)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('extension:enable')
            ->setDescription('Switch an extension on for this wiki')
            ->addArgument('name', InputArgument::REQUIRED, 'The extension, by folder name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string)$input->getArgument('name');
        $problems = $this->services->get(ExtensionActivation::class)->activate($name);
        foreach ($problems as $problem) {
            $output->writeln("<error>{$problem}</error>");
        }
        if ($problems === []) {
            $output->writeln("{$name}: on");
        }

        return $problems === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
