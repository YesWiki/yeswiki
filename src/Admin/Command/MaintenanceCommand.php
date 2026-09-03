<?php

namespace YesWiki\Admin\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Admin\Service\MaintenanceService;

/** `./yeswicli core:maintenance` -- the housekeeping a request runs by default, with an exit code (ticket 54). */
class MaintenanceCommand extends Command
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
            ->setName('core:maintenance')
            ->setDescription('Run the wiki\'s housekeeping sweep (ticket 54)')
            ->setHelp(
                "Purges old page revisions and Journal entries, expires recovery and activation\n"
                . "keys, and drains the search index queue -- the same steps, and the same\n"
                . "maintenance.before/after events, as a request-triggered sweep.\n\n"
                . "A wiki with a crontab should set maintenance_trigger to 'cron' so a visitor\n"
                . 'never pays for this; see INSTALL.md.'
            )
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Sweep even if one ran less than 30 minutes ago');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $maintenance = $this->services->get(MaintenanceService::class);

        if (!$maintenance->claim((bool)$input->getOption('force'))) {
            $ago = time() - $maintenance->lastRun();
            $output->writeln("<comment>A sweep ran {$ago}s ago; the interval has not elapsed. Use --force to sweep anyway.</comment>");

            return Command::SUCCESS;
        }

        $report = $maintenance->sweep();

        foreach ($report->steps() as $step => $outcome) {
            $output->writeln("  {$step}: {$outcome}");
        }
        foreach ($report->failures() as $step => $failure) {
            $output->writeln("<error>  {$step}: {$failure}</error>");
        }

        $took = number_format($report->duration(), 2);
        if ($report->hasFailures()) {
            $output->writeln("<error>Maintenance finished in {$took}s with " . count($report->failures()) . ' failed step(s).</error>');

            return Command::FAILURE;
        }
        $output->writeln("<info>Maintenance done in {$took}s.</info>");

        return Command::SUCCESS;
    }
}
