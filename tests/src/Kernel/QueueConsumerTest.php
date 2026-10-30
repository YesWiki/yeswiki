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
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueBudget;
use YesWiki\Kernel\Queue\QueueClaimHolder;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\ConfigurationWatch;
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
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTaskRows;
use YesWiki\Kernel\Service\Queue\QueueTransport;
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

/** The one consumer of the queue: budgeted passes and blocking loops, retries, killed messages, and what it records of all that. */
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
    private QueueBudgetContext $budgetContext;
    /** What the consumer reads PHP's limits through; null for the real ones. */
    private ?QueueBudgetFactory $budgets = null;

    /** What the test handler does after noting the message; null to just note it. */
    private ?\Closure $behaviour = null;
    /** What the consumer's ConfigurationWatch answers, and how many times it was asked. */
    private bool $configurationChanged = false;
    private int $configurationAsked = 0;
    /** The wiki's own dispatcher, where `queue.message_killed` is announced. */
    private EventDispatcher $events;
    /** @var list<string> */
    private array $handled = [];

    private ClockInterface $clock;
    private QueueTransport $transport;
    private QueueStatistics $statistics;
    private QueueConsumer $consumer;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-queue-') . '.db';
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
        $this->budgetContext = new QueueBudgetContext();
        $this->events = new EventDispatcher(new ServiceLocator([]), new ThrowableFormatter());
        $this->build($this->db, new MockClock(self::START, 'UTC'));
    }

    protected function tearDown(): void
    {
        unset($this->consumer, $this->statistics, $this->transport, $this->schema, $this->db);
        putenv($this->previousConfigFile === false ? 'YESWIKI_CONFIG_FILE' : 'YESWIKI_CONFIG_FILE=' . $this->previousConfigFile);
        @unlink($this->configFile);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
    }

    public function testDrainHandlesEverythingWhenTheBudgetAllows(): void
    {
        $this->send(5);

        $report = $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

        $this->assertSame(5, $report->handled);
        $this->assertSame(0, $report->remaining);
        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
        $this->assertSame(['m0', 'm1', 'm2', 'm3', 'm4'], $this->handled);
        $this->assertSame(array_fill(0, 5, 'handled'), array_column($this->statistics->history(10), 'status'));
    }

    public function testDrainStopsWhenTheBudgetIsSpent(): void
    {
        $this->build($this->db, new NativeClock());
        $this->behaviour = static function (): void {
            usleep(300000);
        };
        $this->send(5);

        $report = $this->consumer->drain(QueueConsumerMode::Request, $this->seconds(1));

        $this->assertLessThan(5, $report->handled);
        $this->assertGreaterThan(0, $report->handled);
        $this->assertSame(QueuePassReport::BUDGET, $report->stopReason);
        $this->assertGreaterThan(0, $report->remaining);
        $this->assertSame(5 - $report->handled, $this->statistics->pendingCount(), 'what was not taken is still waiting, untouched');
    }

    public function testNothingAffordableIsNoBudgetNotAFailure(): void
    {
        $this->send(1);

        $report = $this->consumer->drain(QueueConsumerMode::Request, $this->seconds(0));

        $this->assertSame(0, $report->handled);
        $this->assertSame(QueuePassReport::NO_BUDGET, $report->stopReason);
        $this->assertSame([], $this->statistics->failed());
        $this->assertSame(1, $this->statistics->pendingCount());
    }

    public function testAMessageLongerThanTheBudgetIsTakenFirstWhenNobodyWaits(): void
    {
        $this->recordAverage(QueueTestMessage::class, 91000);
        $this->send(2);

        $report = $this->consumer->drain(QueueConsumerMode::Request, $this->seconds(20), responseAlreadySent: true);

        $this->assertSame(['m0'], $this->handled, 'the first message runs whatever its average, or it would block the queue for good');
        $this->assertSame(QueuePassReport::BUDGET, $report->stopReason, 'the second one is still judged by its average');
        $this->assertSame(1, $this->statistics->pendingCount());
    }

    public function testAMessageLongerThanTheBudgetIsTakenFirstByACommand(): void
    {
        $this->recordAverage(QueueTestMessage::class, 91000);
        $this->send(1);

        $report = $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(20), false, true);

        $this->assertSame(['m0'], $this->handled);
        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
    }

    public function testAMessageLongerThanTheBudgetIsNotTakenWhileTheVisitorWaits(): void
    {
        $this->recordAverage(QueueTestMessage::class, 91000);
        $this->send(1);

        $report = $this->consumer->drain(QueueConsumerMode::Request, $this->seconds(3), responseAlreadySent: false);

        $this->assertSame([], $this->handled);
        $this->assertSame(QueuePassReport::NO_BUDGET, $report->stopReason);
        $this->assertSame(1, $this->statistics->pendingCount(), 'it waits for a pass nobody waits for');
    }

    public function testASpentBudgetTakesNothingEvenWhenNobodyWaits(): void
    {
        $this->send(1);

        $report = $this->consumer->drain(QueueConsumerMode::Request, $this->seconds(0), responseAlreadySent: true);

        $this->assertSame([], $this->handled, 'a budget the request already spent is not a pass that can run anything (§7.2)');
        $this->assertSame(QueuePassReport::NO_BUDGET, $report->stopReason);
    }

    public function testAFailingMessageIsRetriedThenFailed(): void
    {
        $this->behaviour = static function (): void {
            throw new \RuntimeException('remote is down');
        };
        $this->send(1);

        for ($pass = 0; $pass < 4; $pass++) {
            $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));
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

    public function testADefinitiveFailureIsJournaled(): void
    {
        $this->behaviour = static function (): void {
            throw new \RuntimeException('broken for good');
        };
        $this->transport->send(new Envelope(new QueueTestOnceMessage('once')));

        $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

        $journal = $this->journaled();
        $this->assertStringContainsString('"action":"queue.message_failed"', $journal);
        $this->assertStringContainsString('"target":"' . addcslashes(QueueTestOnceMessage::class, '\\') . '"', $journal);
        $this->assertStringContainsString('"channel":"diagnostic"', $journal);
    }

    public function testAnIntermediateFailureIsNotJournaled(): void
    {
        $this->behaviour = static function (): void {
            throw new \RuntimeException('try again later');
        };
        $this->send(1);

        $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

        $this->assertStringNotContainsString('queue.message_failed', $this->journaled());
        $this->assertSame(['retried'], array_column($this->statistics->history(10), 'status'));
    }

    /** R10: a tolerated message only stays out of the generic health check; its definitive failure is journaled like any other. */
    public function testAToleratedMessageIsJournaledToo(): void
    {
        $this->behaviour = static function (): void {
            throw new \RuntimeException('instance gone');
        };
        $this->transport->send(new Envelope(new QueueTestToleratedMessage('delivery')));

        $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

        $this->assertSame([QueueTestToleratedMessage::class], array_column($this->statistics->failed(), 'type'));
        $this->assertSame(['failed'], array_column($this->statistics->history(10), 'status'));
        $this->assertStringContainsString('"target":"' . addcslashes(QueueTestToleratedMessage::class, '\\') . '"', $this->journaled());
        $this->assertStringContainsString('queue.message_failed', $this->journaled());
    }

    public function testARetryPolicyOfZeroFailsAtOnce(): void
    {
        $this->behaviour = static function (): void {
            throw new \RuntimeException('archive failed');
        };
        $this->transport->send(new Envelope(new QueueTestOnceMessage('once')));

        $report = $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

        $this->assertSame(1, $report->handled);
        $this->assertSame(0, $this->statistics->pendingCount(), 'no retry is waiting');
        $this->assertCount(1, $this->statistics->failed());
        $this->assertSame(['failed'], array_column($this->statistics->history(10), 'status'));
    }

    public function testAMessageNobodyHandlesFailsAtOnce(): void
    {
        $this->transport->send(new Envelope(new \stdClass()));

        $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

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

        $report = $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

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

        $report = $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

        $this->assertSame(0, $report->handled, 'a killed message is not run again in the same mode');
        $this->assertSame([], $this->handled);
        $this->assertSame([$id], array_column($this->statistics->failed(), 'id'));
        $this->assertSame('request', $this->statistics->failed()[0]['killedIn']);
        $history = $this->statistics->history(10);
        $this->assertSame(['killed'], array_column($history, 'status'));
        $this->assertSame('request', $history[0]['mode'], 'the mode it was killed in, not the mode that found it');
        $this->assertSame(62000, $history[0]['durationMs']);
        $this->assertStringContainsString('"action":"queue.message_killed"', $this->journaled());
        $this->assertSame('killed', $this->statistics->messageState($id));
    }

    /**
     * R19: a consumer still at work is not reaped however long it takes past its limit; once the host kills it (SIGKILL: nothing of it runs), the next pass fails its message at once.
     *
     * The consumer is a real process, which takes the message and sleeps on it.
     */
    public function testAConsumerTheHostKillsIsReapedByTheNextPassAtOnce(): void
    {
        if (QueueClaimHolder::thisProcess()->machine === '' || !function_exists('posix_kill')) {
            $this->markTestSkipped('this machine does not show its processes in /proc');
        }
        $this->send(1);
        $script = $this->script(<<<'PHP'
            require_once 'tests/src/Kernel/QueueTestMessage.php';
            $db = new YesWiki\Kernel\Service\DbService(new Symfony\Component\DependencyInjection\ParameterBag\ParameterBag(['db_driver' => 'sqlite', 'db_database' => $argv[1], 'table_prefix' => 'ywq_', 'debug' => false]));
            $transport = new YesWiki\Kernel\Service\Queue\QueueTransport($db, new YesWiki\Kernel\Service\Queue\QueueSchema($db));
            $transport->claimAs(YesWiki\Kernel\Queue\QueueConsumerMode::Request, 1);
            echo count([...$transport->get()]), "\n";
            sleep(60);
            PHP);
        $process = proc_open([PHP_BINARY, $script, $this->file], [1 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);

        try {
            $this->assertSame("1\n", fgets($pipes[1]), 'the consumer took the message');
            $this->build($this->db, new MockClock(new \DateTimeImmutable('+2 hours')));
            $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));
            $this->assertSame([], $this->statistics->failed(), 'two hours past its one-second limit, it is still at work');
            $this->assertSame([], $this->handled);

            posix_kill(proc_get_status($process)['pid'], 9);
            fclose($pipes[1]);
            proc_close($process);
            $process = null;
            $this->build($this->db, new NativeClock());
            $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

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

    /**
     * R19: a consumer whose process ends in the middle of a message (here an `exit` in its handler; a fatal error or PHP's time limit end it the same way) fails it as killed itself, once, before the next pass.
     *
     * The consumer is a real process, a QueueTestKit on this test's database.
     */
    public function testAConsumerThatEndsHoldingAMessageFailsItAsKilledOnce(): void
    {
        $this->send(1);
        $script = $this->script(<<<'PHP'
            require_once 'tests/src/Kernel/QueueTestKit.php';
            $kit = new YesWiki\Test\Kernel\QueueTestKit([YesWiki\Test\Kernel\QueueTestMessage::class => static function (): void {
                exit(3);
            }], null, $argv[1]);
            // Registered before the consumer's own: the kit's config and locks go first, the database is the test's.
            register_shutdown_function($kit->dispose(...));
            $kit->consumer->drain(YesWiki\Kernel\Queue\QueueConsumerMode::Command, YesWiki\Kernel\Queue\QueueBudget::unlimited(300));
            PHP);

        try {
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($this->file) . ' 2>&1', $out, $status);
        } finally {
            @unlink($script);
        }

        $this->assertSame(3, $status, implode("\n", $out));
        $this->assertSame(['command'], array_column($this->statistics->failed(), 'killedIn'), 'failed before any pass came');
        $this->assertSame(['killed'], array_column($this->statistics->history(10), 'status'));
        $this->assertStringContainsString('queue.message_killed', (string)$this->db->scalar('SELECT GROUP_CONCAT(action) FROM ' . (new JournalSchema($this->db))->table()));

        $this->build($this->db, new NativeClock());
        $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));
        $this->assertSame(['killed'], array_column($this->statistics->history(10), 'status'), 'recorded once');
    }

    /** R19: a holder declared dead that finishes after all leaves its failure where it is, and the Journal says how it ended. */
    public function testAMessageThatEndsAfterItsHolderWasDeclaredDeadStaysFailed(): void
    {
        $this->behaviour = function (): void {
            // What PHP would do if the process ended now; here it goes on, and the Worker acks.
            $this->consumer->failWhatItHolds();
        };
        $this->send(1);

        $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

        $this->assertSame(['m0'], $this->handled);
        $this->assertSame(['command'], array_column($this->statistics->failed(), 'killedIn'));
        $this->assertStringContainsString('"action":"queue.message_ended_late"', $this->journaled());
    }

    /**
     * R18: reaping changes no content, so a paused pass still does it.
     *
     * A backup PHP killed leaves the wiki `archiving`; were reaping to wait for the wiki to wake
     * up, its claim would never be reaped, and nothing would ever tell the backup it died.
     */
    public function testAPausedPassStillReapsWhatWasKilled(): void
    {
        $id = $this->abandonedAndExpired();
        $this->transport->send(new Envelope(new QueueTestMessage('next')));
        $this->setWikiStatus('archiving');

        $report = $this->consumer->drain(QueueConsumerMode::Request, $this->seconds(30));

        $this->assertSame(QueuePassReport::PAUSED, $report->stopReason);
        $this->assertSame([], $this->handled, 'reaped, not consumed');
        $this->assertSame([$id], array_column($this->statistics->failed(), 'id'));
        $this->assertSame(['killed'], array_column($this->statistics->history(10), 'status'));
        $this->assertStringContainsString('"action":"queue.message_killed"', $this->journaled());
        $this->assertNull($this->statistics->lastPass(), 'a paused pass records no pass row, as before');
        $this->assertSame(1, $this->statistics->pendingCount());
    }

    /**
     * A paused pass whose reaping lifted the pause (a killed backup: its listener sets the wiki back to running) goes on with the pass.
     *
     * Otherwise the first "Consommer maintenant" after a kill, which the killed alert recommends, does nothing.
     */
    public function testAPassWhosePauseTheReapingLiftedGoesOn(): void
    {
        $this->events->addListener(QueueConsumer::MESSAGE_KILLED, function (): void {
            $this->setWikiStatus('running');
        });
        $id = $this->abandonedAndExpired();
        $this->transport->send(new Envelope(new QueueTestMessage('next')));
        $this->setWikiStatus('archiving');

        $report = $this->consumer->drain(QueueConsumerMode::Request, $this->seconds(30));

        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
        $this->assertSame(['next'], $this->handled);
        $this->assertSame([$id], array_column($this->statistics->failed(), 'id'));
        $this->assertSame(['handled', 'killed'], array_column($this->statistics->history(10), 'status'), 'reaped once, then the pass');
    }

    /** What a dead consumer left is announced to the wiki: its id, its class, and the message itself, for whoever followed it. */
    public function testAKilledMessageIsAnnounced(): void
    {
        $heard = [];
        $this->events->addListener(QueueConsumer::MESSAGE_KILLED, function (Event $event) use (&$heard): void {
            $heard[] = $event->getData();
        });
        $id = $this->abandonedAndExpired();

        $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

        $this->assertCount(1, $heard);
        $this->assertSame($id, $heard[0]['messageId']);
        $this->assertSame(QueueTestMessage::class, $heard[0]['type']);
        $this->assertSame('request', $heard[0]['mode'], 'the mode it was killed in');
        $this->assertInstanceOf(QueueTestMessage::class, $heard[0]['message']);
        $this->assertSame('m0', $heard[0]['message']->value);
        $this->assertSame('killed', $this->statistics->messageState($id), 'announced once it is in `failed`');
    }

    /** A blocking consumer announces it too, whatever the wiki's status. */
    public function testABlockingConsumerAnnouncesWhatItReaps(): void
    {
        $heard = [];
        $this->events->addListener(QueueConsumer::MESSAGE_KILLED, function (Event $event) use (&$heard): void {
            $heard[] = $event->getData()['messageId'];
        });
        $id = $this->abandonedAndExpired();
        $this->setWikiStatus('archiving');

        $this->consumer->consume(QueueConsumerMode::Worker, 1, 512 * 1024 * 1024);

        $this->assertSame([$id], $heard);
    }

    public function testAKilledMessageThatCannotBeReadIsAnnouncedWithoutIt(): void
    {
        $heard = [];
        $this->events->addListener(QueueConsumer::MESSAGE_KILLED, function (Event $event) use (&$heard): void {
            $heard[] = $event->getData();
        });
        $this->db->query(
            "INSERT INTO {$this->schema->table()} (queue_name, message_type, body, headers, created_at, available_at, attempts, delivered_at, delivered_by, delivered_mode, delivered_limit)"
            . " VALUES ('default', ?, 'garbage', '[]', ?, ?, 0, ?, 'gone', 'api', 9)",
            ['Gone\\Message', '2026-10-23 11:00:00', '2026-10-23 11:00:00', '2026-10-23 11:00:00']
        );

        $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

        $this->assertCount(1, $heard);
        $this->assertSame('Gone\\Message', $heard[0]['type']);
        $this->assertNull($heard[0]['message']);
    }

    /** A listener that throws is journaled; the ones after it are still told, and the pass goes on. */
    public function testAListenerThatThrowsNeverBreaksThePass(): void
    {
        $heard = 0;
        $this->events->addListener(QueueConsumer::MESSAGE_KILLED, static function (): void {
            throw new \RuntimeException('listener broken');
        });
        $this->events->addListener(QueueConsumer::MESSAGE_KILLED, function () use (&$heard): void {
            $heard++;
        });
        $this->abandonedAndExpired();
        $this->transport->send(new Envelope(new QueueTestMessage('next')));

        $report = $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
        $this->assertSame(['next'], $this->handled);
        $this->assertSame(1, $heard);
        $this->assertStringContainsString('"action":"queue.listener_failed"', $this->journaled());
        $this->assertStringContainsString('listener broken', $this->journaled());
    }

    public function testOnlyOnePassAtATime(): void
    {
        $this->send(1);
        $lock = new RuntimeLock();
        $held = $lock->acquire(QueueConsumer::PASS_LOCK);
        $this->assertTrue($lock->tryLock($held));

        try {
            $report = $this->consumer->drain(QueueConsumerMode::Request, $this->seconds(30));
        } finally {
            $lock->release($held);
        }

        $this->assertSame(QueuePassReport::LOCKED, $report->stopReason);
        $this->assertSame(0, $report->handled);
        $this->assertSame(1, $report->remaining);
        $this->assertSame([], $this->handled);
        $this->assertNull($this->statistics->lastPass(), 'a locked pass records nothing');

        $this->assertSame(1, $this->consumer->drain(QueueConsumerMode::Request, $this->seconds(30))->handled, 'and the lock is free again after');
    }

    /** A cron or an API call that comes back while the end of a request consumes in its place must still be seen: it leaves a row, closed at once. */
    public function testAConsumerPassThatFindsTheLockTakenLeavesARow(): void
    {
        $this->send(1);
        $lock = new RuntimeLock();
        $held = $lock->acquire(QueueConsumer::PASS_LOCK);
        $this->assertTrue($lock->tryLock($held));

        try {
            $report = $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));
        } finally {
            $lock->release($held);
        }

        $this->assertSame(QueuePassReport::LOCKED, $report->stopReason);
        $pass = $this->statistics->lastPass();
        $this->assertNotNull($pass);
        $this->assertSame('command', $pass['mode']);
        $this->assertSame(QueuePassReport::LOCKED, $pass['stopReason']);
        $this->assertSame('consumer', $pass['origin']);
    }

    public function testAManualPassThatFindsTheLockTakenLeavesNoRow(): void
    {
        $this->send(1);
        $lock = new RuntimeLock();
        $held = $lock->acquire(QueueConsumer::PASS_LOCK);
        $this->assertTrue($lock->tryLock($held));

        try {
            $report = $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30), origin: QueuePassOrigin::Manual);
        } finally {
            $lock->release($held);
        }

        $this->assertSame(QueuePassReport::LOCKED, $report->stopReason);
        $this->assertNull($this->statistics->lastPass());
    }

    /** A cron that keeps running while the wiki sleeps must still be seen when it wakes up, or it would be taken for gone and the end of requests would stand in for it. */
    public function testAConsumerPassThatFindsTheWikiHibernatedLeavesARow(): void
    {
        $this->send(1);
        $this->setWikiStatus('hibernate');

        $report = $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

        $this->assertSame(QueuePassReport::PAUSED, $report->stopReason);
        $this->assertSame([], $this->handled);
        $pass = $this->statistics->lastPass();
        $this->assertNotNull($pass);
        $this->assertSame('command', $pass['mode']);
        $this->assertSame(QueuePassReport::PAUSED, $pass['stopReason']);
        $this->assertSame('consumer', $pass['origin']);
        $this->assertSame($this->clock->now()->getTimestamp(), $this->statistics->lastSeenAt(QueueConsumerMode::Command)?->getTimestamp());
    }

    public function testAManualPassThatFindsTheWikiHibernatedLeavesNoRow(): void
    {
        $this->setWikiStatus('hibernate');

        $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30), origin: QueuePassOrigin::Manual);

        $this->assertNull($this->statistics->lastPass());
    }

    public function testAHibernatedWikiDoesNotConsume(): void
    {
        $this->send(1);
        $this->setWikiStatus('archiving');

        $report = $this->consumer->drain(QueueConsumerMode::Request, $this->seconds(30));

        $this->assertSame(QueuePassReport::PAUSED, $report->stopReason);
        $this->assertSame([], $this->handled);
        $this->assertSame('pending', $this->statistics->messageState(1));
    }

    public function testHibernationMidPassStopsBeforeTheNextMessage(): void
    {
        $this->behaviour = function (): void {
            $this->setWikiStatus('hibernate');
        };
        $this->send(2);

        $report = $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

        $this->assertSame(1, $report->handled);
        $this->assertSame(QueuePassReport::PAUSED, $report->stopReason);
        $this->assertSame(1, $this->statistics->pendingCount());
    }

    public function testHandleByIdIgnoresBudgetAndHibernation(): void
    {
        $this->send(2);
        $this->setWikiStatus('hibernate');

        $report = $this->consumer->handleById(2, QueueConsumerMode::Request);

        $this->assertSame(1, $report->handled);
        $this->assertSame(['m1'], $this->handled, 'that message, and only that one');
        $this->assertSame('handled', $this->statistics->messageState(2));
        $this->assertSame('pending', $this->statistics->messageState(1));
        $this->assertSame(0, $this->consumer->handleById(2, QueueConsumerMode::Request)->handled, 'a message already handled is not run again');
    }

    /**
     * A pinned message runs after set_time_limit(0), which a host can disable: PHP's limit, in CPU time, then still
     * reads 30 s, but a backup waiting on the disk or the network outlives it. Its claim must not be judged by that
     * limit, or a pass in another process would fail it as killed while it runs and lift the `archiving` it holds.
     */
    public function testAPinnedMessageIsNotHeldToPhpsTimeLimit(): void
    {
        $this->budgets = new QueueBudgetFactory(static fn (string $name): string => $name === 'max_execution_time' ? '30' : '128M');
        $this->build($this->db, new MockClock(self::START, 'UTC'));
        $limits = [];
        $this->behaviour = function () use (&$limits): void {
            $limits[] = (int)$this->db->scalar("SELECT delivered_limit FROM {$this->schema->table()} WHERE delivered_at IS NOT NULL", null);
        };
        $this->send(1);

        $this->consumer->handleById(1, QueueConsumerMode::Request);

        $this->assertSame([QueueBudgetFactory::KILL_LIMIT_NO_PHP_LIMIT], $limits);
    }

    public function testAPassIsRecorded(): void
    {
        $this->send(2);

        $this->consumer->drain(QueueConsumerMode::Request, $this->seconds(30), true, false);

        $pass = $this->statistics->lastPass();
        $this->assertNotNull($pass);
        $this->assertSame('request', $pass['mode']);
        $this->assertSame(2, $pass['handled']);
        $this->assertSame('empty', $pass['stopReason']);
        $this->assertTrue($pass['beforeResponse'], 'the visitor was still waiting');
        $this->assertNotNull($pass['finishedAt']);
        $this->assertGreaterThan(0, $pass['peakMemory']);
    }

    public function testTheBudgetIsKnownDuringThePassOnly(): void
    {
        $seen = [];
        $this->behaviour = function () use (&$seen): void {
            $seen[] = $this->budgetContext->remainingSeconds();
        };
        $this->send(1);

        $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

        $this->assertNotNull($seen[0] ?? null, 'a handler can ask how long it has');
        $this->assertNull($this->budgetContext->remainingSeconds(), 'code after the pass is not told "0 s left"');
    }

    public function testTheScheduleIsTickedFirstUnlessAskedNotTo(): void
    {
        $this->build($this->db, new MockClock(self::START, 'UTC'), static fn (): array => [RecurringMessage::every('30 minutes', new QueueTestTask())]);
        $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30), false);
        $this->assertNull($this->statistics->scheduleDueAt(), 'not ticked');

        $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));
        $this->assertNotNull($this->statistics->scheduleDueAt(), 'ticked: the schedule has started');
    }

    public function testATickThatFailsDoesNotStopThePass(): void
    {
        // A checkpoint whose due time cannot be read: no tick gets anywhere.
        (new QueueTaskRows($this->db, $this->schema))->update(QueueCheckpoint::ROW, static fn (array $fields): array => ['next_due_at' => 'not a time']);
        $this->send(2);

        $report = $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

        $this->assertSame(2, $report->handled);
        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
        $this->assertStringContainsString('queue.tick_failed', $this->journaled());
    }

    /** A provider that throws is the ticker's to leave out (QueueScheduleTicker::build()): the tick itself goes on, and so does the pass. */
    public function testAScheduleProviderThatThrowsDoesNotStopThePass(): void
    {
        $this->build($this->db, new MockClock(self::START, 'UTC'), static function (): array {
            throw new \RuntimeException('provider broken');
        });
        $this->send(2);

        $report = $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

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
        $report = $this->consumer->consume(QueueConsumerMode::Binary, 1, 512 * 1024 * 1024);

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

    /** R17: what a long-lived consumer was built from is the configuration it booted on; once that changed, it stops between two messages for its supervisor to start a fresh one. */
    public function testConsumeStopsWhenTheConfigurationChanged(): void
    {
        $this->behaviour = function (QueueTestMessage $message): void {
            if ($message->value === 'm0') {
                $this->configurationChanged = true;
                $this->assertInstanceOf(MockClock::class, $this->clock);
                $this->clock->sleep(6);
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
        $report = $this->consumer->consume(QueueConsumerMode::Binary, 30, 512 * 1024 * 1024);

        $this->assertSame(QueuePassReport::CONFIG_CHANGED, $report->stopReason);
        $this->assertLessThan(10.0, microtime(true) - $started, 'it did not wait for its time limit');
        $this->assertSame(1, $this->configurationAsked);
    }

    /** A budgeted pass is a process, or a request, of its own: it runs on the configuration it just read. */
    public function testDrainDoesNotAskAboutTheConfiguration(): void
    {
        $this->configurationChanged = true;
        $this->send(2);
        $this->assertInstanceOf(MockClock::class, $this->clock);
        $this->clock->sleep(60);

        $report = $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
        $this->assertSame(['m0', 'm1'], $this->handled);
        $this->assertSame(0, $this->configurationAsked);
    }

    public function testTheKernelBuildsTheConsumer(): void
    {
        $services = self::getWiki()->services;

        $this->assertInstanceOf(QueueConsumer::class, $services->get(QueueConsumer::class));
        $this->assertInstanceOf(QueueStatistics::class, $services->get(QueueStatistics::class));
        $this->assertSame(
            $services->get(ConfigurationWatch::class),
            (new \ReflectionProperty(QueueConsumer::class, 'configuration'))->getValue($services->get(QueueConsumer::class)),
            'the consumer asks the watch the runtime bound at boot'
        );
    }

    /**
     * The claim and the reaping of a budgeted pass on the engines the wiki may run on.
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
            $this->build($db, new MockClock(self::START, 'UTC'));
            $this->send(3);
            $this->transport->claimAs(QueueConsumerMode::Request, 1);
            [$abandoned] = [...$this->transport->get()];
            QueueTestHolders::hand($db, $schema, (int)$abandoned->last(TransportMessageIdStamp::class)?->getId(), QueueTestHolders::elsewhere());
            $this->assertInstanceOf(MockClock::class, $this->clock);
            $this->clock->sleep(62);
            $this->behaviour = function (QueueTestMessage $message): void {
                if ($message->value === 'm2') {
                    throw new \RuntimeException('later');
                }
            };

            $report = $this->consumer->drain(QueueConsumerMode::Command, $this->seconds(30));

            $this->assertSame(2, $report->handled);
            $this->assertSame(1, $report->remaining, 'the retry of m2 waits');
            $this->assertSame(['m1', 'm2'], $this->handled);
            $this->assertSame([(int)$abandoned->last(TransportMessageIdStamp::class)?->getId()], array_column($this->statistics->failed(), 'id'));
            $statuses = array_column($this->statistics->history(10), 'status');
            sort($statuses);
            $this->assertSame(['handled', 'killed', 'retried'], $statuses);
            $this->assertSame('empty', $this->statistics->lastPass()['stopReason'] ?? null);

            [$retry] = [...$this->transport->all()];
            $this->behaviour = null;
            $this->assertSame(1, $this->consumer->handleById((int)$retry->last(TransportMessageIdStamp::class)?->getId(), QueueConsumerMode::Command)->handled, 'a message asked for by id is taken even before its delay');
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
     */
    private function build(DbService $db, ClockInterface $clock, ?\Closure $schedule = null): void
    {
        $this->clock = $clock;
        $schema = new QueueSchema($db);
        $this->transport = new QueueTransport($db, $schema, clock: $clock);
        $this->statistics = new QueueStatistics($db, $schema, new JournalSchema($db), $this->transport, $clock);
        $this->log = self::memory();
        $journal = new Journal(new ServiceLocator([
            DbService::class => fn (): DbService => $this->db,
            JournalSchema::class => fn (): JournalSchema => new JournalSchema($this->db),
            ThrowableFormatter::class => static fn (): ThrowableFormatter => new ThrowableFormatter(),
        ]));
        $journal->writeTo($this->log);

        $handler = function (object $message): void {
            $this->handled[] = $message instanceof QueueTestMessage || $message instanceof QueueTestOnceMessage ? $message->value : $message::class;
            if ($this->behaviour !== null) {
                ($this->behaviour)($message);
            }
        };
        $locator = new QueueHandlerLocator(
            [QueueTestMessage::class => ['handler'], QueueTestOnceMessage::class => ['handler']],
            new ServiceLocator(['handler' => static fn (): \Closure => $handler])
        );

        $bus = new QueueBus($this->transport, $locator, $journal);
        $recorder = new QueueRecorder($db, $schema, new JournalSchema($db), $clock);
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
            $this->budgetContext,
            $this->budgets ?? new QueueBudgetFactory(),
            new HibernationService($this->params),
            new RuntimeLock(),
            $journal,
            new QueueScheduleTicker([$provider], $bus, new QueueCheckpoint($db, $schema, new RuntimeLock()), $recorder, $clock, $journal),
            $clock,
            configuration: $watch,
            services: new ServiceLocator([EventDispatcher::class => fn (): EventDispatcher => $this->events]),
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

    /** A message m0 that a `request` consumer on another machine took and died with, its limit (1 s and the grace) run out: answers its id. */
    private function abandonedAndExpired(): int
    {
        $this->send(1);
        $this->transport->claimAs(QueueConsumerMode::Request, 1);
        [$abandoned] = [...$this->transport->get()];
        $id = (int)$abandoned->last(TransportMessageIdStamp::class)?->getId();
        QueueTestHolders::hand($this->db, $this->schema, $id, QueueTestHolders::elsewhere());
        $this->assertInstanceOf(MockClock::class, $this->clock);
        $this->clock->sleep(62);

        return $id;
    }

    /** A PHP script run from the root of the program, its autoloader loaded, that ends with $body: answers its path, for the test to remove. */
    private function script(string $body): string
    {
        $script = sys_get_temp_dir() . '/yw-queue-process-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($script, "<?php\nchdir(" . var_export((string)getcwd(), true) . ");\nrequire 'vendor/autoload.php';\n" . $body . "\n");

        return $script;
    }

    /** Queues $count test messages, m0 to m{count-1}, in that order. */
    private function send(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->transport->send(new Envelope(new QueueTestMessage("m{$i}")));
        }
    }

    /** Has the history say that messages of that type took that long when they succeeded. */
    private function recordAverage(string $type, int $durationMs): void
    {
        QueueTestKit::recordRun($this->db, $type, QueueRecorder::HANDLED, $durationMs, '2026-10-23 11:01:31', mode: QueueConsumerMode::Command);
    }

    /** A budget of that many seconds from now, with no memory bound. */
    private function seconds(float $seconds): QueueBudget
    {
        return new QueueBudget(microtime(true) + $seconds, PHP_INT_MAX, 30);
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

                return (new \DateTimeImmutable('@' . sprintf('%.6F', $at)))->setTimezone(new \DateTimeZone('UTC'));
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
