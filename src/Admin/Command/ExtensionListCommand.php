<?php

namespace YesWiki\Admin\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Admin\Service\ExtensionActivation;

/** `./yeswicli extension:list` -- every extension this wiki can see, and whether it runs (ADR-0029). */
class ExtensionListCommand extends Command
{
    public function __construct(private readonly ContainerInterface $services)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('extension:list')->setDescription('List the extensions this wiki can see, and which ones it runs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $activation = $this->services->get(ExtensionActivation::class);
        $active = $activation->active();
        foreach ($activation->visible() as $folder => $manifest) {
            $output->writeln(sprintf('%s %s %s', in_array($folder, $active, true) ? '[on] ' : '[off]', $folder, $manifest->version() ?? 'dev'));
        }

        return Command::SUCCESS;
    }
}
