<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher as PassDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMemoryLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\Worker;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Queue\QueueClaimHolder;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassLimits;
use YesWiki\Kernel\Queue\QueuePassObserver;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Queue\QueuePassReceiver;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Queue\QueueRetryStrategy;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Service\ConfigurationWatch;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;

/**
 * The only code that takes messages from the queue and runs their handlers, in every mode, over Messenger's Worker.
 * drain() is a pass that ends by itself, never waiting; consume() blocks like `messenger:consume`, for `worker` mode.
 * Every pass first fails what dead consumers left, then takes nothing while the wiki is hibernated.
 */
class QueueConsumer
{
    public const PASS_LOCK = 'cache/queue-pass.lock';

    public const MESSAGE_KILLED = 'queue.message_killed';

    private const RECEIVER = QueueTransport::DEFAULT_QUEUE;

    private const HEARTBEAT_SECONDS = 60;

    private const STATUS_CHECK_SECONDS = 5;

    private const CONFIGURATION_CHECK_SECONDS = 5;

    private bool $watchesTheEnd = false;

    public function __construct(
        private readonly QueueTransport $transport,
        private readonly QueueBus $bus,
        private readonly QueueRecorder $recorder,
        private readonly QueueStatistics $statistics,
        private readonly QueuePassLimitsFactory $budgets,
        private readonly HibernationService $hibernation,
        private readonly RuntimeLock $runtimeLock,
        private readonly Journal $journal,
        private readonly QueueScheduleTicker $scheduleTicker,
        private readonly ClockInterface $clock,
        private readonly ConfigurationWatch $configuration,
        private readonly QueueHolderLock $holderLock,
        /** @var \Closure(): EventDispatcher */
        #[AutowireServiceClosure(EventDispatcher::class)]
        private readonly \Closure $events,
    ) {
    }

