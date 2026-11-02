<?php

namespace YesWiki\Kernel\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\Queue\QueueBudgetFactory;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueueSettings;

/**
 * `./yeswicli messenger:consume`: a consumer that blocks, waiting for messages, until a limit or a signal stops it. What a process manager (systemd, supervisord, a container) keeps alive in mode `worker`.
 *
 * `--supervised` is what the Go binary starts for each wiki it serves. The binary does not read
 * the wiki's configuration, so this is where the effective mode is found out: when it is not
 * `binary` (a wiki of a farm that picked another mode) the command exits at once with
 * EXIT_NOT_MY_MODE, which the supervisor takes as "do not restart me" instead of a crash. A
 * supervised consumer the configuration change stopped exits with EXIT_CONFIGURATION_CHANGED,
 * which the supervisor takes as "restart me now", however short its life was; unsupervised, it
 * exits with 0, which systemd's Restart=always restarts without calling it a failure.
 *
 * `--drain` does not block: it consumes what is already in the queue, then exits -- no scheduled
 * task, no wait, no time budget, but the memory ceiling of every pass. It is a pass by hand, recorded as the button of /admin/jobs
 * records one (`request`, origin `manual`): run under a worker, it neither counts as that worker
 * starting nor makes a dead one look alive. A pass already running makes it exit with 0, saying
 * `locked`.
 *
 * `--message` is the process a request hands a message it pinned to (a backup or a restore an
 * admin started), when its response cannot leave before the script ends (QueueRequestTrigger):
 * that message only, run in the wiki's mode as the request would have run it.
 */
class QueueConsumeCommand extends Command
{
    /** `EX_CONFIG`: the supervisor reads it as "this wiki is not mine to consume", not as a failure to retry. */
    public const EXIT_NOT_MY_MODE = 78;

    /** `EX_TEMPFAIL`: the supervisor reads it as "the configuration changed, start me again on the new one", the ordinary end of a life rather than a loop to back off from. */
    public const EXIT_CONFIGURATION_CHANGED = 75;

    private ContainerInterface $services;

    public function __construct(ContainerInterface $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    protected function configure(): void
    {
        $this
            ->setName('messenger:consume')
            ->setDescription('Consume the queue, waiting for new messages until a limit is reached or the process is told to stop; or empty it and exit (--drain)')
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Stop after this many seconds', '3600')
            ->addOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Stop when memory use passes this (128M, 512K, bytes, -1 for none)', '128M')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Stop after this many messages')
            ->addOption('supervised', null, InputOption::VALUE_NONE, 'Started by the Go binary: only runs when the wiki\'s mode is `binary`, exits ' . self::EXIT_NOT_MY_MODE . ' otherwise')
            ->addOption('drain', null, InputOption::VALUE_NONE, 'Consume what is already in the queue, then exit: no scheduled task, no wait')
            ->addOption('message', null, InputOption::VALUE_REQUIRED, 'Handle only the message of this id, which a request pinned (a backup or a restore an admin started)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('message') !== null) {
            return $this->handleOne($input, $output);
        }
        if ($input->getOption('drain')) {
            return $this->drain($input, $output);
        }

        $supervised = (bool)$input->getOption('supervised');
        $mode = $supervised ? QueueConsumerMode::Binary : QueueConsumerMode::Worker;

        // Before anything is read from the queue, and before the options are even looked at: a process that is not this wiki's consumer must not touch a message.
        if ($supervised) {
            $effective = $this->services->get(QueueSettings::class)->mode();
            if ($effective !== QueueConsumerMode::Binary) {
                $this->errorOutput($output)->writeln("The queue of this wiki is consumed in mode '{$effective->value}', not 'binary': the supervised consumer has nothing to do here.");

                return self::EXIT_NOT_MY_MODE;
            }
        }

        $timeLimit = $this->positiveInt($input->getOption('time-limit'));
        $messageLimit = $input->getOption('limit') === null ? null : $this->positiveInt($input->getOption('limit'));
        $memoryLimit = $this->bytes((string)$input->getOption('memory-limit'));
        if ($timeLimit === null || ($input->getOption('limit') !== null && $messageLimit === null) || $memoryLimit === null) {
            $this->errorOutput($output)->writeln('<error>--time-limit and --limit are whole numbers above zero; --memory-limit is a size such as 128M, or -1.</error>');

            return Command::INVALID;
        }

        $report = $this->services->get(QueueConsumer::class)->consume($mode, $timeLimit, $memoryLimit, $messageLimit, $output);
        $output->writeln($report->summary());

        return match (true) {
            $report->stopReason === QueuePassReport::ERROR => Command::FAILURE,
            $supervised && $report->stopReason === QueuePassReport::CONFIG_CHANGED => self::EXIT_CONFIGURATION_CHANGED,
            default => Command::SUCCESS,
        };
    }

    private function drain(InputInterface $input, OutputInterface $output): int
    {
        foreach (['--supervised', '--time-limit', '--memory-limit', '--limit'] as $option) {
            if ($input->hasParameterOption($option)) {
                $this->errorOutput($output)->writeln("<error>--drain stops when the queue is empty: {$option} is for a consumer that waits.</error>");

                return Command::INVALID;
            }
        }

        // No visitor waits for a command: the response has "already left".
        $report = $this->services->get(QueueConsumer::class)->drain(
            QueueConsumerMode::Request,
            $this->services->get(QueueBudgetFactory::class)->forHand(),
            false,
            true,
            QueuePassOrigin::Manual
        );
        $output->writeln($report->summary());

        return $report->stopReason === QueuePassReport::ERROR ? Command::FAILURE : Command::SUCCESS;
    }

    private function handleOne(InputInterface $input, OutputInterface $output): int
    {
        $message = $input->getOption('message');
        if ($input->getOption('drain') || $input->getOption('supervised')) {
            $this->errorOutput($output)->writeln('<error>--message runs that message alone: not with --drain nor --supervised.</error>');

            return Command::INVALID;
        }
        if (!is_string($message) || !ctype_digit($message) || (int)$message === 0) {
            $this->errorOutput($output)->writeln('<error>--message is the id of a message in the queue.</error>');

            return Command::INVALID;
        }

        // In the mode the request would have run it in: the message is recorded the same, whichever ran it.
        $report = $this->services->get(QueueConsumer::class)->handleById((int)$message, $this->services->get(QueueSettings::class)->mode());
        $output->writeln($report->summary());

        return $report->stopReason === QueuePassReport::ERROR ? Command::FAILURE : Command::SUCCESS;
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_string($value) && preg_match('/^[1-9]\d*$/', $value) ? (int)$value : null;
    }

    /** Bytes for "128M"-style sizes, PHP_INT_MAX for -1 (no limit); null for anything else, so a typo does not silently mean "unlimited". */
    private function bytes(string $size): ?int
    {
        if (trim($size) === '-1') {
            return PHP_INT_MAX;
        }
        $bytes = preg_match('/^\s*\d+\s*[kmg]?\s*$/i', $size) ? QueueBudgetFactory::bytes($size) : 0;

        return $bytes > 0 ? $bytes : null;
    }

    private function errorOutput(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }
}
