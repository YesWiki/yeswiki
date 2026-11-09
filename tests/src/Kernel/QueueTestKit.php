<?php

namespace YesWiki\Test\Kernel;

use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassLimits;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\ConfigurationWatch;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueueFallback;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueHealthChecks;
use YesWiki\Kernel\Service\Queue\QueueHolderLock;
use YesWiki\Kernel\Service\Queue\QueuePassLimitsFactory;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueRequestTrigger;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\Queue\ScheduleHistory;
use YesWiki\Kernel\Service\ThrowableFormatter;
use YesWiki\Kernel\Service\TripleStore;

require_once 'tests/src/Kernel/QueueTestMessage.php';
require_once 'tests/src/Kernel/QueueTestHolders.php';

/**
 * A queue and its consumer on a throw-away SQLite file, with a handler that notes the messages it gets, for the tests of what sits in front of the consumer (commands, API route).
 *
 * Its locks and its config file are its own, so that a test never touches the dev wiki's cache/ nor its status.
 */
final class QueueTestKit
{
    public readonly QueueConsumer $consumer;
    public readonly QueueStatistics $statistics;
    public readonly QueueTransport $transport;
    public readonly QueueRecorder $recorder;
    public readonly QueueScheduleTicker $ticker;
    public readonly Journal $journal;
    public readonly QueueBus $bus;
    public readonly DbService $db;
    public readonly QueueSchema $schema;
    public readonly RuntimeLock $locks;
    /** The locks the transport holds for its claims and the consumer for its passes, in the kit's own directory. */
    public readonly QueueHolderLock $holderLock;
    /** The one clock every part of the queue reads. */
    public readonly ClockInterface $clock;
    public readonly QueuePassLimitsFactory $passLimits;
    private readonly HibernationService $hibernation;
    /** What the history says of each scheduled task, which the Scheduler reads. */
    public readonly ScheduleHistory $history;
    /** The end of the request the bus pins a message to (RunsForItsSender): the last one requestTrigger() built. */
    private ?QueueRequestTrigger $trigger = null;
    /** @var list<string> the values of the messages handled, in order */
    public array $handled = [];

    /** What the consumer's ConfigurationWatch answers when the consumer asks. */
    public bool $configurationChanged = false;

    private readonly string $file;
    /** Whether the kit made its database, and removes it with the rest; not when it was given one. */
    private readonly bool $ownsDatabase;
    public readonly string $lockDir;
    private readonly string $configFile;
    private readonly string|false $previousConfigFile;

