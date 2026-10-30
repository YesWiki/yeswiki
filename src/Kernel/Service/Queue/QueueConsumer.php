<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
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
use YesWiki\Kernel\Queue\QueueBudget;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassObserver;
use YesWiki\Kernel\Queue\QueuePassReceiver;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Queue\QueueRetryStrategy;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Service\ConfigurationWatch;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;

/**
 * The only code that takes messages from the queue and runs their handlers, in every mode (messenger / scheduler).
 *
 * Two ways to run, one machinery: drain() is a pass that has to end by itself -- at the end of a
 * request, from the non-blocking command, the API route or the process `request-spawn` starts --
 * and stops when the queue is empty or the budget spent, never waiting; consume() blocks like
 * `messenger:consume`, for the `worker` and `binary` modes. Both build Messenger's Worker over
 * the QueueBus (whose handlers come from QueueHandlerLocator, the one way a handler is found),
 * with Messenger's own retry and failure-transport listeners, and a QueuePassObserver that writes
 * the history; all of it on an event dispatcher of the pass's own, so that nothing a module
 * subscribes to the wiki's dispatcher runs inside the Worker, and nothing of a pass outlives it.
 *
 * Every pass starts by moving to `failed` the messages a dead consumer left behind (killed by
 * PHP's time limit, the OOM killer), and stops there while the wiki is hibernated, archiving or
 * updating: a backup must not see the wiki change under it. Reaping changes no content, so a
 * paused pass still does it, and each message reaped is announced on the wiki's own dispatcher
 * as `queue.message_killed` (MESSAGE_KILLED): a backup PHP killed holds the wiki `archiving`,
 * and that is how its mechanism learns it died; once it lifted the status, the pass goes on. The
 * status is read from the config file as it is now (HibernationService::isWikiHibernatedNow()),
 * since an archive or an update sets it there while a pass or a blocking consumer is already
 * running.
 *
 * A consumer that dies in the middle of a message does not always leave it to the next pass: the
 * first pass of a process has PHP fail, as killed, whatever it still holds when the process ends
 * (R19), so a fatal error, PHP's time limit or an `exit` in a handler is seen at once, even in an
 * FPM child or a FrankenPHP worker that lives on after it.
 */
class QueueConsumer
{
    /** One budgeted pass at a time per wiki; blocking consumers do not take it, the claim keeps them apart. */
    public const PASS_LOCK = 'cache/queue-pass.lock';

    /** What the wiki's EventDispatcher is told each time a pass finds a message a dead consumer left, once it is in `failed`. */
    public const MESSAGE_KILLED = 'queue.message_killed';

    /** The receiver's name in the Worker, under which the retry and failure listeners find their senders. */
    private const RECEIVER = QueueTransport::DEFAULT_QUEUE;

    /** What a message is assumed to take before one of its type has ever succeeded. */
    private const UNKNOWN_DURATION_SECONDS = 0.5;

    /** How often a blocking consumer says it is alive and looks for killed messages. */
    private const HEARTBEAT_SECONDS = 60;

    /** How often a blocking consumer reads the wiki's status again: the config file is parsed at each read, and the Worker loops every second when idle. */
    private const STATUS_CHECK_SECONDS = 5;

    /** How often a blocking consumer asks whether the configuration changed since it started: the question hashes a few files. */
    private const CONFIGURATION_CHECK_SECONDS = 5;

    private ClockInterface $clock;

    /** Whether this consumer has asked PHP to fail what it still holds when the process ends. */
    private bool $watchesTheEnd = false;

    public function __construct(
        private readonly QueueTransport $transport,
        private readonly QueueBus $bus,
        private readonly QueueRecorder $recorder,
        private readonly QueueStatistics $statistics,
        private readonly QueueBudgetContext $budgetContext,
        private readonly QueueBudgetFactory $budgets,
        private readonly HibernationService $hibernation,
        private readonly RuntimeLock $runtimeLock,
        private readonly Journal $journal,
        private readonly QueueScheduleTicker $scheduleTicker,
        ?ClockInterface $clock = null,
        private readonly ?ConfigurationWatch $configuration = null,
        private readonly ?ContainerInterface $services = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
    }

