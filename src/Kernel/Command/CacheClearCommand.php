<?php

namespace YesWiki\Kernel\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Kernel\Service\CacheClearer;

/** `./yeswicli cache:clear` -- works with a stale container too, since that is exactly when it is needed. */
class CacheClearCommand extends Command
{
    private ContainerInterface $services;

    public function __construct(ContainerInterface $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    protected function configure(): void
    {
        $this
            ->setName('cache:clear')
            ->setDescription('Empty the compiled container and template caches')
            ->addOption('container', null, InputOption::VALUE_NONE, 'Only ' . CacheClearer::CONTAINER)
            ->addOption('templates', null, InputOption::VALUE_NONE, 'Only ' . CacheClearer::TEMPLATES)
            ->addOption('all', null, InputOption::VALUE_NONE, 'Everything under cache/: thumbnails, remote copies, the HTML purifier, the hashcash secret too');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $clearer = $this->services->has(CacheClearer::class) ? $this->services->get(CacheClearer::class) : new CacheClearer();
        if ($input->getOption('all')) {
            $count = $clearer->clearEverything();
            $output->writeln("cache: {$count} " . ($count === 1 ? 'entry' : 'entries') . ' removed');

            return Command::SUCCESS;
        }

        $which = [];
        if ($input->getOption('container')) {
            $which[] = CacheClearer::CONTAINER;
        }
        if ($input->getOption('templates')) {
            $which[] = CacheClearer::TEMPLATES;
        }

        foreach ($clearer->clear($which ?: CacheClearer::ALL) as $cache => $count) {
            $output->writeln("{$cache}: {$count} " . ($count === 1 ? 'entry' : 'entries') . ' removed');
        }

        return Command::SUCCESS;
    }
}
