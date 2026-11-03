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
use YesWiki\Kernel\Queue\QueueBudget;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\ConfigurationWatch;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Kernel\Service\DatabaseClock;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueBudgetContext;
use YesWiki\Kernel\Service\Queue\QueueBudgetFactory;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueCheckpoint;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueueFallback;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueHealthChecks;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueRequestTrigger;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTaskRows;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\ThrowableFormatter;
use YesWiki\Kernel\Service\TripleStore;

require_once 'tests/src/Kernel/QueueTestMessage.php';

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
    /** The one clock every part of the queue reads. */
    public readonly ClockInterface $clock;
    public readonly QueueBudgetFactory $budgets;
    public readonly QueueBudgetContext $budgetContext;
    private readonly HibernationService $hibernation;
    /** The Scheduler's checkpoint, in this kit's triples. */
    public readonly QueueCheckpoint $checkpoint;
    /** The end of the request the bus pins a message to (RunsUnbudgeted): the last one requestTrigger() built. */
    private ?QueueRequestTrigger $trigger = null;
    /** @var resource|null */
    private $heldPassLock;

    /** @var list<string> the values of the messages handled, in order */
    public array $handled = [];

    /** What the consumer's ConfigurationWatch answers when the consumer asks. */
    public bool $configurationChanged = false;

    private readonly string $file;
    /** Whether the kit made its database, and removes it with the rest; not when it was given one. */
    private readonly bool $ownsDatabase;
    private readonly string $lockDir;
    private readonly string $configFile;
    private readonly string|false $previousConfigFile;
    private readonly string|false $previousMode;

    /**
     * @param array<class-string, \Closure>      $handlers             further messages the queue handles, by class, each with the closure that handles it; one for QueueTestMessage replaces the kit's
     * @param ClockInterface|null                $clock                the clock of every part of the queue (default: the database's, as in production)
     * @param string|null                        $database             an SQLite file another kit or test made, for a consumer in another process to work on the same queue; left where it is by dispose()
     * @param ProvidesSchedule|null              $schedule             the recurring tasks the schedule declares (default: none)
     * @param QueueBudgetFactory|null            $budgets              what a pass may spend (default: from this PHP's limits)
     * @param HibernationService|null            $hibernation          the wiki's status as the consumer reads it (default: awake)
     * @param (\Closure(): EventDispatcher)|null $events               the wiki's dispatcher, which a killed message is announced on (default: one nobody listens to)
     * @param \Closure(): bool|null              $configurationChanged what the consumer's ConfigurationWatch answers (default: $this->configurationChanged)
     */
    public function __construct(
        array $handlers = [],
        ?ClockInterface $clock = null,
        ?string $database = null,
        ?ProvidesSchedule $schedule = null,
        ?QueueBudgetFactory $budgets = null,
        ?HibernationService $hibernation = null,
        ?\Closure $events = null,
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
        $this->clock = $clock ??= new DatabaseClock($db);
        $this->schema = $schema = new QueueSchema($db);
        $schema->create();
        (new JournalSchema($db))->create();

        $this->configFile = sys_get_temp_dir() . '/yw-kit-config-' . bin2hex(random_bytes(4)) . '.php';
        $this->previousConfigFile = getenv('YESWIKI_CONFIG_FILE');
        $this->previousMode = getenv(QueueSettings::ENV_MODE);
        putenv('YESWIKI_CONFIG_FILE=' . $this->configFile);
        $configuration = new ConfigurationService();
        $config = $configuration->getConfiguration($this->configFile);
        $config->load();
        $config['wiki_status'] = 'running';
        $configuration->write($config);

        $this->transport = new QueueTransport($db, $schema, $clock);
        $tasks = new QueueTaskRows($db, $schema, $clock);
        $this->statistics = new QueueStatistics($db, $schema, new JournalSchema($db), $this->transport, $tasks, $clock);
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
        $this->bus = $bus = new QueueBus(
            $this->transport,
            new QueueHandlerLocator($map, new ServiceLocator($services)),
            $journal,
            fn (): QueueRequestTrigger => $this->trigger ?? $this->requestTrigger(new QueueSettings(new ParameterBag([]), static fn (): array => [])),
        );
        $this->recorder = $recorder = new QueueRecorder($db, $tasks, new JournalSchema($db), $clock);
        $this->locks = $locks = self::locksIn($this->lockDir);
        $schedule ??= new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [];
            }
        };

        $watch = new ConfigurationWatch();
        $watch->bind($configurationChanged ?? fn (): bool => $this->configurationChanged);

        // On the real clock whatever the queue's: a budget is spent against microtime() (QueueBudget).
        $this->budgets = $budgets ??= new QueueBudgetFactory(new NativeClock());
        $this->budgetContext = new QueueBudgetContext();
        $this->hibernation = $hibernation ??= new HibernationService(new ParameterBag(['wiki_status' => 'running']));
        $this->checkpoint = new QueueCheckpoint(self::tripleStore($db, $hibernation), $locks);
        $this->consumer = new QueueConsumer(
            $this->transport,
            $bus,
            $recorder,
            $this->statistics,
            $this->budgetContext,
            $budgets,
            $hibernation,
            $locks,
            $journal,
            $this->ticker = new QueueScheduleTicker([$schedule], $bus, $this->checkpoint, $recorder, $clock, $hibernation, $journal),
            $clock,
            $watch,
            $events ?? static fn (): EventDispatcher => new EventDispatcher(new ServiceLocator([]), new ThrowableFormatter()),
        );
    }

    /** Puts the process's environment back and removes the files; the kit is of no use after. */
    public function dispose(): void
    {
        if ($this->heldPassLock !== null) {
            $this->locks->release($this->heldPassLock);
        }
        putenv($this->previousMode === false ? QueueSettings::ENV_MODE : QueueSettings::ENV_MODE . '=' . $this->previousMode);
        putenv($this->previousConfigFile === false ? 'YESWIKI_CONFIG_FILE' : 'YESWIKI_CONFIG_FILE=' . $this->previousConfigFile);
        @unlink($this->configFile);
        foreach ($this->ownsDatabase ? ['', '-wal', '-shm'] : [] as $suffix) {
            @unlink($this->file . $suffix);
        }
        foreach (glob($this->lockDir . '/cache/*') ?: [] as $lock) {
            @unlink($lock);
        }
        @rmdir($this->lockDir . '/cache');
        @rmdir($this->lockDir);
    }

    /** A pass in another process, as far as the consumer can tell: it finds the pass lock taken. */
    public function holdThePassLock(): void
    {
        $handle = $this->locks->acquire(QueueConsumer::PASS_LOCK);
        if ($handle === null || !$this->locks->tryLock($handle)) {
            throw new \LogicException('The pass lock could not be taken.');
        }
        $this->heldPassLock = $handle;
    }

    /**
     * A run of a message as QueueRecorder writes it in the Journal, as if it had ended at $finishedAt (UTC) after $durationMs.
     */
    public static function recordRun(DbService $db, string $type, string $status, int $durationMs, string $finishedAt, int $messageId = 0, QueueConsumerMode $mode = QueueConsumerMode::Request): void
    {
        $clock = new MockClock($finishedAt, 'UTC');
        $startedAt = (float)$clock->now()->format('U.u') - $durationMs / 1000;

        (new QueueRecorder($db, new QueueTaskRows($db, new QueueSchema($db), $clock), new JournalSchema($db), $clock))
            ->messageFinished($messageId, $type, null, $status, $mode, $startedAt, null);
    }

    /**
     * A pass as QueueRecorder writes it in the Journal, as if it had started at $startedAt and was last seen at $lastSeenAt (both UTC).
     *
     * @param string      $stopReason  why it ended; '' leaves it open, its last heartbeat at $lastSeenAt
     * @param int         $memoryLimit the memory use it stops at, 0 for none
     * @param string|null $holder      the claim token of the process that ran it, when not this one (a consumer's pass names its process)
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
        ?QueuePassOrigin $origin = null,
        ?QueueConsumerMode $fallbackFor = null,
        ?string $holder = null,
    ): int {
        $clock = new MockClock($startedAt, 'UTC');
        $journal = new JournalSchema($db);
        $recorder = new QueueRecorder($db, new QueueTaskRows($db, new QueueSchema($db), $clock), $journal, $clock);

        $id = $recorder->passStarted($mode, $memoryLimit === 0 ? null : new QueueBudget(INF, $memoryLimit, 0), $beforeResponse, $origin ?? QueuePassOrigin::Consumer, $fallbackFor);
        if ($holder !== null) {
            // The recorder only knows the process it runs in: a pass of another process is written as that one would have.
            $context = json_decode((string)$db->scalar("SELECT context FROM {$journal->table()} WHERE id = ?", null, [$id]), true);
            $context['holder'] = $holder;
            $db->query("UPDATE {$journal->table()} SET context = ? WHERE id = ?", [json_encode($context), $id]);
        }
        $clock->modify($lastSeenAt);
        if ($stopReason === '') {
            $recorder->passHeartbeat($id, 0, $peakMemory);
        } else {
            $recorder->passFinished($id, new QueuePassReport(0, 0, 0, $stopReason), $peakMemory);
        }

        return $id;
    }

    /** Fails that waiting message as a consumer killed in that mode leaves it: taken in that mode, then given up as its process ends (QueueTransport::failHeldClaims()). */
    public static function failAsKilled(QueueTransport $transport, int $id, QueueConsumerMode $mode): void
    {
        $transport->claimAs($mode, 30);
        if ($transport->getById($id) === null) {
            throw new \LogicException("Message {$id} is not waiting: it cannot be taken.");
        }
        $transport->failHeldClaims();
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
            QueueBudgetFactory::class => fn (): QueueBudgetFactory => $this->budgets,
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
            $this->budgets,
            $this->clock,
        );
    }

    /** Whether the end of requests consumes for the consumer those settings choose, over this kit's tables and locks. */
    public function fallback(QueueSettings $settings, ?HibernationService $hibernation = null): QueueFallback
    {
        return new QueueFallback($settings, $this->statistics, $this->checkpoint, $hibernation ?? $this->hibernation, $this->locks, $this->clock);
    }

    /**
     * The end of a request as index.php runs it, over this kit's queue, in the mode those settings say.
     *
     * @param (\Closure(): bool)|null $finishResponse hands the response over, answering whether it has left (default: it has)
     * @param HibernationService|null $hibernation    the wiki's status as the end of the request reads it (default: the consumer's)
     */
    public function requestTrigger(QueueSettings $settings, ?ConsoleService $console = null, ?\Closure $finishResponse = null, ?string $workingDirectory = null, ?HibernationService $hibernation = null): QueueRequestTrigger
    {
        return $this->trigger = new QueueRequestTrigger(
            $settings,
            $this->consumer,
            $this->budgets,
            $this->statistics,
            $this->checkpoint,
            $this->fallback($settings, $hibernation),
            $console ?? new QueueTestConsole(),
            $hibernation ?? $this->hibernation,
            $this->journal,
            $this->clock,
            $finishResponse ?? static fn (): bool => true,
            $workingDirectory,
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

    /** The configuration file the kit points YESWIKI_CONFIG_FILE at, as it is now on disk. */
    public function configFile(): string
    {
        return $this->configFile;
    }

    /** The bus's way to the end of the request, for a bus that never sends a message a request must run itself (RunsUnbudgeted). */
    public static function noRequest(): \Closure
    {
        return static fn (): QueueRequestTrigger => throw new \LogicException('No request runs here.');
    }

    /**
     * A triples table on that connection, made if it is not there, and the TripleStore over it: where the Scheduler's checkpoint lives (QueueCheckpoint).
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

            private function in(string $path): string
            {
                return $this->dir . '/' . ltrim($path, '/');
            }
        };
    }
}
