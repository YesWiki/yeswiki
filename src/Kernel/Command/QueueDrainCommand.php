<?php

namespace YesWiki\Kernel\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\Queue\QueueBudgetFactory;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueueSettings;

/**
 * `./yeswicli messenger:drain`: one pass over the queue that ends by itself, for a crontab or any scheduler (mode `command`), and the process a request starts in `request-spawn`.
 *
 * It never waits for a message: due scheduled tasks first, then messages until the queue is empty
 * or the budget spent. The budget is the one the wiki's mode gives; a mode that blocks
 * (`worker`, `binary`) has none, so an admin who runs this by hand there gets the `command` one.
 *
 * With `--message`, it is the process a request hands a message it pinned to, when its response
 * cannot leave before the script ends (QueueRequestTrigger): that message only, run as the request
 * would have run it.
 */
class QueueDrainCommand extends Command
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
            ->setName('messenger:drain')
            ->setDescription('Consume the queue until it is empty or the time budget is spent, without waiting for more')
            ->addOption('budget', null, InputOption::VALUE_REQUIRED, 'Stop taking messages after this many seconds, if that is shorter than what the server allows')
            ->addOption('no-schedule', null, InputOption::VALUE_NONE, 'Do not queue the scheduled tasks that are due first')
            ->addOption('message', null, InputOption::VALUE_REQUIRED, 'Handle only the message of this id, which a request pinned (a backup or a restore an admin started)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $message = $input->getOption('message');
        if ($message !== null) {
            if (!is_string($message) || !ctype_digit($message) || (int)$message === 0) {
                $this->errorOutput($output)->writeln('<error>--message is the id of a message in the queue.</error>');

                return Command::INVALID;
            }
            // In the mode the request would have run it in: the message is recorded the same, whichever ran it.
            $report = $this->services->get(QueueConsumer::class)->handleById((int)$message, $this->services->get(QueueSettings::class)->mode());
            $output->writeln($report->summary());

            return $report->stopReason === QueuePassReport::ERROR ? Command::FAILURE : Command::SUCCESS;
        }

        $seconds = null;
        $asked = $input->getOption('budget');
        if ($asked !== null) {
            if (!is_numeric($asked) || (float)$asked <= 0) {
                $this->errorOutput($output)->writeln('<error>--budget is a number of seconds above zero.</error>');

                return Command::INVALID;
            }
            $seconds = (float)$asked;
        }

        $mode = $this->services->get(QueueSettings::class)->mode();
        if (!$mode->isBudgeted()) {
            $mode = QueueConsumerMode::Command;
        }
        $budget = $this->services->get(QueueBudgetFactory::class)->forMode($mode, true);
        if ($seconds !== null) {
            $budget = $budget->cappedTo($seconds);
        }

        // No visitor waits for a command, whatever the mode: the response has "already left".
        $report = $this->services->get(QueueConsumer::class)->drain($mode, $budget, !$input->getOption('no-schedule'), true);
        $output->writeln($report->summary());

        return $report->stopReason === QueuePassReport::ERROR ? Command::FAILURE : Command::SUCCESS;
    }

    protected function errorOutput(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }
}