    /**
     * @param array<class-string, \Closure> $handlers             further messages the queue handles, by class, each with the closure that handles it; one for QueueTestMessage replaces the kit's
     * @param ClockInterface|null           $clock                the clock of every part of the queue (default: PHP's own, as in production)
     * @param string|null                   $database             an SQLite file another kit or test made, for a consumer in another process to work on the same queue; left where it is by dispose()
     * @param ProvidesSchedule|null         $schedule             the recurring tasks the schedule declares (default: none)
     * @param QueuePassLimitsFactory|null   $passLimits           what a pass may spend (default: from this PHP's limits)
     * @param HibernationService|null       $hibernation          the wiki's status as the consumer reads it (default: awake, its archive lock in the kit's directory)
     * @param \Closure(): bool|null         $configurationChanged what the consumer's ConfigurationWatch answers (default: $this->configurationChanged)
     */
    public function __construct(
        array $handlers = [],
        ?ClockInterface $clock = null,
        ?string $database = null,
        ?ProvidesSchedule $schedule = null,
        ?QueuePassLimitsFactory $passLimits = null,
        ?HibernationService $hibernation = null,
        ?\Closure $configurationChanged = null,
    ) {
        $this->file = $database ?? sys_get_temp_dir() . '/yw-kit-' . bin2hex(random_bytes(6)) . '.db';
        $this->ownsDatabase = $database === null;
        $this->lockDir = sys_get_temp_dir() . '/yw-kit-locks-' . bin2hex(random_bytes(4));
        $this->db = $db = new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywq_',
            'debug' => false,
        ]));
        $this->clock = $clock ??= new NativeClock();
        $this->schema = $schema = new QueueSchema($db);
        $schema->create();
        (new JournalSchema($db))->create();

        $this->configFile = sys_get_temp_dir() . '/yw-kit-config-' . bin2hex(random_bytes(4)) . '.php';
        $this->previousConfigFile = getenv('YESWIKI_CONFIG_FILE');
        putenv('YESWIKI_CONFIG_FILE=' . $this->configFile);
        $configuration = new ConfigurationService();
        $config = $configuration->getConfiguration($this->configFile);
        $config->load();
        $config['wiki_status'] = 'running';
        $configuration->write($config);

        $this->locks = $locks = self::locksIn($this->lockDir);
        $this->holderLock = new QueueHolderLock($locks);
        $this->transport = new QueueTransport($db, $schema, $clock, holderLock: $this->holderLock);
        $this->statistics = new QueueStatistics($db, $schema, $this->transport, $clock);
        $this->journal = $journal = new Journal(new ServiceLocator([
            DbService::class => static fn (): DbService => $db,
            JournalSchema::class => static fn (): JournalSchema => new JournalSchema($db),
            ThrowableFormatter::class => static fn (): ThrowableFormatter => new ThrowableFormatter(),
        ]));
        $log = fopen('php://memory', 'w+b');
        if ($log !== false) {
            $journal->writeTo($log);
        }

        $handlers += [QueueTestMessage::class => function (QueueTestMessage $message): void {
            $this->handled[] = $message->value;
        }];
        $map = [];
        $services = [];
        foreach ($handlers as $type => $handler) {
            $map[$type] = ["handler:{$type}"];
            $services["handler:{$type}"] = static fn (): \Closure => $handler;
        }
        $this->recorder = $recorder = new QueueRecorder($db, new QueueSchema($db), $clock);
        $this->bus = $bus = new QueueBus(
            $this->transport,
            new QueueHandlerLocator($map, new ServiceLocator($services)),
            $journal,
            $recorder,
            fn (): QueueRequestTrigger => $this->trigger ?? $this->requestTrigger(new QueueSettings(new ParameterBag([]))),
        );
        $this->history = new ScheduleHistory($db, new QueueSchema($db));
        $schedule ??= new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [];
            }
        };

        $watch = new ConfigurationWatch();
        $watch->bind($configurationChanged ?? fn (): bool => $this->configurationChanged);

        // On the real clock whatever the queue's: PHP's limit is read against microtime() (QueuePassLimits).
        $this->passLimits = $passLimits ??= new QueuePassLimitsFactory(new NativeClock());
        $this->hibernation = $hibernation ??= new HibernationService(new ParameterBag(['wiki_status' => 'running']), $locks);
        $this->consumer = new QueueConsumer(
            $this->transport,
            $bus,
            $recorder,
            $this->statistics,
            $passLimits,
            $hibernation,
            $journal,
            $this->ticker = new QueueScheduleTicker([$schedule], $bus, $this->history, $recorder, $locks, $clock, $hibernation, $journal),
            $clock,
            $watch,
            $this->holderLock,
        );
    }

    /** The settings of a wiki whose `queue_consumer` parameter says $mode (the environment having been resolved into it). */
    public static function settings(string $mode = '', string $secret = ''): QueueSettings
    {
        return new QueueSettings(new ParameterBag(['queue_consumer' => $mode, 'queue_consume_secret' => $secret]));
    }

    /** Puts the process's environment back and removes the files; the kit is of no use after. */
    public function dispose(): void
    {
        putenv($this->previousConfigFile === false ? 'YESWIKI_CONFIG_FILE' : 'YESWIKI_CONFIG_FILE=' . $this->previousConfigFile);
        @unlink($this->configFile);
        foreach ($this->ownsDatabase ? ['', '-wal', '-shm'] : [] as $suffix) {
            @unlink($this->file . $suffix);
        }
        foreach (glob($this->lockDir . '/' . QueueHolderLock::DIRECTORY . '/*') ?: [] as $holder) {
            @unlink($holder);
        }
        @rmdir($this->lockDir . '/' . QueueHolderLock::DIRECTORY);
        foreach (glob($this->lockDir . '/cache/*') ?: [] as $lock) {
            @unlink($lock);
        }
        @rmdir($this->lockDir . '/cache');
        @rmdir($this->lockDir);
    }

    /**
     * A run of a message as QueueRecorder writes it in the history, as if it had ended at $finishedAt (UTC) after $durationMs.
     */
    public static function recordRun(DbService $db, string $type, string $status, int $durationMs, string $finishedAt, int $messageId = 0, QueueConsumerMode $mode = QueueConsumerMode::Request): void
    {
        $clock = new MockClock(new \DateTimeImmutable($finishedAt));
        $startedAt = (float)$clock->now()->format('U.u') - $durationMs / 1000;

        (new QueueRecorder($db, new QueueSchema($db), $clock))
            ->messageFinished($messageId, $type, null, $status, $mode, $startedAt, null);
    }

    /**
     * A pass as QueueRecorder writes it in the history, as if it had started at $startedAt and was last seen at $lastSeenAt (both in local time).
     *
     * @param string      $stopReason  why it ended; '' leaves it open, its last heartbeat at $lastSeenAt
     * @param int         $memoryLimit the memory use it stops at, 0 for none
     * @param string|null $holder      the token of the process that ran it, whose lock tells whether it is at work; none when null
     *
     * @return int the id of its row
     */
    public static function recordPass(
        DbService $db,
        QueueConsumerMode $mode,
        string $startedAt,
        string $lastSeenAt,
        string $stopReason,
        int $peakMemory = 0,
        int $memoryLimit = 0,
        bool $beforeResponse = false,
        ?string $holder = null,
    ): int {
        $clock = new MockClock(new \DateTimeImmutable($startedAt));
        $recorder = new QueueRecorder($db, new QueueSchema($db), $clock);

        $id = $recorder->passStarted($mode, $memoryLimit === 0 ? null : new QueuePassLimits($memoryLimit), $beforeResponse, $holder);
        $clock->modify($lastSeenAt);
        if ($stopReason === '') {
            $recorder->passHeartbeat($id, 0, $peakMemory);
        } else {
            $recorder->passFinished($id, new QueuePassReport(0, 0, 0, $stopReason), $peakMemory);
        }

        return $id;
    }

    /**
     * Fails that waiting message as a consumer killed in that mode leaves it: taken in that mode by a holder whose lock nobody holds, then reaped as the next pass would.
     *
     * Those are the rows of that queue: a claim of another holder that is overdue is reaped with it.
     */
    public static function failAsKilled(DbService $db, QueueSchema $schema, ClockInterface $clock, int $id, QueueConsumerMode $mode): void
    {
        $dir = sys_get_temp_dir() . '/yw-killed-' . bin2hex(random_bytes(4));
        $transport = new QueueTransport($db, $schema, $clock, holderLock: new QueueHolderLock(self::locksIn($dir)));
        $token = QueueTestHolders::gone($dir);

        try {
            $transport->workFor($token);
            $transport->claimAs($mode);
            if ($transport->getById($id) === null) {
                throw new \LogicException("Message {$id} is not waiting: it cannot be taken.");
            }
            $transport->reapKilled();
        } finally {
            @unlink($dir . '/' . QueueHolderLock::pathOf($token));
            @rmdir($dir . '/' . QueueHolderLock::DIRECTORY);
            @rmdir($dir);
        }
    }
    /** Starts a pass as the consumer does, a locked token given to the transport, for a test that takes messages outside drain(); answers the token. */
    public function pass(): string
    {
        $token = QueueHolderLock::newToken();
        if (!$this->holderLock->hold($token)) {
            throw new \LogicException('The kit could not lock the token of its pass.');
        }
        $this->transport->workFor($token);

        return $token;
    }

    /** The wiki as it is before its migration ran: no queue table. */
    public function dropTables(): void
    {
        $this->schema->drop();
    }

    /** Queues a test message and answers its id. */
    public function send(string $value): int
    {
        $stamp = $this->transport->send(new Envelope(new QueueTestMessage($value)))->last(TransportMessageIdStamp::class);
        if ($stamp === null) {
            throw new \LogicException('The transport gave no id to the message it was sent.');
        }

        return (int)$stamp->getId();
    }

    /** The container of the services the commands and the route ask for, the consumer being this one's. */
    public function services(QueueSettings $settings): ContainerInterface
    {
        return new ServiceLocator([
            QueueConsumer::class => fn (): QueueConsumer => $this->consumer,
            QueueSettings::class => static fn (): QueueSettings => $settings,
            QueuePassLimitsFactory::class => fn (): QueuePassLimitsFactory => $this->passLimits,
        ]);
    }

    /** The queue's health checks over this kit's tables, the wiki awake, in the mode those settings say. */
    public function healthChecks(QueueSettings $settings): QueueHealthChecks
    {
        return new QueueHealthChecks(
            $settings,
            $this->statistics,
            $this->schema,
            $this->hibernation,
            $this->ticker,
            $this->fallback($settings),
            $this->clock,
        );
    }

    /** Whether the end of requests consumes for the consumer those settings choose, over this kit's tables and locks. */
    public function fallback(QueueSettings $settings, ?HibernationService $hibernation = null): QueueFallback
    {
        return new QueueFallback($settings, $this->statistics, $hibernation ?? $this->hibernation, $this->locks, $this->clock);
    }

    /**
     * The end of a request as index.php runs it, over this kit's queue, in the mode those settings say.
     *
     * @param (\Closure(): bool)|null $finishResponse hands the response over, answering whether it has left (default: it has)
     * @param HibernationService|null $hibernation    the wiki's status as the end of the request reads it (default: the consumer's)
     * @param (\Closure(): bool)|null $respondsEarly  whether the server can send the response before the end of the script (default: as this PHP can)
     */
    public function requestTrigger(QueueSettings $settings, ?ConsoleService $console = null, ?\Closure $finishResponse = null, ?string $workingDirectory = null, ?HibernationService $hibernation = null, ?\Closure $respondsEarly = null): QueueRequestTrigger
    {
        return $this->trigger = new QueueRequestTrigger(
            $settings,
            $this->consumer,
            $this->passLimits,
            $this->statistics,
            $this->ticker,
            $this->fallback($settings, $hibernation),
            $console ?? new QueueTestConsole(),
            $hibernation ?? $this->hibernation,
            $this->journal,
            $this->clock,
            $finishResponse ?? static fn (): bool => true,
            $workingDirectory,
            $respondsEarly,
        );
    }

    /**
     * The messages waiting in the queue, delayed ones included, in the order they will be taken.
     *
     * @return list<Envelope>
     */
    public function waiting(): array
    {
        $rows = $this->db->loadAll(
            "SELECT id FROM {$this->schema->table()} WHERE queue_name = ? AND delivered_at IS NULL ORDER BY available_at, id",
            [QueueTransport::DEFAULT_QUEUE]
        );

        return array_values(array_filter(array_map(fn (array $row): ?Envelope => $this->transport->find((int)$row['id']), $rows)));
    }

    /**
     * Ticks the Scheduler and answers the ids of the tasks it queued, taken out of the queue again so that none absorbs the next tick's.
     *
     * @return list<string>
     */
    public function tick(): array
    {
        $this->ticker->tick();
        $ids = [];
        foreach ($this->waiting() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof ScheduledTask) {
                $ids[] = $message->taskId();
            }
        }
        $this->db->query("DELETE FROM {$this->schema->table()} WHERE queue_name = ?", [QueueTransport::DEFAULT_QUEUE]);

        return $ids;
    }

    /** The configuration file the kit points YESWIKI_CONFIG_FILE at, as it is now on disk. */
    public function configFile(): string
    {
        return $this->configFile;
    }

    /** The bus's way to the end of the request, for a bus that never sends a message a request must run itself (RunsForItsSender). */
    public static function noRequest(): \Closure
    {
        return static fn (): QueueRequestTrigger => throw new \LogicException('No request runs here.');
    }

    /**
     * A triples table on that connection, made if it is not there, and the TripleStore over it.
     *
     * @param HibernationService|null $hibernation the wiki's status as the TripleStore reads it before each write (default: awake)
     */
    public static function tripleStore(DbService $db, ?HibernationService $hibernation = null): TripleStore
    {
        $id = match ($db->getDriver()) {
            'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => 'SERIAL PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };
        $db->query('CREATE TABLE IF NOT EXISTS ' . $db->quoteIdentifier(trim($db->prefixTable('triples')))
            . " (id {$id}, resource VARCHAR(191) NOT NULL, property VARCHAR(191) NOT NULL, value TEXT NOT NULL)");

        return new TripleStore($db, $hibernation ?? new HibernationService(new ParameterBag(['wiki_status' => 'running'])));
    }

    /** RuntimeLock with every lock under $dir, so the tests never touch the dev wiki's cache/. */
    public static function locksIn(string $dir): RuntimeLock
    {
        return new class($dir) extends RuntimeLock {
            public function __construct(private readonly string $dir)
            {
                parent::__construct();
            }

            public function lastTaken(string $path): int
            {
                return parent::lastTaken($this->in($path));
            }

            public function stampAt(string $path, int $time): void
            {
                parent::stampAt($this->in($path), $time);
            }

            public function forget(string $path): void
            {
                parent::forget($this->in($path));
            }

            public function acquire(string $path)
            {
                return parent::acquire($this->in($path));
            }

            public function probe(string $path): ?bool
            {
                return parent::probe($this->in($path));
            }

            private function in(string $path): string
            {
                return $this->dir . '/' . ltrim($path, '/');
            }
        };
    }
}