    /**
     * One pass that ends by itself: due tasks first, then messages until the queue is empty or the budget spent.
     *
     * Before each message it checks the memory, and whether that message still fits in the time
     * left, judging by how long its type took on average (half a second when it never ran). The
     * first message of a pass nobody waits for is taken whatever its average, as long as any time
     * is left: a type that takes longer than any pass of its mode would otherwise stay at the head
     * of the queue for good, and everything behind it with it. If PHP stops it, the next pass finds
     * it killed and fails it with the advice to change mode (§7.2), which is what the admin needs
     * to hear. A visitor who waits for the page is never made to wait for such a message.
     * Never throws: a pass that breaks (table missing, database gone) is journaled and reported
     * as `error`, since it often runs at the end of a visitor's request.
     *
     * A consumer's pass that finds the lock taken, or the wiki hibernated, still leaves a row,
     * closed at once, outside `request` (leaveATrace()).
     *
     * @param bool                   $tickSchedule        whether to dispatch the scheduled tasks that are due before consuming
     * @param bool                   $responseAlreadySent whether the visitor already has the response: when not, even the first message must fit its average; recorded, so that the cost of a pass a visitor waited for shows
     * @param QueueConsumerMode|null $fallbackFor         the consumer a fallback pass stands in for
     */
    public function drain(QueueConsumerMode $mode, QueueBudget $budget, bool $tickSchedule = true, bool $responseAlreadySent = true, QueuePassOrigin $origin = QueuePassOrigin::Consumer, ?QueueConsumerMode $fallbackFor = null): QueuePassReport
    {
        $startedAt = microtime(true);
        $lock = $this->runtimeLock->acquire(self::PASS_LOCK);

        try {
            if ($lock === null) {
                // Not "locked": nobody holds it, it cannot be created (cache/ not writable), and no pass would ever run.
                $this->journal->error('The queue pass lock ' . self::PASS_LOCK . ' cannot be created', ['action' => 'queue.pass_failed']);

                return $this->report(0, $startedAt, QueuePassReport::ERROR);
            }
            if (!$this->runtimeLock->tryLock($lock)) {
                return $this->leaveATrace($this->report(0, $startedAt, QueuePassReport::LOCKED), $mode, $budget, $responseAlreadySent, $origin);
            }
            if ($this->hibernation->isWikiHibernatedNow()) {
                $this->reapWhilePaused($mode);
                // A killed backup's listener sets the wiki back to running: then the pass is not paused any more.
                if ($this->hibernation->isWikiHibernatedNow()) {
                    return $this->leaveATrace($this->report(0, $startedAt, QueuePassReport::PAUSED), $mode, $budget, $responseAlreadySent, $origin);
                }
            }

            return $this->pass($mode, $budget, !$responseAlreadySent, $origin, $fallbackFor, function (QueuePassObserver $observer) use ($mode, $budget, $tickSchedule, $responseAlreadySent): string {
                $this->budgetContext->start($budget);
                try {
                    $this->transport->claimAs($mode, $budget->killLimitSeconds);
                    $this->reapKilled($observer);
                    if ($tickSchedule) {
                        $this->tick();
                    }

                    $averages = [];
                    $receiver = new QueuePassReceiver(
                        $this->transport,
                        function () use ($budget, $observer, $responseAlreadySent, &$averages): ?string {
                            return $this->reasonToStop($budget, $observer, !$responseAlreadySent, $averages);
                        },
                        $observer
                    );
                    $this->work($receiver, $observer, null, self::stopWhenIdle(...));

                    return $receiver->stopReason() ?? QueuePassReport::EMPTY;
                } finally {
                    $this->budgetContext->end();
                }
            });
        } finally {
            $this->runtimeLock->release($lock);
        }
    }

