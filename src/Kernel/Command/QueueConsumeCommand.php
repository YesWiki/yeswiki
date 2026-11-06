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
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueuePassLimitsFactory;
use YesWiki\Kernel\Service\Queue\QueueSettings;

/**
 * `./yeswicli messenger:consume`: a blocking consumer kept alive by the Go binary (`--supervised`) or a process manager;
 * `--drain` queues the scheduled tasks that are due, empties the queue once and exits, `--message` runs the one message a request handed it.
 */
class QueueConsumeCommand extends Command
{
    public const EXIT_NOT_MY_MODE = 78;

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
            ->addOption('supervised', null, InputOption::VALUE_NONE, 'Started by the Go binary: only runs when the wiki\'s mode is `worker`, exits ' . self::EXIT_NOT_MY_MODE . ' otherwise')
            ->addOption('drain', null, InputOption::VALUE_NONE, 'Queue the scheduled tasks that are due, consume what is in the queue, then exit: no wait')
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

        if ($supervised) {
            $effective = $this->services->get(QueueSettings::class)->mode();
            if ($effective !== QueueConsumerMode::Worker) {
                $this->errorOutput($output)->writeln("The queue of this wiki is consumed in mode '{$effective->value}', not 'worker': the supervised consumer has nothing to do here.");

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

        $report = $this->services->get(QueueConsumer::class)->consume(QueueConsumerMode::Worker, $timeLimit, $memoryLimit, $messageLimit, $output);
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

        $report = $this->services->get(QueueConsumer::class)->drain(
            QueueConsumerMode::Request,
            $this->services->get(QueuePassLimitsFactory::class)->forPass(),
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

        $report = $this->services->get(QueueConsumer::class)->handleById((int)$message, $this->services->get(QueueSettings::class)->mode());
        $output->writeln($report->summary());

        return $report->stopReason === QueuePassReport::ERROR ? Command::FAILURE : Command::SUCCESS;
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_string($value) && preg_match('/^[1-9]\d*$/', $value) ? (int)$value : null;
    }

    /** Bytes for "128M"-style sizes, PHP_INT_MAX for -1 (no limit); null for anything else. */
    private function bytes(string $size): ?int
    {
        if (trim($size) === '-1') {
            return PHP_INT_MAX;
        }
        $bytes = preg_match('/^\s*\d+\s*[kmg]?\s*$/i', $size) ? QueuePassLimitsFactory::bytes($size) : 0;

        return $bytes > 0 ? $bytes : null;
    }

    private function errorOutput(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }
}