    /**
     * One pass that ends by itself: due tasks first, then every message that was there once they were dispatched, until
     * none is left, the memory is spent or PHP's limit, when it could not be lifted, is near; never throws.
     */
    public function drain(QueueConsumerMode $mode, QueuePassLimits $limits, bool $responseAlreadySent = true, QueuePassOrigin $origin = QueuePassOrigin::Consumer, ?QueueConsumerMode $fallbackFor = null): QueuePassReport
    {
        $startedAt = microtime(true);
        $lock = $this->runtimeLock->acquire(self::PASS_LOCK);

        try {
            if ($lock === null) {
                $this->journal->error('The queue pass lock ' . self::PASS_LOCK . ' cannot be created', ['action' => 'queue.pass_failed']);

                return $this->report(0, $startedAt, QueuePassReport::ERROR);
            }
            if (!$this->runtimeLock->tryLock($lock)) {
                return $this->leaveATrace($this->report(0, $startedAt, QueuePassReport::LOCKED), $mode, $limits, $responseAlreadySent, $origin);
            }
            if ($this->hibernation->isWikiHibernatedNow()) {
                $this->reapWhilePaused($mode);
                if ($this->hibernation->isWikiHibernatedNow()) {
                    return $this->leaveATrace($this->report(0, $startedAt, QueuePassReport::PAUSED), $mode, $limits, $responseAlreadySent, $origin);
                }
            }

            return $this->pass($mode, $limits, !$responseAlreadySent, $origin, $fallbackFor, function (QueuePassObserver $observer, int $passId) use ($mode, $limits): string {
                $this->transport->claimAs($mode, $limits->killLimitSeconds);
                $this->reapKilled($observer);
                $this->closeDeadPasses($passId);
                $this->tick();
                // Read after the tick, so that what it dispatched is run now; what the handlers send waits for the next pass.
                $cutoff = $this->transport->lastId();

                $lifted = false;
                $liftTheLimit = function () use ($mode, &$lifted): ?int {
                    if ($lifted) {
                        return null;
                    }
                    $lifted = true;
                    $limit = $this->budgets->liftPhpLimit();
                    if ($limit !== null) {
                        $this->transport->claimAs($mode, $limit);
                    }

                    return $limit;
                };
                $receiver = new QueuePassReceiver(
                    $this->transport,
                    function () use (&$limits, $observer, $cutoff, $liftTheLimit): ?string {
                        return $this->reasonToStop($limits, $observer, $cutoff, $liftTheLimit);
                    },
                    $observer,
                    upToId: $cutoff
                );
                // A pass that lifted PHP's limit (or never had one) may run for hours: it says it is alive, as consume() does, so that no judge closes it as killed.
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
        } finally {
            $this->runtimeLock->release($lock);
        }
    }

    /** A blocking consumer: takes messages as they come until a limit, a signal or a configuration change stops it. */
    public function consume(QueueConsumerMode $mode, int $timeLimit, int $memoryLimitBytes, ?int $messageLimit = null, ?OutputInterface $out = null): QueuePassReport
    {
        $startedAt = microtime(true);
        $limits = new QueuePassLimits($memoryLimitBytes, QueuePassLimitsFactory::KILL_LIMIT_NO_PHP_LIMIT);

        return $this->pass($mode, $limits, false, QueuePassOrigin::Consumer, null, function (QueuePassObserver $observer, int $passId) use ($mode, $limits, $timeLimit, $memoryLimitBytes, $messageLimit, $out, $startedAt): string {
            $logger = $out === null ? null : new ConsoleLogger($out);
            $this->transport->claimAs($mode, $limits->killLimitSeconds);
            $this->reapKilled($observer);
            $this->closeDeadPasses($passId);
            $hibernated = $this->hibernation->isWikiHibernatedNow();
            $statusReadAt = microtime(true);
            $isHibernated = function () use (&$hibernated, &$statusReadAt): bool {
                if (microtime(true) - $statusReadAt >= self::STATUS_CHECK_SECONDS) {
                    $hibernated = $this->hibernation->isWikiHibernatedNow();
                    $statusReadAt = microtime(true);
                }

                return $hibernated;
            };
            $configurationChanged = $this->configurationChangedEveryFewSeconds();
            if (!$isHibernated()) {
                $this->tick();
            }

            $receiver = new QueuePassReceiver(
                $this->transport,
                static fn (): ?string => match (true) {
                    $configurationChanged() => QueuePassReport::CONFIG_CHANGED,
                    $isHibernated() => QueuePassReport::PAUSED,
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

            $signalled = $this->work($receiver, $observer, $logger, function (WorkerRunningEvent $event) use ($observer, $passId, &$lastBeat, $isHibernated, $receiver): void {
                if ($receiver->stopReason() === QueuePassReport::CONFIG_CHANGED) {
                    $event->getWorker()->stop();

                    return;
                }
                if ($event->isWorkerIdle() && !$isHibernated()) {
                    $this->tick();
                }
                if (microtime(true) - $lastBeat >= self::HEARTBEAT_SECONDS) {
                    $lastBeat = microtime(true);
                    $this->recorder->passHeartbeat($passId, $observer->handled(), memory_get_peak_usage(true));
                    $this->reapKilled($observer);
                    $this->closeDeadPasses($passId);
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

    /** Runs that message now, whatever the limits and even while hibernated: for a message pinned to its sender's request. */
    public function handleById(int $messageId, QueueConsumerMode $mode): QueuePassReport
    {
        return $this->pass($mode, null, false, QueuePassOrigin::Pinned, null, function (QueuePassObserver $observer) use ($messageId, $mode): string {
            $this->transport->claimAs($mode, QueuePassLimitsFactory::KILL_LIMIT_NO_PHP_LIMIT);

            $receiver = new QueuePassReceiver($this->transport, static fn (): ?string => null, $observer, $messageId);
            $this->work($receiver, $observer, null, self::stopWhenIdle(...));

            return QueuePassReport::EMPTY;
        });
    }

    /** The row of a consumer's pass that did nothing (lock taken, wiki hibernated), closed at once, outside `request`. */
    private function leaveATrace(QueuePassReport $report, QueueConsumerMode $mode, QueuePassLimits $budget, bool $responseAlreadySent, QueuePassOrigin $origin): QueuePassReport
    {
        if ($origin === QueuePassOrigin::Consumer && !$mode->consumesAtEndOfRequests()) {
            try {
                $this->recorder->passFinished($this->recorder->passStarted($mode, $budget, !$responseAlreadySent), $report, memory_get_peak_usage(true));
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
    private function pass(QueueConsumerMode $mode, ?QueuePassLimits $budget, bool $beforeResponse, QueuePassOrigin $origin, ?QueueConsumerMode $fallbackFor, \Closure $body): QueuePassReport
    {
        $startedAt = microtime(true);
        $observer = new QueuePassObserver($this->recorder, $this->journal, $mode, $this->clock);
        $passId = null;
        $holder = QueueClaimHolder::thisProcess()->token();
        $this->failWhatItHoldsWhenTheProcessEnds();

        try {
            try {
                // Taken before the row names it, so a judge never finds the file of a pass that has not locked it yet.
                $this->holderLock->hold($holder);
                $passId = $this->recorder->passStarted($mode, $budget, $beforeResponse, $origin, $fallbackFor, $holder);
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
     * Why a pass that ends by itself should take no further message, or null to take the next one. Before its first, it
     * lifts PHP's limit, a visitor waiting or not; when the host keeps that limit, the pass stops short of it.
     *
     * @param \Closure(): ?int $liftTheLimit the kill limit to claim by once PHP's limit is gone, null when it holds or was tried already
     */
    private function reasonToStop(QueuePassLimits &$limits, QueuePassObserver $observer, int $cutoff, \Closure $liftTheLimit): ?string
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
            $limit = $liftTheLimit();
            if ($limit !== null) {
                $limits = $limits->lifted($limit);
            }
        }
        if ($limits->phpLimitReached()) {
            return QueuePassReport::PHP_LIMIT;
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
        $askedAt = $this->now();

        return function () use (&$changed, &$askedAt): bool {
            if (!$changed && $this->now() - $askedAt >= self::CONFIGURATION_CHECK_SECONDS) {
                $askedAt = $this->now();
                $changed = $this->configuration->changedSinceBoot();
            }

            return $changed;
        };
    }

    private function now(): float
    {
        return (float)$this->clock->now()->format('U.u');
    }

    /** Moves to `failed`, as killed, what this consumer still holds, records it and tells the wiki; never throws. */
    public function failWhatItHolds(): void
    {
        try {
            $observer = new QueuePassObserver($this->recorder, $this->journal, QueueConsumerMode::Request, $this->clock);
            foreach ($this->transport->failHeldClaims() as $killed) {
                $this->killed($observer, $killed);
            }
        } catch (\Throwable $broken) {
            try {
                $this->journal->error('The messages a consumer held as its process ended could not be failed: ' . $broken->getMessage(), [
                    'action' => 'queue.pass_failed',
                    'exception' => $broken,
                ]);
            } catch (\Throwable) {
            }
        }
    }

    /** Has PHP call failWhatItHolds() when the process ends, once per consumer, through a weak reference. */
    private function failWhatItHoldsWhenTheProcessEnds(): void
    {
        if ($this->watchesTheEnd) {
            return;
        }
        $this->watchesTheEnd = true;
        $consumer = \WeakReference::create($this);
        register_shutdown_function(static function () use ($consumer): void {
            $consumer->get()?->failWhatItHolds();
        });
    }

    /** Moves to `failed` what dead consumers left in progress, records each as killed, and tells the wiki. */
    private function reapKilled(QueuePassObserver $observer): void
    {
        foreach ($this->transport->reapKilled() as $killed) {
            $this->killed($observer, $killed);
        }
    }

    /**
     * Records a message found killed, now in `failed`, and tells the wiki.
     *
     * @param array{id: int, type: string, mode: string, deliveredAt: string} $killed
     */
    private function killed(QueuePassObserver $observer, array $killed): void
    {
        $message = $this->failedMessage($killed['id']);
        $observer->killed($killed, $message instanceof ScheduledTask ? $message->taskId() : null);
        $this->announceKilled($killed, $message);
    }

    /** What a paused pass still does: reap and close dead passes, which change no content (R18); no pass row, and a failure is only journaled. */
    private function reapWhilePaused(QueueConsumerMode $mode): void
    {
        try {
            $this->reapKilled(new QueuePassObserver($this->recorder, $this->journal, $mode, $this->clock));
        } catch (\Throwable $broken) {
            $this->journal->error('The killed messages could not be reaped while the wiki is paused: ' . $broken->getMessage(), [
                'action' => 'queue.pass_failed',
                'target' => $mode->value,
                'exception' => $broken,
            ]);
        }
        $this->closeDeadPasses(null);
    }

    /** Closes as killed the open passes, but its own, whose holder is gone, or that nothing tells about and no process could still run; a failure is only journaled. */
    private function closeDeadPasses(?int $ownPassId): void
    {
        try {
            $longestLife = QueuePassLimitsFactory::KILL_LIMIT_NO_PHP_LIMIT + QueueTransport::KILL_GRACE_SECONDS;
            $lastPossible = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->modify("-{$longestLife} seconds");
            foreach ($this->statistics->openPasses() as $pass) {
                if ($pass['id'] === $ownPassId) {
                    continue;
                }
                $state = $pass['holder'] === '' ? QueueClaimHolder::UNKNOWN : $this->transport->holderState($pass['holder']);
                if ($state === QueueClaimHolder::GONE) {
                    $this->recorder->passKilled($pass['id']);
                    $this->holderLock->forget($pass['holder']);
                } elseif ($state === QueueClaimHolder::UNKNOWN && $pass['lastAt'] < $lastPossible) {
                    $this->recorder->passKilled($pass['id']);
                }
            }
        } catch (\Throwable $broken) {
            $this->journal->error('The passes whose process is gone could not be closed: ' . $broken->getMessage(), [
                'action' => 'queue.pass_failed',
                'exception' => $broken,
            ]);
        }
    }

    /**
     * Tells the wiki's dispatcher that a dead consumer left that message, now in `failed`, each listener on its own.
     *
     * @param array{id: int, type: string, mode: string, deliveredAt: string} $killed
     */
    private function announceKilled(array $killed, ?object $message): void
    {
        $event = new Event(['messageId' => $killed['id'], 'type' => $killed['type'], 'mode' => $killed['mode'], 'message' => $message]);

        try {
            $dispatcher = ($this->events)();
            $dispatcher->dispatchToEach(self::MESSAGE_KILLED, $event, $this->listenerFailed(...));
        } catch (\Throwable $broken) {
            $this->listenerFailed(EventDispatcher::class, $broken);
        }
    }

    private function listenerFailed(string $name, \Throwable $failed): void
    {
        $this->journal->error('A ' . self::MESSAGE_KILLED . " listener ({$name}) failed: {$failed->getMessage()}", [
            'action' => 'queue.listener_failed',
            'target' => $name,
            'exception' => $failed,
        ]);
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
