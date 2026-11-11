<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassLimits;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\ConfigurationWatch;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Lock\FileLocks;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueHolderLock;
use YesWiki\Kernel\Service\Queue\QueuePassLimitsFactory;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\Queue\ScheduleHistory;
use YesWiki\Kernel\Service\ThrowableFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestMessage.php';
require_once 'tests/src/Kernel/QueueTestOnceMessage.php';
require_once 'tests/src/Kernel/QueueTestTask.php';
require_once 'tests/src/Kernel/QueueTestToleratedMessage.php';
require_once 'tests/src/Kernel/QueueTransportTest.php';
require_once 'tests/src/Kernel/QueueTestHolders.php';
require_once 'tests/src/Kernel/QueueTestKit.php';

/** The one consumer of the queue: passes that end by themselves and blocking loops, retries, killed messages, and what it records of all that. */
#[CoversClass(QueueConsumer::class)]
#[CoversClass(QueuePassReport::class)]
class QueueConsumerTest extends YesWikiTestCase
{
    private const START = '2026-10-23 12:00:00';

    /** Longer than the longest retry delay (30 min) with its jitter. */
    private const PAST_ANY_RETRY = 2000;

    private string $file;
    private DbService $db;
    private QueueSchema $schema;
    /** The wiki's parameters as the container froze them at boot: `running`, whatever the config file says later. */
    private ParameterBag $params;
    /** A config file of the test's own (YESWIKI_CONFIG_FILE), where the status is written the way ArchiveService writes it. */
    private string $configFile;
    private string|false $previousConfigFile;
    /** @var resource */
    private $log;
    /** What the consumer reads PHP's limits through; null for the real ones. */
    private ?QueuePassLimitsFactory $passLimits = null;

    /** What the test handler does after noting the message; null to just note it. */
    private ?\Closure $behaviour = null;
    /** What the consumer's ConfigurationWatch answers, and how many times it was asked. */
    private bool $configurationChanged = false;
    private int $configurationAsked = 0;
    /** @var list<string> */
    private array $handled = [];

