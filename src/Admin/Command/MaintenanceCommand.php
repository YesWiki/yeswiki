<?php

namespace YesWiki\Admin\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Kernel\Command\QueueDrainCommand;

/**
 * `./yeswicli core:maintenance`: a deprecated alias of `messenger:drain`, so that the crontabs written for the housekeeping sweep (ticket 54) keep working.
 *
 * The sweep is gone: each housekeeping step is a scheduled task of the queue, and a drain queues
 * the due ones and consumes them. The alias does exactly what the drain does, exit code included,
 * and says once on stderr -- which cron mails -- what to call instead.
 */
class MaintenanceCommand extends QueueDrainCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('core:maintenance')
            ->setDescription('Deprecated: runs messenger:drain, call that instead')
            ->setHelp(
                "The housekeeping sweep this command ran is gone: every step is a scheduled task of\n"
                . "the background queue, which `messenger:drain` consumes. This alias runs it, so an\n"
                . 'existing crontab keeps working; replace it with `./yeswicli messenger:drain`.'
            )
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Ignored: kept so that crontabs that pass it keep working');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->errorOutput($output)->writeln('<comment>core:maintenance is deprecated: it runs messenger:drain, call `./yeswicli messenger:drain` instead.</comment>');

        return parent::execute($input, $output);
    }
}