    /**
     * A blocking consumer: takes messages as they come, waits when there are none, until a limit or a signal stops it.
     *
     * It keeps one pass row in the Journal for its whole life and moves it forward every minute, when
     * it also reaps killed messages; the scheduled tasks are ticked whenever it is idle. While the
     * wiki is hibernated it stays up but takes nothing: exiting would only have its supervisor
     * restart it in a loop.
     *
     * Its container, and every parameter a handler or its own schedule reads, is the configuration
     * it started on, and its classes the code that was there then. Once either changed (a data
     * source added, mail settings saved, `private/.env` edited; an upgrade's `migrate` or a
     * `cache:clear` emptied the container cache: the ConfigurationWatch says so, as it says it to
     * FrankenPHP's worker), it takes no further message and stops with `config-changed`, for
     * systemd or the binary to start a consumer that knows the new one. Setting the wiki's status rewrites the config file too, so
     * a hibernation or a backup restarts it once more: the new one then idles as said above.
     *
     * @param int      $timeLimit        seconds after which it stops
     * @param int      $memoryLimitBytes memory use after which it stops
     * @param int|null $messageLimit     messages after which it stops; null for no such limit
     */
    public function consume(QueueConsumerMode $mode, int $timeLimit, int $memoryLimitBytes, ?int $messageLimit = null, ?OutputInterface $out = null): QueuePassReport
    {
        $startedAt = microtime(true);
        $limits = new QueueBudget(INF, $memoryLimitBytes, $this->budgets->forMode($mode, true)->killLimitSeconds);

        return $this->pass($mode, $limits, false, QueuePassOrigin::Consumer, null, function (QueuePassObserver $observer, int $passId) use ($mode, $limits, $timeLimit, $memoryLimitBytes, $messageLimit, $out, $startedAt): string {
            $logger = $out === null ? null : new ConsoleLogger($out);
            $this->transport->claimAs($mode, $limits->killLimitSeconds);
            $this->reapKilled($observer);
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
                    // Before any tick: a schedule read from the old configuration would prune the tasks it does not declare.
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

    /**
     * Runs that message now, whatever the budget and even while the wiki is hibernated: for a message its sender pinned to its own request (a backup archive, which sets the wiki to `archiving` itself).
     *
     * The message is claimed like any other, so a consumer running at the same moment cannot take
     * it too; one already taken, handled or failed is left alone. No schedule tick, no reaping: this
     * is not a pass over the queue, only that message.
     *
     * Its claim is held to no PHP limit: its sender lifted it (set_time_limit(0)), and where a host
     * disabled that, the limit PHP keeps counts CPU time, which a backup waiting on the disk or the
     * network outlives. Judged by it, a pass in another process -- this one holds no pass lock --
     * would fail the message as killed while it runs. A holder that really dies is still seen at
     * once: its process is gone, or it fails what it holds as it ends.
     */
    public function handleById(int $messageId, QueueConsumerMode $mode, QueuePassOrigin $origin = QueuePassOrigin::Pinned): QueuePassReport
    {
        return $this->pass($mode, null, false, $origin, null, function (QueuePassObserver $observer) use ($messageId, $mode): string {
            $this->transport->claimAs($mode, QueueBudgetFactory::KILL_LIMIT_NO_PHP_LIMIT);

            $receiver = new QueuePassReceiver($this->transport, static fn (): ?string => null, $observer, $messageId);
            $this->work($receiver, $observer, null, self::stopWhenIdle(...));

            return QueuePassReport::EMPTY;
        });
    }

    /**
     * The row of a consumer's pass that did nothing -- the lock taken, the wiki hibernated --, closed at once, outside `request`.
     *
     * A cron, an API call or a spawned process that comes back while the end of a request consumes
     * in its place, or that kept running while the wiki slept, has to be seen (QueueFallback): or
     * it is taken for gone, and the end of requests stands in for it. In `request` that place is its
     * own, and a busy wiki's requests finding each other's lock, or a hibernated wiki's visits,
     * would only fill the Journal.
     */
    private function leaveATrace(QueuePassReport $report, QueueConsumerMode $mode, QueueBudget $budget, bool $responseAlreadySent, QueuePassOrigin $origin): QueuePassReport
    {
        if ($origin === QueuePassOrigin::Consumer && $mode !== QueueConsumerMode::Request) {
            try {
                $this->recorder->passFinished($this->recorder->passStarted($mode, $budget, !$responseAlreadySent), $report, memory_get_peak_usage(true));
            } catch (\Throwable) {
                // Nothing was done to be lost with it; the next pass that runs says whether the database is gone.
            }
        }

        return $report;
    }

    /**
     * Opens the pass's row, runs it, closes the row with the report.
     *
     * @param QueueConsumerMode|null                  $fallbackFor the consumer a fallback pass stands in for
     * @param \Closure(QueuePassObserver, int): string $body        runs the pass and answers why it stopped
     */
    private function pass(QueueConsumerMode $mode, ?QueueBudget $budget, bool $beforeResponse, QueuePassOrigin $origin, ?QueueConsumerMode $fallbackFor, \Closure $body): QueuePassReport
    {
        $startedAt = microtime(true);
        $observer = new QueuePassObserver($this->recorder, $this->journal, $mode, $this->clock);
        $passId = null;
        $this->failWhatItHoldsWhenTheProcessEnds();

        try {
            $passId = $this->recorder->passStarted($mode, $budget, $beforeResponse, $origin, $fallbackFor);
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
                // The database is what broke the pass, and that is journaled already.
            }
        }

        return $report;
    }

    /**
     * Runs a Worker over the receiver until it stops, with the retry and failure listeners on a dispatcher of its own.
     *
     * @param \Closure(WorkerRunningEvent): void $onRunning     after each message, and each time the queue is found empty
     * @param list<EventSubscriberInterface>     $stopListeners Messenger's StopWorkerOn*Listener the Worker obeys
     *
     * @return bool whether a signal (SIGTERM, SIGINT) stopped it
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

    /**
     * Runs the worker; for a blocking consumer, SIGTERM and SIGINT let the message in hand finish instead of killing it (which would leave it "running" until it is reaped as killed hours later).
     *
     * @return bool whether a signal stopped it
     */
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

    /** A pass that ends with the queue: the worker stops the first time it finds nothing to take. */
    private static function stopWhenIdle(WorkerRunningEvent $event): void
    {
        if ($event->isWorkerIdle()) {
            $event->getWorker()->stop();
        }
    }

    /**
     * Why a budgeted pass should take no further message, or null to take the next one.
     *
     * @param bool                    $visitorWaits whether a visitor waits for the pass to end: only then is the first message held to its average duration too
     * @param array<string, int|null> $averages     the average durations this pass already read, by type: a week of runs does not move while one pass runs, and the query reads them all (one still unknown is asked again, its first success may have come in this pass)
     */
    private function reasonToStop(QueueBudget $budget, QueuePassObserver $observer, bool $visitorWaits, array &$averages): ?string
    {
        if ($this->hibernation->isWikiHibernatedNow()) {
            return QueuePassReport::PAUSED;
        }
        if ($budget->memoryExhausted()) {
            return QueuePassReport::MEMORY;
        }

        $type = $this->transport->peekNextType();
        if ($type === null) {
            return QueuePassReport::EMPTY;
        }

        $first = $observer->handled() === 0;
        if ($budget->remainingSeconds() <= 0.0) {
            // The request spent the budget before the pass began (§7.2): nothing to start, not even the first message.
            return $first ? QueuePassReport::NO_BUDGET : QueuePassReport::BUDGET;
        }
        if ($first && !$visitorWaits) {
            return null;
        }

        $averageMs = $averages[$type] ??= $this->statistics->averageDurationMs($type);
        $expected = $averageMs === null ? self::UNKNOWN_DURATION_SECONDS : $averageMs / 1000;
        if (!$budget->canAfford($expected)) {
            return $first ? QueuePassReport::NO_BUDGET : QueuePassReport::BUDGET;
        }

        return null;
    }

    /**
     * Whether the configuration changed since this consumer started, asked of the ConfigurationWatch at most every few seconds and true for good once it was.
     *
     * Not asked at the start: the process just booted on the configuration as it is. The spacing
     * is on the consumer's clock, so that a test can move it. A message dispatched in the few
     * seconds after a change can still be taken under the old configuration.
     *
     * @return \Closure(): bool
     */
    private function configurationChangedEveryFewSeconds(): \Closure
    {
        $changed = false;
        $askedAt = $this->now();

        return function () use (&$changed, &$askedAt): bool {
            if (!$changed && $this->configuration !== null && $this->now() - $askedAt >= self::CONFIGURATION_CHECK_SECONDS) {
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

    /**
     * Moves to `failed`, as killed, the messages this consumer still holds, records them and tells the wiki: what PHP runs when the process ends.
     *
     * Nothing to do after a pass that ended as it should, every message acked. Never throws: the
     * process is ending, and a failure here only leaves the claims to the next pass.
     */
    public function failWhatItHolds(): void
    {
        try {
            // Each claim is recorded under the mode it was taken in; Request only stands for a claim that recorded none.
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
                // The database is gone with the process; the next pass reaps them.
            }
        }
    }

    /**
     * Has PHP call failWhatItHolds() when the process ends, once per consumer.
     *
     * Through a weak reference, so that the consumer is not kept alive for it. PHP runs no further
     * shutdown function once one has died of a fatal error: a pass that runs in one (the end of
     * an FPM or mod_php request) and dies of one leaves its claim to the transport, which tells it
     * dead from the next request this process serves, or by the time rule.
     */
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

    /**
     * What a paused pass still does: reap, which changes no content (R18).
     *
     * Not a pass of its own (no row: a hibernated wiki in `request` mode would write one per page
     * view), and a failure is journaled without changing what the pass answers: the wiki is paused.
     */
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
    }

    /**
     * Tells the wiki's dispatcher that a dead consumer left that message, now in `failed`: how the code that followed it (a backup holding the wiki `archiving`) learns that it died.
     *
     * The Event carries `messageId`, `type` (its class), `mode` (the one it was killed in) and
     * `message` (null when it cannot be read back). Each listener is called on its own
     * (EventDispatcher::dispatchToEach()): one that throws is journaled under
     * `queue.listener_failed`, and stops neither the ones after it nor the pass. The dispatcher
     * is only asked for here, since building it builds every subscriber of the wiki.
     *
     * @param array{id: int, type: string, mode: string, deliveredAt: string} $killed
     */
    private function announceKilled(array $killed, ?object $message): void
    {
        if ($this->services === null) {
            return;
        }
        $event = new Event(['messageId' => $killed['id'], 'type' => $killed['type'], 'mode' => $killed['mode'], 'message' => $message]);

        try {
            $dispatcher = $this->services->get(EventDispatcher::class);
            $dispatcher->dispatchToEach(self::MESSAGE_KILLED, $event, $this->listenerFailed(...));
        } catch (\Throwable $broken) {
            // Building the dispatcher, or the listeners it resolves, failed: none of them was called.
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

    /**
     * Dispatches the scheduled tasks that are due.
     *
     * A schedule that breaks (a checkpoint that cannot be written; a provider that throws, the
     * ticker leaves out itself) is journaled and the pass goes on: the messages already queued do
     * not depend on it. The ticker waits a minute before trying a broken schedule again, so a
     * blocking consumer that ticks at every idle second does not journal it every second.
     */
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

    /** Messages still waiting; 0 when the queue cannot be read, which the pass's own report already says. */
    private function remaining(): int
    {
        try {
            return $this->statistics->pendingCount();
        } catch (\Throwable) {
            return 0;
        }
    }
}