    private ClockInterface $clock;
    private QueueTransport $transport;
    private QueueStatistics $statistics;
    private ScheduleHistory $history;
    private QueueConsumer $consumer;
    /** Where the holders' lock files go, the test's own: the transport and the consumer share it, as in production. */
    private string $lockDir;
    private QueueHolderLock $holderLock;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-queue-') . '.db';
        $this->lockDir = sys_get_temp_dir() . '/yw-queue-holders-' . bin2hex(random_bytes(4));
        $this->holderLock = new QueueHolderLock(new FileLocks(QueueTestKit::locksIn($this->lockDir)));
        $this->db = new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywq_',
            'debug' => false,
        ]));
        $this->schema = new QueueSchema($this->db);
        $this->schema->create();
        (new JournalSchema($this->db))->create();
        $this->params = new ParameterBag(['wiki_status' => 'running']);
        $this->configFile = tempnam(sys_get_temp_dir(), 'yw-config-') . '.php';
        $this->previousConfigFile = getenv('YESWIKI_CONFIG_FILE');
        putenv('YESWIKI_CONFIG_FILE=' . $this->configFile);
        $this->setWikiStatus('running');
        $this->build($this->db, new MockClock(new \DateTimeImmutable(self::START)));
    }

    protected function tearDown(): void
    {
        unset($this->consumer, $this->statistics, $this->transport, $this->schema, $this->db);
        putenv($this->previousConfigFile === false ? 'YESWIKI_CONFIG_FILE' : 'YESWIKI_CONFIG_FILE=' . $this->previousConfigFile);
        @unlink($this->configFile);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
        foreach (glob($this->lockDir . '/' . QueueHolderLock::DIRECTORY . '/*') ?: [] as $holder) {
            @unlink($holder);
        }
        @rmdir($this->lockDir . '/' . QueueHolderLock::DIRECTORY);
        @unlink($this->archiveLock());
        @rmdir($this->lockDir . '/cache');
        @rmdir($this->lockDir);
    }

    public function testDrainHandlesEverythingWhenThePassLimitsAllow(): void
    {
        $this->send(5);

        $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(5, $report->handled);
        $this->assertSame(0, $report->remaining);
        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
        $this->assertSame(['m0', 'm1', 'm2', 'm3', 'm4'], $this->handled);
        $this->assertSame(array_fill(0, 5, 'handled'), array_column($this->statistics->history(10), 'status'));
    }

    /** No time limit any more: slow messages are all taken, the pass ends when what was there is done. */
    public function testAPassRunsEveryMessageEvenWhenTheyAreSlow(): void
    {
        $this->behaviour = static function (): void {
            usleep(250000);
        };
        $this->send(3);

        $report = $this->consumer->drain(QueueConsumerMode::Request, (new QueuePassLimitsFactory(new NativeClock()))->forPass(), responseAlreadySent: false);

        $this->assertSame(3, $report->handled);
        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
    }

    /** A pass runs what was there when it began: a message its handlers send waits for the next pass, or a pass could never end. */
    public function testAMessageSentDuringThePassWaitsForTheNext(): void
    {
        $this->behaviour = function (object $message): void {
            if ($message instanceof QueueTestMessage && $message->value === 'm0') {
                $this->transport->send(new Envelope(new QueueTestMessage('m-late')));
            }
        };
        $this->send(1);

        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(['m0'], $this->handled);
        $this->assertSame(1, $this->statistics->pendingCount());

        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));
        $this->assertSame(['m0', 'm-late'], $this->handled, 'the next pass takes it');
    }

    /** What the tick dispatches is there when the pass begins: the cutoff is read after it. */
    public function testATaskTheTickDispatchesIsRunInTheSamePass(): void
    {
        $clock = new MockClock(new \DateTimeImmutable(self::START));
        $this->build($this->db, $clock, static fn (): array => [RecurringMessage::every('30 minutes', new QueueTestTask())]);
        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));
        $this->assertSame(0, $this->statistics->pendingCount(), 'the first tick only notes the task');
        $clock->sleep(31 * 60);

        $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(1, $report->handled, 'the task now due was dispatched by the tick and taken by the same pass');
        $this->assertSame('test-task', $this->statistics->history(1)[0]['taskId'] ?? null);
        $this->assertSame(0, $this->statistics->pendingCount());
    }

    /** The cutoff is on ids, not on time: a delayed message that was there comes due during the pass and is taken. */
    public function testADelayedMessageThatComesDueDuringThePassIsTaken(): void
    {
        $clock = new MockClock(new \DateTimeImmutable(self::START));
        $this->build($this->db, $clock);
        $this->behaviour = static function (object $message) use ($clock): void {
            if ($message instanceof QueueTestMessage && $message->value === 'm0') {
                $clock->sleep(2);
            }
        };
        $this->transport->send(new Envelope(new QueueTestMessage('m0')));
        $this->transport->send(new Envelope(new QueueTestMessage('m1'), [new DelayStamp(1000)]));

        $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(['m0', 'm1'], $this->handled);
        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
    }

    public function testAFailingMessageIsRetriedThenFailed(): void
    {
        $this->behaviour = static function (): void {
            throw new \RuntimeException('remote is down');
        };
        $this->send(1);

        for ($pass = 0; $pass < 4; $pass++) {
            $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));
            $this->assertInstanceOf(MockClock::class, $this->clock);
            $this->clock->sleep(self::PAST_ANY_RETRY);
        }

        $this->assertCount(4, $this->handled, 'the first attempt and three retries');
        $this->assertSame(0, $this->statistics->pendingCount());
        $failed = $this->statistics->failed();
        $this->assertCount(1, $failed);
        $this->assertSame('RuntimeException: remote is down', $failed[0]['error']);
        $this->assertSame(3, $failed[0]['attempts']);
        $statuses = array_count_values(array_column($this->statistics->history(10), 'status'));
        ksort($statuses);
        $this->assertSame(['failed' => 1, 'retried' => 3], $statuses);
    }

    /**
     * A message whose retry policy is zero fails at once, is journaled like any other failure, and a tolerated one is no exception to that (it only stays out of the generic health check).
     *
     * @param class-string $class
     */
    #[DataProvider('messagesThatFailAtOnce')]
    public function testADefinitiveFailureIsFailedAtOnceAndJournaled(string $class): void
    {
        $this->behaviour = static function (): void {
            throw new \RuntimeException('broken for good');
        };
        $this->transport->send(new Envelope(new $class('once')));

        $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(1, $report->handled);
        $this->assertSame(0, $this->statistics->pendingCount(), 'no retry is waiting');
        $this->assertSame([$class], array_column($this->statistics->failed(), 'type'));
        $this->assertSame(['failed'], array_column($this->statistics->history(10), 'status'));
        $journal = $this->journaled();
        $this->assertStringContainsString('"action":"queue.message_failed"', $journal);
        $this->assertStringContainsString('"target":"' . addcslashes($class, '\\') . '"', $journal);
        $this->assertStringContainsString('"channel":"diagnostic"', $journal);
    }

    /** @return array<string, array{class-string}> */
    public static function messagesThatFailAtOnce(): array
    {
        return [
            'a message that is not retried' => [QueueTestOnceMessage::class],
        ];
    }

    public function testAToleratedFailureThatUsedItsRetriesLeavesNothingInFailed(): void
    {
        $this->behaviour = static function (): void {
            throw new \RuntimeException('dead inbox');
        };
        $this->transport->send(new Envelope(new QueueTestToleratedMessage('once')));

        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(0, $this->statistics->failedCount(), 'a routine failure is not kept for the admin to act on');
        $this->assertSame(0, $this->statistics->pendingCount());
        $this->assertSame(['failed'], array_column($this->statistics->history(10), 'status'));
        $this->assertStringContainsString('"action":"queue.message_failed"', $this->journaled());
    }

    public function testAnIntermediateFailureIsNotJournaled(): void
    {
        $this->behaviour = static function (): void {
            throw new \RuntimeException('try again later');
        };
        $this->send(1);

        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertStringNotContainsString('queue.message_failed', $this->journaled());
        $this->assertSame(['retried'], array_column($this->statistics->history(10), 'status'));
    }

    public function testAMessageNobodyHandlesFailsAtOnce(): void
    {
        $this->transport->send(new Envelope(new \stdClass()));

        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(0, $this->statistics->pendingCount(), 'retrying would find no handler either');
        $failed = $this->statistics->failed();
        $this->assertCount(1, $failed);
        $this->assertStringStartsWith('Symfony\Component\Messenger\Exception\NoHandlerForMessageException', (string)$failed[0]['error']);
    }

    public function testAnUndecodableMessageIsFailedAndThePassGoesOn(): void
    {
        $this->db->query(
            "INSERT INTO {$this->schema->table()} (queue_name, message_type, body, headers, created_at, available_at, attempts)"
            . " VALUES ('default', ?, 'garbage', '[]', ?, ?, 0)",
            ['Gone\\Message', self::START, self::START]
        );
        $this->send(1);

        $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(['m0'], $this->handled);
        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
        $this->assertSame(['Gone\Message'], array_column($this->statistics->failed(), 'type'));
        $history = $this->statistics->history(10);
        $this->assertSame(['handled', 'failed'], array_column($history, 'status'));
        $this->assertSame('Gone\Message', $history[1]['type']);
        $this->assertStringContainsString('queue.message_failed', $this->journaled());
    }

    public function testAKilledMessageIsReapedAtTheNextPass(): void
    {
        $id = $this->abandonedAndExpired();

        $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(0, $report->handled, 'a killed message is not run again in the same mode');
        $this->assertSame([], $this->handled);
        $this->assertSame([$id], array_column($this->statistics->failed(), 'id'));
        $this->assertSame('request', $this->statistics->failed()[0]['killedIn']);
        $history = $this->statistics->history(10);
        $this->assertSame(['killed'], array_column($history, 'status'));
        $this->assertSame('request', $history[0]['mode'], 'the mode it was killed in, not the mode that found it');
        $this->assertSame((QueueTransport::ABSENT_HOLDER_SECONDS + 1) * 1000, $history[0]['durationMs']);
        $this->assertStringContainsString('"action":"queue.message_killed"', $this->journaled());
        $this->assertSame('killed', $this->statistics->messageState($id));
    }

    /**
     * A consumer still at work is not reaped however long it takes past its limit; once the host kills it (SIGKILL: nothing of it runs), the next pass fails its message at once.
     *
     * The consumer is a real process, which takes the message and sleeps on it.
     */
    public function testAConsumerTheHostKillsIsReapedByTheNextPassAtOnce(): void
    {
        if (!function_exists('posix_kill')) {
            $this->markTestSkipped('posix_kill is needed to kill the consumer');
        }
        $this->send(1);
        $script = $this->script(<<<'PHP'
            define('YESWIKI_INSTANCE_DIR', $argv[2]);
            require_once 'tests/src/Kernel/QueueTestMessage.php';
            $db = new YesWiki\Kernel\Service\DbService(new Symfony\Component\DependencyInjection\ParameterBag\ParameterBag(['db_driver' => 'sqlite', 'db_database' => $argv[1], 'table_prefix' => 'ywq_', 'debug' => false]));
            $transport = new YesWiki\Kernel\Service\Queue\QueueTransport($db, new YesWiki\Kernel\Service\Queue\QueueSchema($db), new Symfony\Component\Clock\NativeClock());
            $transport->claimAs(YesWiki\Kernel\Queue\QueueConsumerMode::Request);
            $holder = YesWiki\Kernel\Service\Queue\QueueHolderLock::newToken();
            $locks = new YesWiki\Kernel\Service\Queue\QueueHolderLock(new YesWiki\Kernel\Service\Lock\FileLocks());
            $locks->hold($holder) or exit(2);
            $transport->workFor($holder);
            echo count([...$transport->get()]), "\n";
            sleep(60);
            PHP);
        $process = proc_open([PHP_BINARY, $script, $this->file, $this->lockDir], [1 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);

        try {
            $this->assertSame("1\n", fgets($pipes[1]), 'the consumer took the message');
            $this->build($this->db, new MockClock(new \DateTimeImmutable('+30 minutes')));
            $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));
            $this->assertSame([], $this->statistics->failed(), 'half an hour past its one-second limit, it holds its lock: it is still at work');
            $this->assertSame([], $this->handled);

            posix_kill(proc_get_status($process)['pid'], 9);
            fclose($pipes[1]);
            proc_close($process);
            $process = null;
            $this->build($this->db, new NativeClock());
            $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

            $this->assertSame(['request'], array_column($this->statistics->failed(), 'killedIn'));
            $this->assertSame(['killed'], array_column($this->statistics->history(10), 'status'));
            $this->assertSame([], $this->handled, 'a killed message is not run again in the same mode');
        } finally {
            if (is_resource($process)) {
                proc_terminate($process, 9);
                proc_close($process);
            }
            @unlink($script);
        }
    }

    /** A holder declared dead that finishes after all leaves its failure where it is, and the Journal says how it ended. */
    public function testAMessageThatEndsAfterItsHolderWasDeclaredDeadStaysFailed(): void
    {
        $this->behaviour = function (): void {
            // What a judge finds if the holder's lock were let go now; here the pass goes on, and the Worker acks.
            $id = (int)$this->db->scalar("SELECT id FROM {$this->schema->table()} WHERE delivered_at IS NOT NULL");
            QueueTestHolders::hand($this->db, $this->schema, $id, QueueTestHolders::gone($this->lockDir));
            $this->transport->reapKilled();
        };
        $this->send(1);

        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(['m0'], $this->handled);
        $this->assertSame(['api'], array_column($this->statistics->failed(), 'killedIn'));
        $this->assertStringContainsString('"action":"queue.message_ended_late"', $this->journaled());
    }

    /** A killed message whose body can no longer be read is recorded all the same, under the type its row names. */
    public function testAKilledMessageThatCannotBeReadIsStillRecorded(): void
    {
        $this->db->query(
            "INSERT INTO {$this->schema->table()} (queue_name, message_type, body, headers, created_at, available_at, attempts, delivered_at, delivered_by, delivered_mode)"
            . " VALUES ('default', ?, 'garbage', '[]', ?, ?, 0, ?, 'gone', 'api')",
            ['Gone\\Message', '2026-10-23 11:00:00', '2026-10-23 11:00:00', '2026-10-23 10:00:00']
        );

        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(['Gone\\Message'], array_column($this->statistics->failed(), 'type'));
        $history = $this->statistics->history(10);
        $this->assertSame(['killed'], array_column($history, 'status'));
        $this->assertSame('Gone\\Message', $history[0]['type']);
    }

    /** A long message does not hold the others back: a second pass, started while the first runs it, takes what waits behind it. */
    public function testTwoPassesRunAtOnce(): void
    {
        $this->transport->send(new Envelope(new QueueTestMessage('long')));
        $this->send(2);
        $second = null;
        $this->behaviour = function (QueueTestMessage $message) use (&$second): void {
            if ($message->value === 'long') {
                $second = $this->consumer->drain(QueueConsumerMode::Request, new QueuePassLimits(PHP_INT_MAX));
            }
        };

        $first = $this->consumer->drain(QueueConsumerMode::Request, new QueuePassLimits(PHP_INT_MAX));

        $this->assertNotNull($second);
        $this->assertSame(2, $second->handled, 'the second pass took the short messages while the first ran the long one');
        $this->assertSame(1, $first->handled, 'and the first did not take them again');
        $this->assertSame(QueuePassReport::EMPTY, $first->stopReason);
        $this->assertSame(['long', 'm0', 'm1'], $this->handled);
        $this->assertSame(0, $this->statistics->pendingCount());
        $this->assertSame([], $this->statistics->openPasses(), 'both passes are closed');
    }

    /** A cron that keeps running while the wiki sleeps must still be seen when it wakes up, or it would be taken for gone and the end of requests would stand in for it. */
    public function testAConsumerPassThatFindsTheWikiHibernatedLeavesARow(): void
    {
        $this->send(1);
        $this->setWikiStatus('hibernate');

        $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(QueuePassReport::PAUSED, $report->stopReason);
        $this->assertSame([], $this->handled);
        $pass = $this->statistics->lastPass();
        $this->assertNotNull($pass);
        $this->assertSame('api', $pass['mode']);
        $this->assertSame(QueuePassReport::PAUSED, $pass['stopReason']);
        $this->assertSame($this->clock->now()->getTimestamp(), $this->statistics->lastSeenAt(QueueConsumerMode::Api)?->getTimestamp());
    }

    /** The end of a request, the jobs page's button, `--drain`: none of them is a consumer that could be taken for gone. */
    public function testARequestPassThatFindsTheWikiHibernatedLeavesNoRow(): void
    {
        $this->setWikiStatus('hibernate');

        $this->consumer->drain(QueueConsumerMode::Request, new QueuePassLimits(PHP_INT_MAX));

        $this->assertNull($this->statistics->lastPass());
    }

    public function testAPassWhoseProcessIsGoneIsClosedByTheNextOne(): void
    {
        $dead = QueueTestKit::recordPass($this->db, QueueConsumerMode::Worker, self::START, self::START, '', holder: QueueTestHolders::gone($this->lockDir));

        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(QueuePassReport::KILLED, $this->stopReasonOf($dead), 'its process ended without closing it: it will never run again');
        $this->assertSame(self::START, $this->lastSeenOf($dead), 'last seen when it last beat');
    }

    public function testAPassWhoseLockIsFreeIsClosedAndItsFileRemoved(): void
    {
        $token = QueueTestHolders::token();
        $file = $this->holderFile($token);
        mkdir(\dirname($file), 0o777, true);
        touch($file);
        $dead = QueueTestKit::recordPass($this->db, QueueConsumerMode::Request, self::START, self::START, '', holder: $token);

        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(QueuePassReport::KILLED, $this->stopReasonOf($dead), 'a lock nobody holds is a holder that is gone, however alive its pid looks');
        $this->assertFileDoesNotExist($file);
    }

    public function testAPassWhoseLockIsHeldStaysOpen(): void
    {
        $token = QueueTestHolders::token();
        $alive = QueueTestKit::recordPass($this->db, QueueConsumerMode::Request, self::START, self::START, '', holder: $token);
        $letGo = QueueTestHolders::lockHeldByAnotherProcess($this->holderFile($token));

        try {
            $this->assertInstanceOf(MockClock::class, $this->clock);
            $this->clock->sleep(7200);
            $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

            $this->assertSame('', $this->stopReasonOf($alive), 'a process that holds its lock is at work, however long it takes');
            $this->assertFileExists($this->holderFile($token), 'the file of a living holder is its own');
        } finally {
            $this->assertSame(0, $letGo());
        }
    }

    /** A long pass with no deadline (the jobs page's button) says it is alive between its messages, at most once a minute, so that nobody closes it as killed while it works. */
    public function testADrainSaysItIsAliveBetweenItsMessages(): void
    {
        $clock = $this->clock;
        $this->assertInstanceOf(MockClock::class, $clock);
        $seen = [];
        $this->behaviour = function (QueueTestMessage $message) use (&$seen, $clock): void {
            $pass = $this->statistics->openPasses()[0] ?? null;
            $this->assertNotNull($pass);
            $seen[$message->value] = $this->lastSeenOf($pass['id']);
            $clock->sleep($message->value === 'm1' ? 61 : 30);
        };
        $this->send(4);

        $report = $this->consumer->drain(QueueConsumerMode::Request, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
        $this->assertSame([
            'm0' => self::START,
            'm1' => self::START,
            'm2' => '2026-10-23 12:01:31',
            'm3' => '2026-10-23 12:01:31',
        ], $seen, 'a beat once a minute has passed since the last one, after the message that crossed it');
    }

    public function testAnOpenPassWithoutHolderIsClosedPastTheAbsentHolderDelay(): void
    {
        $unnamed = QueueTestKit::recordPass($this->db, QueueConsumerMode::Request, self::START, self::START, '');
        $this->assertInstanceOf(MockClock::class, $this->clock);

        $this->clock->sleep(QueueTransport::ABSENT_HOLDER_SECONDS);
        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));
        $this->assertSame('', $this->stopReasonOf($unnamed), 'nothing tells it is gone: it may still run, up to the longest a process lives and a minute');

        $this->clock->sleep(1);
        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));
        $this->assertSame(QueuePassReport::KILLED, $this->stopReasonOf($unnamed), 'no process lives that long');
        $this->assertSame(self::START, $this->lastSeenOf($unnamed));
    }

    public function testAPassHoldsItsLockWhileItRuns(): void
    {
        $seen = [];
        $this->behaviour = function () use (&$seen): void {
            foreach ($this->statistics->openPasses() as $pass) {
                $seen[$pass['id']] = QueueTestHolders::seenFromAnotherProcess($this->holderFile($pass['holder']));
            }
        };
        $this->send(1);

        $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
        $this->assertCount(1, $seen, 'the pass that runs is the one open');
        $passId = (int)array_key_first($seen);
        $this->assertSame(QueueHolderLock::HELD, $seen[$passId], 'while it runs, its lock tells it is there');
        $this->assertSame(QueuePassReport::EMPTY, $this->stopReasonOf($passId), 'its own pass is never taken for dead');
        $this->assertSame([], glob($this->lockDir . '/' . QueueHolderLock::DIRECTORY . '/*') ?: [], 'its file goes with it');
    }

    /** A holder found gone has its messages failed and its pass closed by the same reaping, and only then its file removed. */
    public function testADeadPassAndItsMessagesAreReapedTogetherAndItsFileRemoved(): void
    {
        $this->send(1);
        $this->transport->claimAs(QueueConsumerMode::Worker);
        $this->transport->workFor(QueueTestHolders::passToken());
        [$taken] = [...$this->transport->get()];
        $id = (int)$taken->last(TransportMessageIdStamp::class)?->getId();
        $gone = QueueTestHolders::hand($this->db, $this->schema, $id, QueueTestHolders::gone($this->lockDir));
        $dead = QueueTestKit::recordPass($this->db, QueueConsumerMode::Worker, self::START, self::START, '', holder: $gone);

        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame([$id], array_column($this->statistics->failed(), 'id'));
        $this->assertSame(['killed'], array_column($this->statistics->history(10), 'status'));
        $this->assertSame(QueuePassReport::KILLED, $this->stopReasonOf($dead), 'its pass goes with its messages');
        $this->assertFileDoesNotExist($this->holderFile($gone));
    }

    /** Passes run side by side: one that holds its file keeps its row open and its message, however long. */
    public function testAPassDoesNotReapAnotherPassThatHoldsItsFile(): void
    {
        $this->send(1);
        $this->transport->claimAs(QueueConsumerMode::Worker);
        $this->transport->workFor(QueueTestHolders::passToken());
        [$taken] = [...$this->transport->get()];
        $id = (int)$taken->last(TransportMessageIdStamp::class)?->getId();
        $other = QueueTestHolders::hand($this->db, $this->schema, $id, QueueTestHolders::token());
        $running = QueueTestKit::recordPass($this->db, QueueConsumerMode::Worker, self::START, self::START, '', holder: $other);
        $letGo = QueueTestHolders::lockHeldByAnotherProcess($this->holderFile($other));

        try {
            $this->assertInstanceOf(MockClock::class, $this->clock);
            $this->clock->sleep(5 * 3600);
            $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

            $this->assertSame('', $this->stopReasonOf($running));
            $this->assertSame([], $this->statistics->failed());
            $this->assertSame($other, $this->db->scalar("SELECT delivered_by FROM {$this->schema->table()} WHERE id = ?", null, [$id]));
            $this->assertFileExists($this->holderFile($other));
        } finally {
            $this->assertSame(0, $letGo());
        }
    }

    /** Every pass reaps: the first one to find a dead claim reports it, and the next ones find nothing more. */
    public function testTwoReapersReportADeadClaimOnce(): void
    {
        $this->send(1);
        $this->transport->claimAs(QueueConsumerMode::Worker);
        $this->transport->workFor(QueueTestHolders::passToken());
        [$taken] = [...$this->transport->get()];
        QueueTestHolders::hand($this->db, $this->schema, (int)$taken->last(TransportMessageIdStamp::class)?->getId(), QueueTestHolders::gone($this->lockDir));

        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));
        $this->consumer->drain(QueueConsumerMode::Request, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(['killed'], array_column($this->statistics->history(10), 'status'));
        $this->assertSame(1, substr_count($this->journaled(), '"action":"queue.message_killed"'));
    }

    /** Two passes in a row in one request (a pinned message, then a drain) each claim under their own token, and neither takes the other for dead. */
    public function testTwoPassesInARowEachClaimUnderItsOwnToken(): void
    {
        $claimedBy = [];
        $this->behaviour = function (QueueTestMessage $message) use (&$claimedBy): void {
            $claimedBy[$message->value] = (string)$this->db->scalar("SELECT delivered_by FROM {$this->schema->table()} WHERE delivered_at IS NOT NULL", '');
        };
        $this->send(2);
        $first = (int)$this->db->scalar("SELECT MIN(id) FROM {$this->schema->table()}", 0);

        $this->consumer->handleById($first);
        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(['m0', 'm1'], array_keys($claimedBy));
        $this->assertNotSame($claimedBy['m0'], $claimedBy['m1']);
        $this->assertSame([], $this->statistics->failed(), 'nothing was taken for dead');
        $this->assertSame(['m0', 'm1'], $this->handled);
    }

    /** The messages of a pass carry the pass's own token, the one its lock file is named after. */
    public function testEveryMessageOfAPassCarriesItsHolder(): void
    {
        $seen = [];
        $this->behaviour = function () use (&$seen): void {
            $claimed = $this->db->loadAll("SELECT delivered_by FROM {$this->schema->table()} WHERE delivered_at IS NOT NULL");
            $open = $this->statistics->openPasses();
            $seen[] = [array_column($claimed, 'delivered_by'), array_column($open, 'holder')];
        };
        $this->send(2);

        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertCount(2, $seen);
        foreach ($seen as [$claims, $holders]) {
            $this->assertCount(1, $holders);
            $this->assertSame($holders, $claims, 'the message being handled is claimed under the token of the pass that runs');
        }
    }

    public function testAPassRunsEvenWhenItsLockCannotBeCreated(): void
    {
        $notADirectory = (string)tempnam(sys_get_temp_dir(), 'yw-not-a-dir-');
        $this->holderLock = new QueueHolderLock(new FileLocks(QueueTestKit::locksIn($notADirectory)));
        $this->build($this->db, $this->clock);
        $this->send(1);

        try {
            $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));
        } finally {
            @unlink($notADirectory);
        }

        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason, 'without its lock, a pass is judged by the rules it had before');
        $this->assertSame(['m0'], $this->handled);
    }

    /** A paused drain does nothing but leave its trace: what dead consumers left waits for the first pass after the wiki wakes up, which reaps it as any other. */
    public function testAPausedPassReapsNothing(): void
    {
        $id = $this->abandonedAndExpired();
        $dead = QueueTestKit::recordPass($this->db, QueueConsumerMode::Worker, self::START, self::START, '', holder: QueueTestHolders::gone($this->lockDir));
        $this->setWikiStatus('hibernate');

        $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(QueuePassReport::PAUSED, $report->stopReason);
        $this->assertSame([], $this->statistics->failed());
        $this->assertSame('', $this->stopReasonOf($dead));

        $this->setWikiStatus('running');
        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame([$id], array_column($this->statistics->failed(), 'id'));
        $this->assertSame(QueuePassReport::KILLED, $this->stopReasonOf($dead));
    }

    public function testABlockingConsumerClosesDeadPassesWhenItStarts(): void
    {
        $dead = QueueTestKit::recordPass($this->db, QueueConsumerMode::Worker, self::START, self::START, '', holder: QueueTestHolders::gone($this->lockDir));
        $this->send(1);

        $report = $this->consumer->consume(QueueConsumerMode::Worker, 30, 512 * 1024 * 1024, 1);

        $this->assertSame(QueuePassReport::LIMIT, $report->stopReason);
        $this->assertSame(QueuePassReport::KILLED, $this->stopReasonOf($dead));
    }

    /** A backup that died left `archiving`, and nobody holds its lock: the next pass lifts the status and consumes, whether anyone opens the backups page or not. */
    public function testAPassLiftsTheStatusADeadBackupLeftAndConsumes(): void
    {
        $this->send(1);
        $this->setWikiStatus('archiving');

        $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
        $this->assertSame(['m0'], $this->handled);
        $this->assertSame('running', $this->wikiStatus());
    }

    /** A backup under way, in another process, holds the archive lock: its `archiving` pauses the pass. */
    public function testAHibernatedWikiDoesNotConsume(): void
    {
        $this->send(1);
        $letGo = QueueTestHolders::lockHeldByAnotherProcess($this->archiveLock());
        $this->setWikiStatus('archiving');

        try {
            $report = $this->consumer->drain(QueueConsumerMode::Request, new QueuePassLimits(PHP_INT_MAX));
        } finally {
            $this->assertSame(0, $letGo());
        }

        $this->assertSame(QueuePassReport::PAUSED, $report->stopReason);
        $this->assertSame([], $this->handled);
        $this->assertSame('pending', $this->statistics->messageState(1));
        $this->assertSame('archiving', $this->wikiStatus());
    }

    public function testHibernationMidPassStopsBeforeTheNextMessage(): void
    {
        $this->behaviour = function (): void {
            $this->setWikiStatus('hibernate');
        };
        $this->send(2);

        $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(1, $report->handled);
        $this->assertSame(QueuePassReport::PAUSED, $report->stopReason);
        $this->assertSame(1, $this->statistics->pendingCount());
    }

    public function testHandleByIdIgnoresPassLimitsAndHibernation(): void
    {
        $this->send(2);
        $this->setWikiStatus('hibernate');

        $report = $this->consumer->handleById(2);

        $this->assertSame(1, $report->handled);
        $this->assertSame(['m1'], $this->handled, 'that message, and only that one');
        $this->assertSame('handled', $this->statistics->messageState(2));
        $this->assertSame('pending', $this->statistics->messageState(1));
        $this->assertSame(0, $this->consumer->handleById(2)->handled, 'a message already handled is not run again');
    }

    /**
     * A pass nobody waits for raises PHP's limit before its first message, whatever that message's average.
     */
    public function testTheFirstMessageAfterTheResponseRunsWithTheLimitRaised(): void
    {
        $lifts = 0;
        $passLimits = $this->passLimitsUnderA30SecondLimit(true, $lifts);
        $this->passLimits = $passLimits;
        $this->build($this->db, new MockClock(new \DateTimeImmutable(self::START)));
        $this->send(2);

        $report = $this->consumer->drain(QueueConsumerMode::Request, $passLimits->forPass(), true);

        $this->assertSame(2, $report->handled);
        $this->assertSame(6, $lifts, 'the six steps from 30 s to the ceiling, once per pass and not once per message');
    }

    /** A visitor still waits: the limit is raised all the same. */
    public function testAPassBeforeTheResponseAlsoRaisesTheLimit(): void
    {
        $lifts = 0;
        $passLimits = $this->passLimitsUnderA30SecondLimit(true, $lifts);
        $this->passLimits = $passLimits;
        $this->build($this->db, new MockClock(new \DateTimeImmutable(self::START)));
        $this->send(2);

        $report = $this->consumer->drain(QueueConsumerMode::Request, $passLimits->forPass(), responseAlreadySent: false);

        $this->assertSame(2, $report->handled);
        $this->assertSame(6, $lifts);
    }

    /** The configuration is left as it is until a message is known to be there. */
    public function testAPassWithNothingToDoLeavesTheLimitAlone(): void
    {
        $lifts = 0;
        $passLimits = $this->passLimitsUnderA30SecondLimit(true, $lifts);
        $this->passLimits = $passLimits;
        $this->build($this->db, new MockClock(new \DateTimeImmutable(self::START)));

        $report = $this->consumer->drain(QueueConsumerMode::Request, $passLimits->forPass());

        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
        $this->assertSame(0, $lifts);
    }

    /** A limit already past the ceiling is kept, and running out its 80 % is the wiki's own stop, not a refusal of the host. */
    public function testAPassThatRanOutOfItsCeilingSaysSo(): void
    {
        $passLimits = new QueuePassLimitsFactory(
            new NativeClock(),
            static fn (string $name): string => ['max_execution_time' => '3600', 'memory_limit' => '-1'][$name],
            microtime(true) - 3000.0,
            static fn (int $seconds): bool => false
        );
        $this->passLimits = $passLimits;
        $this->build($this->db, new MockClock(new \DateTimeImmutable(self::START)));
        $this->send(1);

        $report = $this->consumer->drain(QueueConsumerMode::Request, $passLimits->forPass());

        $this->assertSame(0, $report->handled);
        $this->assertSame(QueuePassReport::TIME_CAP, $report->stopReason);
    }

    /** A host that keeps its limit: the pass takes no message past 80 % of it, and leaves the rest waiting. */
    public function testAPassStopsTakingMessagesOncePhpsLimitThatCouldNotBeRaisedIsReached(): void
    {
        $lifts = 0;
        $passLimits = $this->passLimitsUnderA30SecondLimit(false, $lifts, 22.0);
        $this->passLimits = $passLimits;
        $this->build($this->db, new MockClock(new \DateTimeImmutable(self::START)));
        $this->behaviour = static function (): void {
            usleep(3500000);
        };
        $this->send(3);

        $report = $this->consumer->drain(QueueConsumerMode::Request, $passLimits->forPass());

        $this->assertSame(1, $report->handled, 'the stop is at 80 % of 30 = 24 s, two seconds after the pass began; the first message ends at 25.5 s');
        $this->assertSame(QueuePassReport::PHP_LIMIT, $report->stopReason);
        $this->assertSame(2, $this->statistics->pendingCount(), 'what was not taken is still waiting, untouched');
    }

    /** The raise lasts the whole pass: 24.5 s into a 30 s request, the three messages (1.8 s) all run. */
    public function testARaisedLimitHoldsForTheWholePass(): void
    {
        $lifts = 0;
        $passLimits = $this->passLimitsUnderA30SecondLimit(true, $lifts, 24.5);
        $this->passLimits = $passLimits;
        $this->build($this->db, new MockClock(new \DateTimeImmutable(self::START)));
        $this->behaviour = static function (): void {
            usleep(600000);
        };
        $this->send(3);

        $report = $this->consumer->drain(QueueConsumerMode::Request, $passLimits->forPass());

        $this->assertSame(3, $report->handled);
        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
    }

    public function testAPassUnderALimitThatCouldNotBeRaisedGoesOnWhileTimeIsLeft(): void
    {
        $lifts = 0;
        $passLimits = $this->passLimitsUnderA30SecondLimit(false, $lifts);
        $this->passLimits = $passLimits;
        $this->build($this->db, new MockClock(new \DateTimeImmutable(self::START)));
        $this->send(2);

        $report = $this->consumer->drain(QueueConsumerMode::Request, $passLimits->forPass());

        $this->assertSame(2, $report->handled);
        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
    }

    public function testAPassIsRecorded(): void
    {
        $this->send(2);

        $this->consumer->drain(QueueConsumerMode::Request, new QueuePassLimits(PHP_INT_MAX), false);

        $pass = $this->statistics->lastPass();
        $this->assertNotNull($pass);
        $this->assertSame('request', $pass['mode']);
        $this->assertSame(2, $pass['handled']);
        $this->assertSame('empty', $pass['stopReason']);
        $this->assertTrue($pass['beforeResponse'], 'the visitor was still waiting');
        $this->assertNotNull($pass['finishedAt']);
        $this->assertGreaterThan(0, $pass['peakMemory']);
    }

    /** A pass that breaks does not throw: it reports an error, journals it with its mode, and closes its row. */
    public function testAPassThatBreaksReportsAnErrorAndJournalsIt(): void
    {
        $this->send(1);
        $this->db->query('DROP TABLE ' . $this->db->quoteIdentifier($this->schema->table()));

        $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(QueuePassReport::ERROR, $report->stopReason);
        $this->assertSame(0, $report->handled);
        $journal = $this->journaled();
        $this->assertStringContainsString('"action":"queue.pass_failed"', $journal);
        $this->assertStringContainsString('"target":"api"', $journal);
        $pass = $this->statistics->lastPass();
        $this->assertNotNull($pass);
        $this->assertSame('error', $pass['stopReason']);
        $this->assertNotNull($pass['finishedAt'], 'the pass row is not left open');
    }

    /** A pass lock that cannot even be created (here: `cache` is a file) is an error of the pass, journaled; nothing is taken. */
    public function testALockThatCannotBeCreatedIsAnError(): void
    {
        if (\defined('YESWIKI_INSTANCE_DIR')) {
            $this->markTestSkipped('the locks go to the instance directory here');
        }
        $this->send(1);
        $directory = sys_get_temp_dir() . '/yw-nolock-' . bin2hex(random_bytes(4));
        mkdir($directory);
        touch($directory . '/cache');
        $previous = (string)getcwd();

        try {
            chdir($directory);
            $this->build($this->db, $this->clock);
            $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));
        } finally {
            chdir($previous);
            @unlink($directory . '/cache');
            @rmdir($directory);
        }

        $this->assertSame(QueuePassReport::ERROR, $report->stopReason);
        $this->assertSame([], $this->handled);
        $this->assertSame(1, $this->statistics->pendingCount());
        $this->assertNull($this->statistics->lastPass(), 'no pass row was written');
        $this->assertStringContainsString('"action":"queue.pass_failed"', $this->journaled());
    }

    /** The memory ceiling is looked at before each message: limits already past it take none. */
    public function testDrainStopsOnMemoryBeforeTheNextMessage(): void
    {
        $this->send(2);

        $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(1));

        $this->assertSame(QueuePassReport::MEMORY, $report->stopReason);
        $this->assertSame([], $this->handled);
        $this->assertSame(2, $this->statistics->pendingCount());
    }

    /** A blocking consumer past its memory limit finishes the message in hand and stops; the rest waits for the next one. */
    public function testConsumeStopsOnMemoryAfterTheMessageInHand(): void
    {
        $this->send(2);

        $report = $this->consumer->consume(QueueConsumerMode::Worker, 5, 1);

        $this->assertSame(['m0'], $this->handled);
        $this->assertSame(QueuePassReport::MEMORY, $report->stopReason);
        $this->assertSame(1, $report->remaining);
        $this->assertSame('memory', $this->statistics->lastPass()['stopReason'] ?? null);
    }

    public function testTheScheduleIsTickedFirst(): void
    {
        $this->build($this->db, new MockClock(new \DateTimeImmutable(self::START)), static fn (): array => [RecurringMessage::every('30 minutes', new QueueTestTask())]);
        $this->assertSame([], $this->history->all(), 'never seen before the pass');

        $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));
        $this->assertSame(['test-task'], array_keys($this->history->all()), 'ticked: the task was noted');
    }

    public function testATickThatFailsDoesNotStopThePass(): void
    {
        // The history table out of the Scheduler's reach (none under that prefix): no tick gets anywhere, though the pass writes its rows.
        $elsewhere = new DbService(new ParameterBag(['db_driver' => 'sqlite', 'db_database' => $this->file, 'table_prefix' => 'ywgone_', 'debug' => false]));
        $this->build($this->db, $this->clock, static fn (): array => [RecurringMessage::every('30 minutes', new QueueTestTask())], new ScheduleHistory($elsewhere, new QueueSchema($elsewhere)));
        $this->send(2);

        $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(2, $report->handled);
        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
        $this->assertStringContainsString('queue.tick_failed', $this->journaled());
    }

    /** A provider that throws is the ticker's to leave out (QueueScheduleTicker::build()): the tick itself goes on, and so does the pass. */
    public function testAScheduleProviderThatThrowsDoesNotStopThePass(): void
    {
        $this->build($this->db, new MockClock(new \DateTimeImmutable(self::START)), static function (): array {
            throw new \RuntimeException('provider broken');
        });
        $this->send(2);

        $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(2, $report->handled);
        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
        $this->assertStringContainsString('queue.schedule_provider_failed', $this->journaled());
        $this->assertStringContainsString('provider broken', $this->journaled());
        $this->assertStringNotContainsString('queue.tick_failed', $this->journaled());
    }

    public function testConsumeStopsAtItsMessageLimit(): void
    {
        $this->send(3);

        $report = $this->consumer->consume(QueueConsumerMode::Worker, 30, 512 * 1024 * 1024, 2);

        $this->assertSame(2, $report->handled);
        $this->assertSame(QueuePassReport::LIMIT, $report->stopReason);
        $this->assertSame(1, $report->remaining);
        $pass = $this->statistics->lastPass();
        $this->assertNotNull($pass);
        $this->assertSame('worker', $pass['mode']);
        $this->assertSame('limit', $pass['stopReason']);
        $this->assertTrue($pass['blocking'], 'a blocking consumer counts as a start');
        $this->assertSame(512 * 1024 * 1024, $pass['memoryLimit']);
    }

    /** Replicas 19: a supervised consumer that found its queue empty leaves, its lock and its connection with it, and the binary starts it again later. */
    public function testAConsumerToldToLeaveWhenEmptyDrainsThenLeaves(): void
    {
        $this->send(2);

        $started = microtime(true);
        $report = $this->consumer->consume(QueueConsumerMode::Worker, 30, 512 * 1024 * 1024, null, null, true);

        $this->assertSame(2, $report->handled);
        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
        $this->assertSame(0, $report->remaining);
        $this->assertLessThan(10, microtime(true) - $started, 'it did not wait for its time limit');
    }

    public function testConsumeWaitsUntilItsTimeLimit(): void
    {
        $this->build($this->db, new NativeClock());
        $this->send(1);
        $this->db->query(
            "INSERT INTO {$this->schema->table()} (queue_name, message_type, body, headers, created_at, available_at, attempts)"
            . " VALUES ('default', ?, 'garbage', '[]', '2000-01-01 00:00:00', '2000-01-01 00:00:00', 0)",
            ['Gone\\Message']
        );

        $started = microtime(true);
        $report = $this->consumer->consume(QueueConsumerMode::Worker, 1, 512 * 1024 * 1024);

        $this->assertGreaterThanOrEqual(1.0, microtime(true) - $started, 'an empty queue is waited on, not left');
        $this->assertSame(QueuePassReport::TIME_LIMIT, $report->stopReason);
        $this->assertSame(['m0'], $this->handled, 'an undecodable message does not stop a blocking consumer');
        $this->assertSame(['Gone\Message'], array_column($this->statistics->failed(), 'type'));
    }

    public function testConsumeIdlesWhileHibernated(): void
    {
        $this->build($this->db, new NativeClock());
        $this->send(1);
        $this->setWikiStatus('hibernate');

        $report = $this->consumer->consume(QueueConsumerMode::Worker, 1, 512 * 1024 * 1024);

        $this->assertSame(QueuePassReport::TIME_LIMIT, $report->stopReason, 'it stays up, so the binary does not restart it in a loop');
        $this->assertSame([], $this->handled);
        $this->assertSame(1, $this->statistics->pendingCount());
    }

    /** A change of wiki status rewrites the configuration: a blocking consumer started asleep takes nothing and idles until that change ends it. */
    public function testABlockingConsumerStartedHibernatedIdlesUntilTheConfigurationChanges(): void
    {
        $this->build($this->db, new NativeClock());
        $this->send(2);
        $this->setWikiStatus('hibernate');
        $this->configurationChanged = true;

        $started = microtime(true);
        $report = $this->consumer->consume(QueueConsumerMode::Worker, 30, 512 * 1024 * 1024);

        $this->assertSame(QueuePassReport::CONFIG_CHANGED, $report->stopReason);
        $this->assertSame([], $this->handled, 'no message is taken while the wiki sleeps');
        $this->assertSame(2, $this->statistics->pendingCount());
        $this->assertLessThan(15.0, microtime(true) - $started, 'it did not wait for its time limit');
        $this->assertSame(1, $this->configurationAsked);
    }

    /** A message in hand when the consumer is told to stop is finished and acked, not left to be reaped as killed. */
    public function testASignalLetsTheMessageInHandFinish(): void
    {
        if (!function_exists('pcntl_signal') || !function_exists('posix_kill')) {
            $this->markTestSkipped('needs pcntl and posix');
        }
        $this->send(1);
        $marker = $this->file . '.started';
        $script = $this->script(<<<'PHP'
            require_once 'tests/src/Kernel/QueueTestKit.php';
            $kit = new YesWiki\Test\Kernel\QueueTestKit([YesWiki\Test\Kernel\QueueTestMessage::class => static function () use ($argv): void {
                touch($argv[2]);
                sleep(1);
            }], null, $argv[1]);
            register_shutdown_function($kit->dispose(...));
            $report = $kit->consumer->consume(YesWiki\Kernel\Queue\QueueConsumerMode::Worker, 60, 512 * 1024 * 1024);
            exit($report->stopReason === 'stopped' ? 0 : 9);
            PHP);
        $process = proc_open([PHP_BINARY, $script, $this->file, $marker], [], $pipes);
        $this->assertIsResource($process);

        try {
            for ($waited = 0; !file_exists($marker) && $waited < 100; $waited++) {
                usleep(100000);
            }
            $this->assertFileExists($marker, 'the consumer took the message');
            posix_kill(proc_get_status($process)['pid'], SIGTERM);
            $exit = null;
            for ($waited = 0; $waited < 150; $waited++) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    $exit = $status['exitcode'];
                    break;
                }
                usleep(100000);
            }
            $this->assertSame(0, $exit, 'it stopped by itself, with the reason "stopped"');
        } finally {
            $status = proc_get_status($process);
            if ($status['running']) {
                posix_kill($status['pid'], 9);
            }
            proc_close($process);
            @unlink($script);
            @unlink($marker);
        }

        $this->assertSame(0, $this->statistics->pendingCount());
        $this->assertSame([], $this->statistics->failed(), 'not reaped as killed');
        $this->assertSame(['handled'], array_column($this->statistics->history(10), 'status'));
        $this->assertSame('stopped', $this->statistics->lastPass()['stopReason'] ?? null);
    }

    public function testAWorkerTicksTheScheduleAtItsStart(): void
    {
        $this->build($this->db, new MockClock(new \DateTimeImmutable(self::START)), static fn (): array => [RecurringMessage::every('30 minutes', new QueueTestTask())]);
        $this->send(1);

        $this->consumer->consume(QueueConsumerMode::Worker, 30, 512 * 1024 * 1024, 1);

        $this->assertSame(['test-task'], array_keys($this->history->all()), 'no idle turn came: the start ticked');
    }

    /** The worker mode is the one that ticks on a queue with nothing in it: the start tick finds no task, a task that appears half a second later is seen when the minute the ticker waits has gone by on the clock is dispatched at an idle turn. */
    public function testAWorkerTicksTheScheduleAtAnIdleTurn(): void
    {
        $appearsAt = microtime(true) + 0.5;
        $this->build($this->db, self::fastClock(200.0), static fn (): array => microtime(true) < $appearsAt ? [] : [RecurringMessage::every('30 minutes', new QueueTestTask())]);

        $this->consumer->consume(QueueConsumerMode::Worker, 3, 512 * 1024 * 1024);

        $this->assertSame(['test-task'], array_keys($this->history->all()), 'the idle turn dispatched it');
    }

    public function testAHibernatedWorkerDoesNotTick(): void
    {
        $this->build($this->db, new MockClock(new \DateTimeImmutable(self::START)), static fn (): array => [RecurringMessage::every('30 minutes', new QueueTestTask())]);
        $this->send(1);
        $this->setWikiStatus('hibernate');

        $this->consumer->consume(QueueConsumerMode::Worker, 1, 512 * 1024 * 1024);

        $this->assertSame([], $this->history->all());
        $this->assertSame([], $this->handled);
    }

    /** What a long-lived consumer was built from is the configuration it booted on; once that changed, it stops between two messages for its supervisor to start a fresh one. */
    public function testConsumeStopsWhenTheConfigurationChanged(): void
    {
        $this->behaviour = function (QueueTestMessage $message): void {
            if ($message->value === 'm0') {
                $this->configurationChanged = true;
                usleep(5100000); // the configuration is asked again every five seconds of the real clock
            }
        };
        $this->send(3);

        $report = $this->consumer->consume(QueueConsumerMode::Worker, 30, 512 * 1024 * 1024);

        $this->assertSame(['m0'], $this->handled, 'the message in hand finishes; the next ones wait for a consumer that knows the new configuration');
        $this->assertSame(QueuePassReport::CONFIG_CHANGED, $report->stopReason);
        $this->assertSame(2, $report->remaining);
        $this->assertSame('config-changed', $this->statistics->lastPass()['stopReason'] ?? null);
    }

    /** The question reads and hashes files: it is asked every few seconds, not before every message. */
    public function testTheConfigurationIsAskedEveryFewSecondsOnly(): void
    {
        $this->configurationChanged = true;
        $this->send(3);

        $report = $this->consumer->consume(QueueConsumerMode::Worker, 30, 512 * 1024 * 1024, 3);

        $this->assertSame(['m0', 'm1', 'm2'], $this->handled, 'no time passed on the clock since it started');
        $this->assertSame(QueuePassReport::LIMIT, $report->stopReason);
        $this->assertSame(0, $this->configurationAsked);
    }

    /** An idle consumer notices too: nothing has to arrive for it to restart. */
    public function testAnIdleConsumerStopsWhenTheConfigurationChanged(): void
    {
        $this->build($this->db, self::fastClock(10.0));
        $this->configurationChanged = true;

        $started = microtime(true);
        $report = $this->consumer->consume(QueueConsumerMode::Worker, 30, 512 * 1024 * 1024);

        $this->assertSame(QueuePassReport::CONFIG_CHANGED, $report->stopReason);
        $this->assertLessThan(10.0, microtime(true) - $started, 'it did not wait for its time limit');
        $this->assertSame(1, $this->configurationAsked);
    }

    /** A pass that ends by itself is a process, or a request, of its own: it runs on the configuration it just read. */
    public function testDrainDoesNotAskAboutTheConfiguration(): void
    {
        $this->configurationChanged = true;
        $this->send(2);
        $this->assertInstanceOf(MockClock::class, $this->clock);
        $this->clock->sleep(60);

        $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
        $this->assertSame(['m0', 'm1'], $this->handled);
        $this->assertSame(0, $this->configurationAsked);
    }

    public function testTheKernelBuildsTheConsumer(): void
    {
        $services = self::getWiki()->services;

        $this->assertInstanceOf(QueueConsumer::class, $services->get(QueueConsumer::class));
        $this->assertInstanceOf(QueueStatistics::class, $services->get(QueueStatistics::class));
    }

    /**
     * The claim and the reaping of a pass that ends by itself on the engines the wiki may run on.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('realServers')]
    public function testOnARealServer(array $params): void
    {
        if (!QueueTransportTest::reachable($params)) {
            $this->markTestSkipped("no {$params['db_driver']} server at {$params['db_host']}");
        }
        $db = new DbService(new ParameterBag($params));
        $schema = new QueueSchema($db);
        $schema->drop();
        $schema->create();
        $journal = new JournalSchema($db);
        $journal->drop();
        $journal->create();

        try {
            $this->build($db, new MockClock(new \DateTimeImmutable(self::START)));
            $this->send(3);
            $this->transport->claimAs(QueueConsumerMode::Request);
            $this->transport->workFor(QueueTestHolders::passToken());
            [$abandoned] = [...$this->transport->get()];
            QueueTestHolders::hand($db, $schema, (int)$abandoned->last(TransportMessageIdStamp::class)?->getId(), QueueTestHolders::token());
            $this->assertInstanceOf(MockClock::class, $this->clock);
            $this->clock->sleep(QueueTransport::ABSENT_HOLDER_SECONDS + 1);
            $this->behaviour = function (QueueTestMessage $message): void {
                if ($message->value === 'm2') {
                    throw new \RuntimeException('later');
                }
            };

            $report = $this->consumer->drain(QueueConsumerMode::Api, new QueuePassLimits(PHP_INT_MAX));

            $this->assertSame(2, $report->handled);
            $this->assertSame(1, $report->remaining, 'the retry of m2 waits');
            $this->assertSame(['m1', 'm2'], $this->handled);
            $this->assertSame([(int)$abandoned->last(TransportMessageIdStamp::class)?->getId()], array_column($this->statistics->failed(), 'id'));
            $statuses = array_column($this->statistics->history(10), 'status');
            sort($statuses);
            $this->assertSame(['handled', 'killed', 'retried'], $statuses);
            $this->assertSame('empty', $this->statistics->lastPass()['stopReason'] ?? null);

            $retryId = (int)$db->scalar("SELECT id FROM {$schema->table()} WHERE queue_name = ? AND delivered_at IS NULL", 0, [QueueTransport::DEFAULT_QUEUE]);
            $this->behaviour = null;
            $this->assertSame(1, $this->consumer->handleById($retryId)->handled, 'a message asked for by id is taken even before its delay');
            $this->assertSame(['m1', 'm2', 'm2'], $this->handled);
        } finally {
            $schema->drop();
            $journal->drop();
        }
    }

    /** @return array<string, array{array<string, string|bool>}> */
    public static function realServers(): array
    {
        return QueueTransportTest::realServers();
    }

    /** (Re)builds the consumer and what it stands on over $db, on that clock. */
    /**
     * @param (\Closure(): list<RecurringMessage>)|null $schedule what the schedule declares; nothing by default
     * @param ScheduleHistory|null                      $history  where the Scheduler reads the tasks' history (default: that database's history)
     */
    private function build(DbService $db, ClockInterface $clock, ?\Closure $schedule = null, ?ScheduleHistory $history = null): void
    {
        $this->clock = $clock;
        $schema = new QueueSchema($db);
        $this->transport = new QueueTransport($db, $schema, clock: $clock, holderLock: $this->holderLock);
        $this->statistics = new QueueStatistics($db, $schema, $this->transport, $clock);
        $this->log = self::memory();
        $journal = new Journal(new ServiceLocator([
            DbService::class => fn (): DbService => $this->db,
            JournalSchema::class => fn (): JournalSchema => new JournalSchema($this->db),
            ThrowableFormatter::class => static fn (): ThrowableFormatter => new ThrowableFormatter(),
        ]));
        $journal->writeTo($this->log);

        $handler = function (object $message): void {
            $this->handled[] = $message instanceof QueueTestMessage || $message instanceof QueueTestOnceMessage || $message instanceof QueueTestToleratedMessage ? $message->value : $message::class;
            if ($this->behaviour !== null) {
                ($this->behaviour)($message);
            }
        };
        $locator = new QueueHandlerLocator(
            [QueueTestMessage::class => ['handler'], QueueTestOnceMessage::class => ['handler'], QueueTestToleratedMessage::class => ['handler']],
            new ServiceLocator(['handler' => static fn (): \Closure => $handler])
        );

        $recorder = new QueueRecorder($db, new QueueSchema($db), $clock);
        $bus = new QueueBus($this->transport, $locator, $journal, $recorder, QueueTestKit::noRequest());
        $this->history = $history ?? new ScheduleHistory($db, new QueueSchema($db));
        $provider = new class($schedule ?? static fn (): array => []) implements ProvidesSchedule {
            /** @param \Closure(): list<RecurringMessage> $schedule */
            public function __construct(private \Closure $schedule)
            {
            }

            public function recurringMessages(): array
            {
                return ($this->schedule)();
            }
        };

        $watch = new ConfigurationWatch();
        $watch->bind(function (): bool {
            $this->configurationAsked++;

            return $this->configurationChanged;
        });

        $this->consumer = new QueueConsumer(
            $this->transport,
            $bus,
            $recorder,
            $this->statistics,
            $this->passLimits ?? new QueuePassLimitsFactory(new NativeClock()),
            $hibernation = new HibernationService($this->params, new FileLocks(QueueTestKit::locksIn($this->lockDir))),
            $journal,
            new QueueScheduleTicker([$provider], $bus, $this->history, $recorder, new FileLocks(), $clock, $hibernation, $journal),
            $clock,
            $watch,
            $this->holderLock,
        );
    }

    /**
     * Sets the wiki's status the way production does (ArchiveService::setWikiStatus(), an update, an admin): in the config file, which the container's parameters do not follow.
     */
    private function setWikiStatus(string $status): void
    {
        $configuration = new ConfigurationService();
        $config = $configuration->getConfiguration($this->configFile);
        $config->load();
        $config['wiki_status'] = $status;
        $this->assertTrue($configuration->write($config));
    }

    /** What the config file says of the status now, `running` when it says nothing. */
    private function wikiStatus(): string
    {
        $config = (new ConfigurationService())->getConfiguration($this->configFile);
        $config->load();

        return trim((string)($config['wiki_status'] ?? '')) ?: 'running';
    }

    /** The archive lock the consumer reads, in the test's own lock directory, as another process addresses it. */
    private function archiveLock(): string
    {
        return $this->lockDir . '/' . FileLocks::pathOf(HibernationService::ARCHIVING_LOCK);
    }

    /** A message m0 that a `request` consumer on another machine took and died with, with no lock file to tell, the hour and the minute run out: answers its id. */
    private function abandonedAndExpired(): int
    {
        $this->send(1);
        $this->transport->claimAs(QueueConsumerMode::Request);
        $this->transport->workFor(QueueTestHolders::passToken());
        [$abandoned] = [...$this->transport->get()];
        $id = (int)$abandoned->last(TransportMessageIdStamp::class)?->getId();
        QueueTestHolders::hand($this->db, $this->schema, $id, QueueTestHolders::token());
        $this->assertInstanceOf(MockClock::class, $this->clock);
        $this->clock->sleep(QueueTransport::ABSENT_HOLDER_SECONDS + 1);

        return $id;
    }

    /** A PHP script run from the root of the program, its autoloader loaded, that ends with $body: answers its path, for the test to remove. */
    private function script(string $body): string
    {
        $script = sys_get_temp_dir() . '/yw-queue-process-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($script, "<?php\nchdir(" . var_export((string)getcwd(), true) . ");\nrequire 'vendor/autoload.php';\n" . $body . "\n");

        return $script;
    }

    /** Why that pass stopped, as its row says; '' while it is open. */
    private function stopReasonOf(int $passId): string
    {
        $row = $this->db->loadSingle('SELECT target FROM ' . $this->schema->historyTable() . ' WHERE id = ?', [$passId]);
        $this->assertNotNull($row);

        return (string)$row['target'];
    }

    /** When that pass was last seen at work, as its row says (UTC). */
    private function lastSeenOf(int $passId): string
    {
        $row = $this->db->loadSingle('SELECT last_at FROM ' . $this->schema->historyTable() . ' WHERE id = ?', [$passId]);
        $this->assertNotNull($row);

        return (string)$row['last_at'];
    }

    /** The absolute path of that token's lock file, in the test's own lock directory. */
    private function holderFile(string $token): string
    {
        $path = QueueHolderLock::pathOf($token);
        $this->assertNotNull($path);

        return $this->lockDir . '/' . $path;
    }

    /** Queues $count test messages, m0 to m{count-1}, in that order. */
    private function send(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->transport->send(new Envelope(new QueueTestMessage("m{$i}")));
        }
    }

    /**
     * PHP's limits as a server gives them, `max_execution_time` 30 s for a request that began $startedSecondsAgo, and no memory limit (the run's own use is not the test's business); set_time_limit() changes it when $hostLetsIt, and each call is counted in $lifts.
     */
    private function passLimitsUnderA30SecondLimit(bool $hostLetsIt, int &$lifts, float $startedSecondsAgo = 0.0): QueuePassLimitsFactory
    {
        $ini = ['max_execution_time' => '30', 'memory_limit' => '-1'];

        return new QueuePassLimitsFactory(
            new NativeClock(),
            static function (string $name) use (&$ini): string {
                return $ini[$name];
            },
            microtime(true) - $startedSecondsAgo,
            static function (int $seconds) use (&$ini, &$lifts, $hostLetsIt): bool {
                $lifts++;
                if ($hostLetsIt) {
                    $ini['max_execution_time'] = (string)$seconds;
                }

                return $hostLetsIt;
            }
        );
    }

    /** Everything the Journal wrote, as its JSON lines. */
    private function journaled(): string
    {
        rewind($this->log);

        return (string)stream_get_contents($this->log);
    }

    /** A clock that runs $factor times faster than the real one from now on: what lets a test see an idle consumer through seconds of its own time. */
    private static function fastClock(float $factor): ClockInterface
    {
        return new class($factor) implements ClockInterface {
            private readonly float $origin;

            public function __construct(private readonly float $factor)
            {
                $this->origin = microtime(true);
            }

            public function now(): \DateTimeImmutable
            {
                $at = $this->origin + (microtime(true) - $this->origin) * $this->factor;

                return (new \DateTimeImmutable('@' . sprintf('%.6F', $at)))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            }
        };
    }

    /** @return resource */
    private static function memory()
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);

        return $stream;
    }
}
