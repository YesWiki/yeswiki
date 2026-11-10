<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher as PassDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMemoryLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\Worker;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassLimits;
use YesWiki\Kernel\Queue\QueuePassObserver;
use YesWiki\Kernel\Queue\QueuePassReceiver;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Queue\QueueRetryStrategy;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Service\ConfigurationWatch;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\RequestScope;

/**
 * The only code that takes messages from the queue and runs their handlers, in every mode, over Messenger's Worker.
 * drain() is a pass that ends by itself, never waiting; consume() blocks like `messenger:consume`, for `worker` mode.
 * A pass first fails what dead consumers left, then takes nothing while the wiki is hibernated; drain() on a hibernated wiki does nothing but leave its trace.
 */
class QueueConsumer
{
    private const RECEIVER = QueueTransport::DEFAULT_QUEUE;

    private const HEARTBEAT_SECONDS = 60;

    private const CONFIGURATION_CHECK_SECONDS = 5;

    public function __construct(
        private readonly QueueTransport $transport,
        private readonly QueueBus $bus,
        private readonly QueueRecorder $recorder,
        private readonly QueueStatistics $statistics,
        private readonly QueuePassLimitsFactory $passLimits,
        private readonly HibernationService $hibernation,
        private readonly Journal $journal,
        private readonly QueueScheduleTicker $scheduleTicker,
        private readonly ClockInterface $clock,
        private readonly ConfigurationWatch $configuration,
        private readonly QueueHolderLock $holderLock,
        private readonly ?RequestScope $requestScope = null,
    ) {
    }

