<?php

namespace YesWiki\Kernel\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\RuntimeConfig;

/** `./yeswicli journal:prune` -- the retention the housekeeping pass applies, for an operator who wants it now (ticket 51). */
class JournalPruneCommand extends Command
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
            ->setName('journal:prune')
            ->setDescription('Delete Journal entries past their retention (ticket 51)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->services->get(RuntimeConfig::class);
        $audit = (int)($config[Journal::AUDIT_PURGE_SETTING] ?? 365);
        $diagnostic = (int)($config[Journal::DIAGNOSTIC_PURGE_SETTING] ?? 14);

        $removed = $this->services->get(Journal::class)->prune();

        $output->writeln("Journal pruned: {$removed} entries removed (audit kept {$audit} days, diagnostics {$diagnostic}).");

        return Command::SUCCESS;
    }
}