    /** Before each message, the services forget what they cached of the database, since another process may have written since the one before. */
    public function onMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        $this->requestScope?->forgetTheDatabase();
    }

    /**
     * One pass that ends by itself: due tasks first, then every message that was there once they were dispatched, until
     * none is left, the memory is spent or 80 % of PHP's limit, raised as far as the host lets it, has passed; never throws.
     * $mode is `api` for the cron's call, `request` for every other pass: the end of a request, the jobs page's button, `--drain`.
     */
    public function drain(QueueConsumerMode $mode, QueuePassLimits $limits, bool $responseAlreadySent = true): QueuePassReport
    {
        $startedAt = microtime(true);
        if ($this->hibernation->isWikiHibernatedNow()) {
            return $this->leaveATrace($this->report(0, $startedAt, QueuePassReport::PAUSED), $mode, $limits, $responseAlreadySent);
        }

        return $this->pass($mode, $limits, !$responseAlreadySent, function (QueuePassObserver $observer, int $passId) use ($mode, $limits): string {
            $this->transport->claimAs($mode);
            $this->reapTheDead($observer, $passId);
            $this->tick();
            // Read after the tick, so that what it dispatched is run now; what the handlers send waits for the next pass.
            $cutoff = $this->transport->lastId();

            $raised = false;
            $raiseTheLimit = function () use (&$limits, &$raised): void {
                if (!$raised) {
                    $raised = true;
                    $limits = $this->passLimits->withPhpLimitRaised($limits);
                }
            };
            $receiver = new QueuePassReceiver(
                $this->transport,
                function () use (&$limits, $observer, $cutoff, $raiseTheLimit): ?string {
                    return $this->reasonToStop($limits, $observer, $cutoff, $raiseTheLimit);
                },
                $observer,
                upToId: $cutoff
            );
            // A pass may run for minutes, for hours where PHP has no limit: it says it is alive, as consume() does, so that no judge closes it as killed.
            $lastBeat = $this->now();
            $this->work($receiver, $observer, null, function (WorkerRunningEvent $event) use ($observer, $passId, &$lastBeat): void {
                self::stopWhenIdle($event);
                if (!$event->isWorkerIdle() && $this->now() - $lastBeat >= self::HEARTBEAT_SECONDS) {
                    $lastBeat = $this->now();
                    $this->recorder->passHeartbeat($passId, $observer->handled(), memory_get_peak_usage(true));
                }
            });

            return $receiver->stopReason() ?? QueuePassReport::EMPTY;
        });
    }

    /** A blocking consumer: takes messages as they come until a limit, a signal or a configuration change stops it. */
    public function consume(QueueConsumerMode $mode, int $timeLimit, int $memoryLimitBytes, ?int $messageLimit = null, ?OutputInterface $out = null): QueuePassReport
    {
        $startedAt = microtime(true);
        $limits = new QueuePassLimits($memoryLimitBytes);

        return $this->pass($mode, $limits, false, function (QueuePassObserver $observer, int $passId) use ($mode, $timeLimit, $memoryLimitBytes, $messageLimit, $out, $startedAt): string {
            $logger = $out === null ? null : new ConsoleLogger($out);
            $this->transport->claimAs($mode);
            $this->reapTheDead($observer, $passId);
            $hibernated = $this->hibernation->isWikiHibernatedNow();
            $configurationChanged = $this->configurationChangedEveryFewSeconds();
            if (!$hibernated) {
                $this->tick();
            }

            $receiver = new QueuePassReceiver(
                $this->transport,
                static fn (): ?string => match (true) {
                    $configurationChanged() => QueuePassReport::CONFIG_CHANGED,
                    $hibernated => QueuePassReport::PAUSED,
                    default => null,
                },
                $observer
            );
            $lastBeat = microtime(true);
            $stopListeners = [
                new StopWorkerOnTimeLimitListener($timeLimit, $logger),
                new StopWorkerOnMemoryLimitListener($memoryLimitBytes, $logger),
            ];
            if ($messageLimit !== null) {
                $stopListeners[] = new StopWorkerOnMessageLimitListener($messageLimit, $logger);
            }

            $signalled = $this->work($receiver, $observer, $logger, function (WorkerRunningEvent $event) use ($observer, $passId, &$lastBeat, $hibernated, $receiver): void {
                if ($receiver->stopReason() === QueuePassReport::CONFIG_CHANGED) {
                    $event->getWorker()->stop();

                    return;
                }
                if ($event->isWorkerIdle() && !$hibernated) {
                    $this->tick();
                }
                if (microtime(true) - $lastBeat >= self::HEARTBEAT_SECONDS) {
                    $lastBeat = microtime(true);
                    $this->recorder->passHeartbeat($passId, $observer->handled(), memory_get_peak_usage(true));
                    $this->reapTheDead($observer, $passId);
                }
            }, $stopListeners, true);

            return match (true) {
                $signalled => QueuePassReport::STOPPED,
                $receiver->stopReason() === QueuePassReport::CONFIG_CHANGED => QueuePassReport::CONFIG_CHANGED,
                $messageLimit !== null && $observer->handled() >= $messageLimit => QueuePassReport::LIMIT,
                memory_get_usage(true) > $memoryLimitBytes => QueuePassReport::MEMORY,
                microtime(true) - $startedAt >= $timeLimit => QueuePassReport::TIME_LIMIT,
                default => QueuePassReport::STOPPED,
            };
        });
    }

    /** Runs that message now, whatever the limits and even while hibernated, in a pass of `request`: for a message pinned to its sender's request, here or in the process it was handed to. */
    public function handleById(int $messageId): QueuePassReport
    {
        return $this->pass(QueueConsumerMode::Request, null, false, function (QueuePassObserver $observer) use ($messageId): string {
            $this->transport->claimAs(QueueConsumerMode::Request);

            $receiver = new QueuePassReceiver($this->transport, static fn (): ?string => null, $observer, $messageId);
            $this->work($receiver, $observer, null, self::stopWhenIdle(...));

            return QueuePassReport::EMPTY;
        });
    }

    /** The row of the cron's pass that did nothing (wiki hibernated), closed at once: seen, it is not taken for gone when the wiki wakes up; `worker` passes through consume(). */
    private function leaveATrace(QueuePassReport $report, QueueConsumerMode $mode, QueuePassLimits $limits, bool $responseAlreadySent): QueuePassReport
    {
        if ($mode === QueueConsumerMode::Api) {
            try {
                $this->recorder->passFinished($this->recorder->passStarted($mode, $limits, !$responseAlreadySent), $report, memory_get_peak_usage(true));
            } catch (\Throwable) {
            }
        }

        return $report;
    }

    /**
     * Opens the pass's row, runs it, closes the row with the report; the pass holds its holder lock from before its row names it until after the row is closed.
     *
     * @param \Closure(QueuePassObserver, int): string $body
     */
    private function pass(QueueConsumerMode $mode, ?QueuePassLimits $limits, bool $beforeResponse, \Closure $body): QueuePassReport
    {
        $startedAt = microtime(true);
        $observer = new QueuePassObserver($this->recorder, $this->journal, $mode, $this->clock);
        $passId = null;
        $holder = QueueHolderLock::newToken();

        try {
            try {
                // Taken before the row names it, so a judge never finds the file of a pass that has not locked it yet.
                $this->holderLock->hold($holder);
                $this->transport->workFor($holder);
                $passId = $this->recorder->passStarted($mode, $limits, $beforeResponse, $holder);
                $stopReason = $body($observer, $passId);
                $report = $this->report($observer->handled(), $startedAt, $stopReason);
            } catch (\Throwable $broken) {
                $this->journal->error('A queue pass stopped on an error: ' . $broken->getMessage(), [
                    'action' => 'queue.pass_failed',
                    'target' => $mode->value,
                    'exception' => $broken,
                ]);
                $report = $this->report($observer->handled(), $startedAt, QueuePassReport::ERROR);
            }

            if ($passId !== null) {
                try {
                    $this->recorder->passFinished($passId, $report, memory_get_peak_usage(true));
                } catch (\Throwable) {
                }
            }
        } finally {
            $this->holderLock->release($holder);
            $this->transport->workFor(null);
        }

        return $report;
    }

    /**
     * Runs a Worker over the receiver until it stops, with the retry and failure listeners on a dispatcher of its own.
     *
     * @param \Closure(WorkerRunningEvent): void $onRunning
     * @param list<EventSubscriberInterface>     $stopListeners
     */
    private function work(QueuePassReceiver $receiver, QueuePassObserver $observer, ?LoggerInterface $logger, \Closure $onRunning, array $stopListeners = [], bool $stopOnSignals = false): bool
    {
        $events = new PassDispatcher();
        $events->addSubscriber(new AddErrorDetailsStampListener());
        $events->addSubscriber(new SendFailedMessageForRetryListener(
            new ServiceLocator([self::RECEIVER => fn (): QueueTransport => $this->transport]),
            new ServiceLocator([self::RECEIVER => static fn (): QueueRetryStrategy => new QueueRetryStrategy()]),
            $logger
        ));
        $events->addSubscriber(new SendFailedMessageToFailureTransportListener(
            new ServiceLocator([self::RECEIVER => fn (): QueueTransport => $this->transport->withQueue(QueueTransport::FAILED_QUEUE)]),
            $logger
        ));
        $events->addSubscriber($observer);
        foreach ($stopListeners as $listener) {
            $events->addSubscriber($listener);
        }
        $events->addListener(WorkerRunningEvent::class, $onRunning);
        $events->addListener(WorkerMessageReceivedEvent::class, $this->onMessageReceived(...));

        $worker = new Worker([self::RECEIVER => $receiver], $this->bus, $events, $logger);

        return $this->runStoppingOnSignals($worker, $stopOnSignals);
    }

    /** Runs the worker; for a blocking consumer, SIGTERM and SIGINT let the message in hand finish instead of killing it. */
    private function runStoppingOnSignals(Worker $worker, bool $stopOnSignals): bool
    {
        if (!$stopOnSignals || PHP_SAPI !== 'cli' || !function_exists('pcntl_signal')) {
            $worker->run();

            return false;
        }

        $signalled = false;
        $async = pcntl_async_signals(true);
        $previous = [];
        foreach ([SIGTERM, SIGINT] as $signal) {
            $previous[$signal] = pcntl_signal_get_handler($signal);
            pcntl_signal($signal, static function () use ($worker, &$signalled): void {
                $signalled = true;
                $worker->stop();
            });
        }

        try {
            $worker->run();
        } finally {
            foreach ($previous as $signal => $handler) {
                pcntl_signal($signal, $handler);
            }
            pcntl_async_signals($async);
        }

        return $signalled;
    }

    private static function stopWhenIdle(WorkerRunningEvent $event): void
    {
        if ($event->isWorkerIdle()) {
            $event->getWorker()->stop();
        }
    }

    /**
     * Why a pass that ends by itself should take no further message, or null to take the next one. Once a message is
     * known to be there and before the first is taken, it raises PHP's limit, a visitor waiting or not; the pass stops short of the limit that holds.
     *
     * @param \Closure(): void $raiseTheLimit raises PHP's limit once, and moves the pass's PHP stop accordingly
     */
    private function reasonToStop(QueuePassLimits &$limits, QueuePassObserver $observer, int $cutoff, \Closure $raiseTheLimit): ?string
    {
        if ($this->hibernation->isWikiHibernatedNow()) {
            return QueuePassReport::PAUSED;
        }
        if ($limits->memoryExhausted()) {
            return QueuePassReport::MEMORY;
        }
        if ($this->transport->peekNextType($cutoff) === null) {
            return QueuePassReport::EMPTY;
        }
        if ($observer->handled() === 0) {
            $raiseTheLimit();
        }
        if ($limits->phpLimitReached()) {
            return $limits->timeStopReason();
        }

        return null;
    }

    /**
     * Whether the configuration changed since this consumer started, asked at most every few seconds, true for good once it was.
     *
     * @return \Closure(): bool
     */
    private function configurationChangedEveryFewSeconds(): \Closure
    {
        $changed = false;
        $askedAt = microtime(true);

        return function () use (&$changed, &$askedAt): bool {
            if (!$changed && microtime(true) - $askedAt >= self::CONFIGURATION_CHECK_SECONDS) {
                $askedAt = microtime(true);
                $changed = $this->configuration->changedSinceBoot();
            }

            return $changed;
        };
    }

    private function now(): float
    {
        return (float)$this->clock->now()->format('U.u');
    }

    /**
     * The one reaper (ADR-0030, "The consumer"): moves to `failed` what dead holders left in progress, recording each; closes as killed the open passes, but its own, that are overdue by the same verdict;
     * and only then removes the file of each of those holders that is gone, so that no judge finds it absent while one of its rows is still open. A failure is only journaled.
     */
    private function reapTheDead(QueuePassObserver $observer, ?int $ownPassId): void
    {
        try {
            $dead = [];
            foreach ($this->transport->reapKilled() as $killed) {
                $dead[$killed['deliveredBy']] = true;
                $this->killed($observer, $killed);
            }
            $now = $this->clock->now();
            foreach ($this->statistics->openPasses() as $pass) {
                if ($pass['id'] !== $ownPassId && $this->transport->isOverdue($pass['holder'], $pass['lastAt'], $now)) {
                    $this->recorder->passKilled($pass['id']);
                    $dead[$pass['holder']] = true;
                }
            }
            foreach (array_keys($dead) as $holder) {
                if ($this->transport->holderState((string)$holder) === QueueHolderLock::FREE) {
                    $this->holderLock->forget((string)$holder);
                }
            }
        } catch (\Throwable $broken) {
            $this->journal->error('The work of holders that are gone could not be reaped: ' . $broken->getMessage(), [
                'action' => 'queue.pass_failed',
                'exception' => $broken,
            ]);
        }
    }

    /**
     * Records a message found killed, now in `failed`.
     *
     * @param array{id: int, type: string, mode: string, deliveredAt: string} $killed
     */
    private function killed(QueuePassObserver $observer, array $killed): void
    {
        $message = $this->failedMessage($killed['id']);
        $observer->killed($killed, $message instanceof ScheduledTask ? $message->taskId() : null);
    }

    /** The message a row of `failed` holds, or null when it cannot be read back. */
    private function failedMessage(int $failedId): ?object
    {
        try {
            return $this->transport->withQueue(QueueTransport::FAILED_QUEUE)->find($failedId)?->getMessage();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Dispatches the scheduled tasks that are due; a schedule that breaks is journaled and the pass goes on. */
    private function tick(): void
    {
        try {
            $this->scheduleTicker->tick();
        } catch (\Throwable $failed) {
            $this->journal->error('The schedule could not be ticked: ' . $failed->getMessage(), [
                'action' => 'queue.tick_failed',
                'exception' => $failed,
            ]);
        }
    }

    private function report(int $handled, float $startedAt, string $stopReason): QueuePassReport
    {
        return new QueuePassReport($handled, $this->remaining(), (int)round((microtime(true) - $startedAt) * 1000), $stopReason);
    }

    /** Messages still waiting; 0 when the queue cannot be read. */
    private function remaining(): int
    {
        try {
            return $this->statistics->pendingCount();
        } catch (\Throwable) {
            return 0;
        }
    }
}
