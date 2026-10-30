<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Kernel\Queue\QueueBudget;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueJournalEntry;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueCheckpoint;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTaskRows;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestMessage.php';
require_once 'tests/src/Kernel/QueueTestTask.php';
require_once 'tests/src/Kernel/QueueTransportTest.php';
require_once 'tests/src/Kernel/QueueTestKit.php';

/** What the consumer writes about its passes and messages, and what the health checks and the admin page read back. */
#[CoversClass(QueueRecorder::class)]
#[CoversClass(QueueStatistics::class)]
#[CoversClass(QueueJournalEntry::class)]
#[CoversClass(QueueTaskRows::class)]
class QueueRecorderTest extends YesWikiTestCase
{
    private const START = '2026-10-23 12:00:00';

    private string $file;
    private MockClock $clock;
    private DbService $db;
    private QueueSchema $schema;
    private JournalSchema $journal;
    private QueueTransport $transport;
    private QueueRecorder $recorder;
    private QueueStatistics $statistics;
    /** Whether the tables are on a database server, which the test must clean up, rather than in its own file. */
    private bool $onServer = false;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-queue-') . '.db';
        $this->clock = new MockClock(self::START, 'UTC');
        $this->use(new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywq_',
            'debug' => false,
        ])));
    }

    protected function tearDown(): void
    {
        if ($this->onServer) {
            $this->schema->drop();
            $this->journal->drop();
        }
        unset($this->statistics, $this->recorder, $this->transport, $this->journal, $this->schema, $this->db);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testARunIsAJournalRow(array $params): void
    {
        $this->on($params);
        $start = (float)$this->clock->now()->format('U.u');
        $this->clock->sleep(1.5);

        $this->recorder->messageFinished(7, 'App\\Foo', 'digest-daily', QueueRecorder::FAILED, QueueConsumerMode::Command, $start, 'boom');

        $rows = $this->db->loadAll("SELECT * FROM {$this->journal->table()}");
        $this->assertCount(1, $rows);
        [$row] = $rows;
        $this->assertSame('queue', $row['channel']);
        $this->assertSame('queue.run.failed', $row['action']);
        $this->assertSame('App\\Foo', $row['target']);
        $this->assertSame('command', $row['actor']);
        $this->assertSame('error', $row['level']);
        $this->assertSame(1, (int)$row['repeat']);
        $this->assertNull($row['fingerprint']);
        $this->assertNull($row['day']);
        $this->assertSame('2026-10-23 12:00:01', $row['at'], 'a run is dated by its end, in UTC');
        $this->assertSame($row['at'], $row['last_at']);
        $context = $this->context($row);
        $this->assertSame(7, $context['message_id']);
        $this->assertSame('digest-daily', $context['task_id']);
        $this->assertSame(1500, $context['duration_ms'], 'it began that long before it ended: its start is not written apart');
        $this->assertSame('boom', $context['message'], 'the cause is where every Journal entry keeps its message');

        $this->recorder->messageFinished(8, 'App\\Foo', null, QueueRecorder::HANDLED, QueueConsumerMode::Command, $start, null);
        $handled = $this->db->loadSingle("SELECT * FROM {$this->journal->table()} WHERE action = ?", ['queue.run.handled']);
        $this->assertNotNull($handled);
        $this->assertSame('info', $handled['level']);
        $this->assertEqualsCanonicalizing(['message_id', 'duration_ms'], array_keys($this->context($handled)), 'a null is left out, never written');
    }

    /** The queue's rows never reach stderr: the recorder is given no Journal nor any logger to write them through. */
    public function testTheRecorderHasNoWayToTheServerLog(): void
    {
        $constructor = (new \ReflectionClass(QueueRecorder::class))->getConstructor();
        $this->assertNotNull($constructor);

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            $names = $type instanceof \ReflectionNamedType ? [$type->getName()]
                : ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType
                    ? array_map(static fn (\ReflectionType $part): string => $part instanceof \ReflectionNamedType ? $part->getName() : (string)$part, $type->getTypes())
                    : []);
            foreach ($names as $name) {
                $this->assertFalse(
                    $name === Journal::class || $name === LoggerInterface::class || (class_exists($name) && is_a($name, LoggerInterface::class, true)),
                    "\${$parameter->getName()} would let the recorder log through stderr"
                );
            }
        }
    }

    /**
     * A pass's row is moved by its id and its channel: an audit row that took the same id (after a restore, say) is left as it is.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testAPassNeverMovesARowOfAnotherChannel(array $params): void
    {
        $this->on($params);
        $columns = implode(', ', array_map($this->db->quoteIdentifier(...), ['at', 'last_at', 'repeat', 'channel', 'level', 'actor', 'action', 'target', 'context']));
        $this->db->query(
            "INSERT INTO {$this->journal->table()} ({$columns}) VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?)",
            ['2026-10-01 08:00:00', '2026-10-01 08:00:00', 'audit', 'info', 'admin', 'page.saved', 'HomePage', '{"note":"kept"}']
        );
        $audit = $this->db->lastInsertId();
        $before = $this->journalRow($audit);

        $this->clock->sleep(60);
        $this->recorder->passHeartbeat($audit, 3, 1000);
        $this->recorder->passFinished($audit, new QueuePassReport(3, 0, 60000, QueuePassReport::EMPTY), 1000);

        $after = $this->journalRow($audit);
        $this->assertSame($before['last_at'], $after['last_at']);
        $this->assertSame('HomePage', $after['target']);
        $this->assertSame($before['context'], $after['context']);
        $this->assertSame(['note' => 'kept'], json_decode((string)$after['context'], true));
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testAPassIsOneJournalRowMovedByItsHeartbeats(array $params): void
    {
        $this->on($params);
        $id = $this->recorder->passStarted(QueueConsumerMode::Worker, new QueueBudget(microtime(true) + 600, 1 << 30, 30), false);

        $row = $this->journalRow($id);
        $this->assertSame('queue', $row['channel']);
        $this->assertSame('queue.pass', $row['action']);
        $this->assertSame('', $row['target'], 'an open pass has no stop reason');
        $this->assertSame('worker', $row['actor']);
        $this->assertSame('info', $row['level']);
        $this->assertSame(self::START, $row['at']);
        $this->assertSame($row['at'], $row['last_at']);
        $started = $this->context($row);
        $this->assertSame(getmypid(), $started['pid']);
        $this->assertEqualsWithDelta(600000, $started['budget_ms'], 1000);
        $this->assertEquals($this->statistics->recentPasses(1)[0]['startedAt'], $this->statistics->recentPasses(1)[0]['finishedAt'], 'no sign of life yet: last seen when it started');

        $this->clock->sleep(60);
        $this->recorder->passHeartbeat($id, 3, 1000);

        $row = $this->journalRow($id);
        $this->assertSame(self::START, $row['at'], 'a heartbeat does not move the start');
        $this->assertSame('2026-10-23 12:01:00', $row['last_at']);
        $this->assertSame('', $row['target'], 'a heartbeat does not end the pass');
        $beaten = $this->context($row);
        $this->assertSame(3, $beaten['handled']);
        $this->assertArrayNotHasKey('finished_at', $beaten, 'the heartbeat is `last_at`, not written twice');
        $this->assertSame($started['pid'], $beaten['pid']);
        $this->assertSame($started['budget_ms'], $beaten['budget_ms']);

        $this->clock->sleep(60);
        $this->recorder->passFinished($id, new QueuePassReport(5, 0, 120000, QueuePassReport::STOPPED), 2000);

        $row = $this->journalRow($id);
        $this->assertSame(QueuePassReport::STOPPED, $row['target']);
        $this->assertSame('2026-10-23 12:02:00', $row['last_at']);

        $pass = $this->statistics->recentPasses(1)[0];
        $this->assertSame($id, $pass['id']);
        $this->assertSame('worker', $pass['mode']);
        $this->assertEquals(new \DateTimeImmutable(self::START, new \DateTimeZone('UTC')), $pass['startedAt']);
        $this->assertEquals(new \DateTimeImmutable('2026-10-23 12:02:00', new \DateTimeZone('UTC')), $pass['finishedAt']);
        $this->assertSame(5, $pass['handled']);
        $this->assertSame(QueuePassReport::STOPPED, $pass['stopReason']);
        $this->assertSame($started['budget_ms'], $pass['budgetMs']);
        $this->assertSame(2000, $pass['peakMemory']);
        $this->assertSame(1 << 30, $pass['memoryLimit']);
        $this->assertSame(getmypid(), $pass['pid']);
        $this->assertTrue($pass['blocking'], 'a worker\'s pass is its consumer\'s whole life');
        $this->assertFalse($pass['beforeResponse']);
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testBudgetedPassesAreNotConsumerStarts(array $params): void
    {
        $this->on($params);
        $this->recorder->passStarted(QueueConsumerMode::Request, null, true);
        $this->recorder->passStarted(QueueConsumerMode::Request, null, false);
        $this->recorder->passStarted(QueueConsumerMode::Worker, null, false);

        $this->assertSame(1, $this->statistics->consumerStarts(3600));

        $restarted = $this->recorder->passStarted(QueueConsumerMode::Worker, null, false);
        $this->recorder->passFinished($restarted, new QueuePassReport(0, 0, 10, QueuePassReport::CONFIG_CHANGED), 0);

        $this->assertSame(1, $this->statistics->consumerStarts(3600), 'a restart asked for by a configuration change is not a crash');
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testStatisticsReadTheJournal(array $params): void
    {
        $this->on($params);
        foreach ([1 => 100, 2 => 200, 3 => 300] as $id => $ms) {
            $this->ran($id, 'T', QueueRecorder::HANDLED, $ms);
        }
        $this->ran(4, 'T', QueueRecorder::FAILED, 50, 'boom');
        $this->ran(5, 'T', QueueRecorder::RETRIED, 50, 'again');

        $this->assertSame(['handled' => 3, 'failed' => 1, 'killed' => 0, 'avgMs' => 200], $this->statistics->outcomes());
        $this->assertSame(200, $this->statistics->averageDurationMs('T'));

        $failed = $this->statistics->history(10, 'failed');
        $this->assertCount(1, $failed);
        $this->assertSame(['id', 'messageId', 'type', 'taskId', 'status', 'mode', 'finishedAt', 'durationMs', 'error'], array_keys($failed[0]));
        $this->assertSame(4, $failed[0]['messageId']);
        $this->assertSame('T', $failed[0]['type']);
        $this->assertNull($failed[0]['taskId']);
        $this->assertSame('failed', $failed[0]['status']);
        $this->assertSame('request', $failed[0]['mode']);
        $this->assertSame(50, $failed[0]['durationMs']);
        $this->assertSame('boom', $failed[0]['error']);
        $this->assertSame('failed', $this->statistics->messageState(4), 'gone from the queue, its last run tells');
        $this->assertSame('pending', $this->statistics->messageState(5));

        $this->recorder->passStarted(QueueConsumerMode::Request, null, true);
        $waited = $this->recorder->passStarted(QueueConsumerMode::Request, null, true);
        $after = $this->recorder->passStarted(QueueConsumerMode::Request, null, false);
        $this->clock->sleep(2);
        $this->recorder->passFinished($waited, new QueuePassReport(1, 0, 2000, QueuePassReport::EMPTY), 0);
        $this->recorder->passFinished($after, new QueuePassReport(1, 0, 2000, QueuePassReport::EMPTY), 0);

        $this->assertSame([2.0], $this->statistics->visitorWaits(3600), 'only the closed passes a visitor waited for');
        $this->assertEquals(new \DateTimeImmutable('2026-10-23 12:00:02', new \DateTimeZone('UTC')), $this->statistics->lastActivityAt());
    }

    public function testARunsActionCarriesItsStatus(): void
    {
        $this->assertSame('queue.run.killed', QueueJournalEntry::run(QueueRecorder::KILLED));
        $this->assertSame(QueueRecorder::KILLED, QueueJournalEntry::statusOf('queue.run.killed'));
        $this->assertNull(QueueJournalEntry::statusOf(QueueJournalEntry::PASS), 'a pass is not a run');
    }

    public function testAPassIsOpenUntilItFinishes(): void
    {
        $id = $this->recorder->passStarted(QueueConsumerMode::Request, new QueueBudget(microtime(true) + 3, 1000, 30), true);

        $open = $this->statistics->lastPass();
        $this->assertNotNull($open);
        $this->assertSame($id, $open['id']);
        $this->assertSame('request', $open['mode']);
        $this->assertSame('', $open['stopReason'], 'a pass still running has no stop reason yet');
        $this->assertEquals($open['startedAt'], $open['finishedAt'], 'last seen when it started');
        $this->assertTrue($open['beforeResponse']);
        $this->assertEqualsWithDelta(3000, (int)$open['budgetMs'], 100);
        $this->assertSame(1000, $open['memoryLimit']);
        $this->assertSame(getmypid(), $open['pid']);
        $this->assertFalse($open['blocking'], 'a budgeted pass is not a long-lived consumer');

        $this->clock->sleep(2);
        $this->recorder->passFinished($id, new QueuePassReport(4, 1, 2000, QueuePassReport::BUDGET), 12345);

        $closed = $this->statistics->lastPass();
        $this->assertNotNull($closed);
        $this->assertSame(4, $closed['handled']);
        $this->assertSame('budget', $closed['stopReason']);
        $this->assertSame(12345, $closed['peakMemory']);
        $this->assertEquals(new \DateTimeImmutable('2026-10-23 12:00:02', new \DateTimeZone('UTC')), $closed['finishedAt']);
    }

    public function testABlockingConsumerBeatsAndCountsAsAStart(): void
    {
        $id = $this->recorder->passStarted(QueueConsumerMode::Binary, QueueBudget::unlimited(21600), false);
        $this->clock->sleep(60);
        $this->recorder->passHeartbeat($id, 7, 999);

        $pass = $this->statistics->lastPass();
        $this->assertNotNull($pass);
        $this->assertNull($pass['budgetMs'], 'no deadline, no budget');
        $this->assertSame(0, $pass['memoryLimit'], 'no memory bound is stored as 0');
        $this->assertSame('', $pass['stopReason'], 'a heartbeat does not end the pass');
        $this->assertSame(7, $pass['handled']);
        $this->assertEquals(new \DateTimeImmutable('2026-10-23 12:01:00', new \DateTimeZone('UTC')), $pass['finishedAt'], 'finishedAt of an open pass is its last sign of life');
        $this->assertTrue($pass['blocking']);

        $this->assertSame(1, $this->statistics->consumerStarts(600));
        $this->clock->sleep(600);
        $this->assertSame(0, $this->statistics->consumerStarts(600));
    }

    public function testRecentPassesAreNewestFirst(): void
    {
        $first = $this->recorder->passStarted(QueueConsumerMode::Command, null, false);
        $second = $this->recorder->passStarted(QueueConsumerMode::Api, null, false);

        $this->assertSame([$second, $first], array_column($this->statistics->recentPasses(5), 'id'));
        $this->assertCount(1, $this->statistics->recentPasses(1));
    }

    public function testAFinishedMessageIsInTheHistory(): void
    {
        $startedAt = (float)$this->clock->now()->format('U.u');
        $this->clock->sleep(1.5);

        $this->recorder->messageFinished(12, QueueTestMessage::class, 'search-drain', QueueRecorder::FAILED, QueueConsumerMode::Command, $startedAt, str_repeat('x', 2000));

        [$row] = $this->statistics->history(10);
        $this->assertSame(12, $row['messageId']);
        $this->assertSame(QueueTestMessage::class, $row['type']);
        $this->assertSame('search-drain', $row['taskId']);
        $this->assertSame('failed', $row['status']);
        $this->assertSame('command', $row['mode']);
        $this->assertSame(1500, $row['durationMs']);
        $this->assertEquals(new \DateTimeImmutable('2026-10-23 12:00:01', new \DateTimeZone('UTC')), $row['finishedAt'], 'dated by its end, to the second');
        $this->assertSame(1000, mb_strlen((string)$row['error']), 'the error is cut, not the row refused');
    }

    public function testHistoryFiltersByStatusNewestFirst(): void
    {
        $now = (float)$this->clock->now()->format('U.u');
        $this->recorder->messageFinished(1, QueueTestMessage::class, null, QueueRecorder::HANDLED, QueueConsumerMode::Request, $now, null);
        $this->recorder->messageFinished(2, QueueTestMessage::class, null, QueueRecorder::RETRIED, QueueConsumerMode::Request, $now, 'boom');
        $this->recorder->messageFinished(3, QueueTestMessage::class, null, QueueRecorder::HANDLED, QueueConsumerMode::Request, $now, null);

        $this->assertSame([3, 2, 1], array_column($this->statistics->history(10), 'messageId'));
        $this->assertSame([3, 1], array_column($this->statistics->history(10, 'handled'), 'messageId'));
        $this->assertSame([3], array_column($this->statistics->history(1, 'handled'), 'messageId'));
    }

    public function testAveragesOnlyCountSuccesses(): void
    {
        $this->assertNull($this->statistics->averageDurationMs(QueueTestMessage::class), 'never seen: no guess');

        $this->finish(1, QueueRecorder::HANDLED, 0.2);
        $this->finish(2, QueueRecorder::HANDLED, 0.4);
        $this->finish(3, QueueRecorder::FAILED, 9.0);

        $this->assertSame(300, $this->statistics->averageDurationMs(QueueTestMessage::class));
    }

    public function testOutcomesOfOneTypeOverAWindow(): void
    {
        $this->finish(1, QueueRecorder::HANDLED, 0.1);
        $this->finish(2, QueueRecorder::FAILED, 0.1);
        $this->finish(3, QueueRecorder::FAILED, 0.1);
        $this->finish(4, QueueRecorder::KILLED, 0.1);
        $this->finish(5, QueueRecorder::RETRIED, 0.1);
        $this->recorder->messageIgnored(2, QueueTestMessage::class, 'boom');
        QueueTestKit::recordRun($this->db, 'Other\\Type', QueueRecorder::HANDLED, 1000, '2026-10-23 11:00:01', 9);
        QueueTestKit::recordRun($this->db, QueueTestMessage::class, QueueRecorder::HANDLED, 1000, '2026-10-21 11:00:01', 10);

        $this->assertSame(
            ['handled' => 1, 'failed' => 2, 'killed' => 1],
            $this->statistics->outcomesOfType(QueueTestMessage::class, 86400),
            'a retry is not an outcome, nor an acknowledgement, nor another type, nor a row older than the window'
        );
        $this->assertSame(['handled' => 0, 'failed' => 0, 'killed' => 0], $this->statistics->outcomesOfType('Never\\Seen', 86400));
    }

    public function testByTypeJoinsTheQueueAndTheHistory(): void
    {
        $this->transport->send(new Envelope(new QueueTestMessage('waiting')));
        $this->transport->withQueue('failed')->send(new Envelope(new QueueTestMessage('broken')));
        $this->finish(1, QueueRecorder::HANDLED, 0.1);
        $this->finish(2, QueueRecorder::HANDLED, 0.3);
        QueueTestKit::recordRun($this->db, 'Other\\Gone', QueueRecorder::HANDLED, 1000, '2026-10-01 00:00:01', 9);

        $types = $this->statistics->byType(7);

        $this->assertSame([QueueTestMessage::class], array_column($types, 'type'), 'a type only seen before the window is not listed');
        $this->assertSame(1, $types[0]['pending']);
        $this->assertSame(1, $types[0]['failed']);
        $this->assertSame(200, $types[0]['avgMs']);
        $this->assertSame('2026-10-23 12:00:00', $types[0]['lastSuccessAt']?->format('Y-m-d H:i:s'), 'stored to the second');
    }

    public function testFailedListsTheFailureQueue(): void
    {
        $sent = $this->transport->send(new Envelope(new QueueTestMessage('broken')));
        $id = (int)$sent->last(TransportMessageIdStamp::class)?->getId();
        $this->transport->moveToFailed($id, 'RuntimeException: boom');

        $failed = $this->statistics->failed();

        $this->assertCount(1, $failed);
        $this->assertSame($id, $failed[0]['id']);
        $this->assertSame(QueueTestMessage::class, $failed[0]['type']);
        $this->assertSame('RuntimeException: boom', $failed[0]['error']);
        $this->assertNull($failed[0]['killedIn'], 'it failed by itself');
        $this->assertSame(0, $this->statistics->pendingCount());
    }

    public function testPendingCountAndOldest(): void
    {
        $this->assertNull($this->statistics->oldestPendingAt());
        $this->transport->send(new Envelope(new QueueTestMessage('one')));
        $this->clock->sleep(30);
        $this->transport->send(new Envelope(new QueueTestMessage('two'), [new DelayStamp(60000)]));

        $this->assertSame(2, $this->statistics->pendingCount(), 'delayed messages are waiting too');
        $this->assertEquals(new \DateTimeImmutable(self::START, new \DateTimeZone('UTC')), $this->statistics->oldestPendingAt());
    }

    public function testMessageStateFollowsTheMessage(): void
    {
        $id = (int)$this->transport->send(new Envelope(new QueueTestMessage('one')))->last(TransportMessageIdStamp::class)?->getId();
        $this->assertSame('pending', $this->statistics->messageState($id));

        [$taken] = [...$this->transport->get()];
        $this->assertSame('running', $this->statistics->messageState($id));

        $this->transport->ack($taken);
        $this->recorder->messageFinished($id, QueueTestMessage::class, null, QueueRecorder::HANDLED, QueueConsumerMode::Request, 0.0, null);
        $this->assertSame('handled', $this->statistics->messageState($id));

        $this->recorder->messageFinished(50, QueueTestMessage::class, null, QueueRecorder::RETRIED, QueueConsumerMode::Request, 0.0, 'boom');
        $this->assertSame('pending', $this->statistics->messageState(50), 'a retry waits under a new id: the work is still to do');
        $this->recorder->messageFinished(51, QueueTestMessage::class, null, QueueRecorder::FAILED, QueueConsumerMode::Request, 0.0, 'boom');
        $this->assertSame('failed', $this->statistics->messageState(51));

        $killed = (int)$this->transport->send(new Envelope(new QueueTestMessage('two')))->last(TransportMessageIdStamp::class)?->getId();
        QueueTestKit::failAsKilled($this->transport, $killed, QueueConsumerMode::Request);
        $this->assertSame('killed', $this->statistics->messageState($killed));
        $failed = (int)$this->transport->send(new Envelope(new QueueTestMessage('three')))->last(TransportMessageIdStamp::class)?->getId();
        $this->transport->moveToFailed($failed, 'undecodable: gone');
        $this->assertSame('failed', $this->statistics->messageState($failed));

        $this->assertSame('unknown', $this->statistics->messageState(999));
    }

    public function testTasksLeaveTheCheckpointOut(): void
    {
        $rows = new QueueTaskRows($this->db, $this->schema, $this->clock);
        $rows->update(QueueCheckpoint::ROW, static fn (array $fields): array => ['next_due_at' => '2026-10-23 12:30:00']);
        $rows->update('queue-prune', static fn (array $fields): array => [
            'label' => 'Prune',
            'every_seconds' => 86400,
            'next_due_at' => '2026-10-24 12:00:00',
            'consecutive_failures' => 2,
        ]);

        $tasks = $this->statistics->tasks();

        $this->assertSame(['queue-prune'], array_column($tasks, 'taskId'));
        $this->assertSame(86400, $tasks[0]['everySeconds']);
        $this->assertSame(2, $tasks[0]['consecutiveFailures']);
        $this->assertEquals(new \DateTimeImmutable('2026-10-24 12:00:00', new \DateTimeZone('UTC')), $tasks[0]['nextDueAt']);
    }

    /**
     * A task's row keeps its failures counted run after run, and its next due time when a run asked for by hand does not know it; forgetting every task keeps the checkpoint.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testTaskStateKeepsItsCountsAndDueTime(array $params): void
    {
        $this->on($params);
        $rows = new QueueTaskRows($this->db, $this->schema, $this->clock);
        $rows->update(QueueCheckpoint::ROW, static fn (array $fields): array => ['next_due_at' => '2026-10-23 12:30:00'] + $fields);
        $task = new QueueTestTask('digest');

        $this->recorder->taskDispatched($task, 1800, new \DateTimeImmutable('2026-10-23 14:30:00', new \DateTimeZone('Europe/Paris')));
        $this->recorder->taskFinished('digest', QueueRecorder::FAILED, 'boom');
        $this->assertSame([1, 'failed', 'boom'], $this->taskSummary('digest'));
        $this->clock->sleep(60);
        $this->recorder->taskDispatched($task, 1800, null);
        $this->recorder->taskFinished('digest', QueueRecorder::FAILED, 'boom again');
        $this->assertSame([2, 'failed', 'boom again'], $this->taskSummary('digest'));
        $this->recorder->taskFinished('digest', QueueRecorder::HANDLED, null);
        $this->assertSame([0, 'handled', null], $this->taskSummary('digest'));

        [$state] = $this->statistics->tasks();
        $this->assertSame('Test task digest', $state['label']);
        $this->assertSame(1800, $state['everySeconds']);
        $this->assertSame('2026-10-23 12:01:00', $state['lastDispatchedAt']?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-23 12:30:00', $state['nextDueAt']?->format('Y-m-d H:i:s'), 'kept, in UTC, when the dispatch did not know it');
        $this->assertSame('2026-10-23 12:01:00', $state['lastFinishedAt']?->format('Y-m-d H:i:s'));
        $this->assertSame(['digest'], $this->recorder->taskIds());

        $this->recorder->forgetTasksExcept([]);

        $this->assertSame([], $this->recorder->taskIds());
        $this->assertSame([], $this->statistics->tasks());
        $this->assertSame('2026-10-23 12:30:00', $this->statistics->scheduleDueAt()?->format('Y-m-d H:i:s'), 'the checkpoint is no task to forget');
    }

    /**
     * The recorder's and the statistics' SQL on the engines the wiki may run on.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testOnEachServer(array $params): void
    {
        $this->on($params);

        $this->transport->send(new Envelope(new QueueTestMessage('waiting')));
        $pass = $this->recorder->passStarted(QueueConsumerMode::Binary, new QueueBudget(INF, 1 << 33, 21600), false);
        $startedAt = (float)$this->clock->now()->format('U.u');
        $this->clock->sleep(0.25);
        $this->recorder->messageFinished(99, QueueTestMessage::class, null, QueueRecorder::HANDLED, QueueConsumerMode::Binary, $startedAt, null);
        $this->recorder->passHeartbeat($pass, 1, 1 << 32);
        $this->recorder->passFinished($pass, new QueuePassReport(1, 1, 250, QueuePassReport::TIME_LIMIT), 1 << 32);

        $this->assertSame(250, $this->statistics->averageDurationMs(QueueTestMessage::class));
        $this->assertSame([QueueTestMessage::class], array_column($this->statistics->byType(), 'type'));
        $this->assertSame(1, $this->statistics->byType()[0]['pending']);
        $this->assertSame(250, $this->statistics->byType()[0]['avgMs']);
        $this->assertSame(['handled' => 1, 'failed' => 0, 'killed' => 0], $this->statistics->outcomesOfType(QueueTestMessage::class, 3600));
        $this->assertSame('time-limit', $this->statistics->lastPass()['stopReason'] ?? null);
        $this->assertSame(1 << 32, $this->statistics->lastPass()['peakMemory'] ?? null, 'bytes past 4 GB fit');
        $this->assertSame(1 << 33, $this->statistics->lastPass()['memoryLimit'] ?? null);
        $this->assertSame(1, $this->statistics->consumerStarts(600));
        $this->assertSame('handled', $this->statistics->messageState(99));
        $this->assertSame([99], array_column($this->statistics->history(10), 'messageId'));
        $this->assertSame([], $this->statistics->failed());
        $this->assertSame([], $this->statistics->tasks());
    }

    /**
     * SQLite in a file of its own, and the development servers when they are there.
     *
     * @return array<string, array{array<string, string|bool>}>
     */
    public static function servers(): array
    {
        return ['sqlite' => [['db_driver' => 'sqlite']]] + QueueTransportTest::realServers();
    }

    /**
     * Moves the test onto that server, in tables of its own that tearDown() removes; SQLite stays on the file setUp() made.
     *
     * @param array<string, string|bool> $params
     */
    private function on(array $params): void
    {
        if ($params['db_driver'] === 'sqlite') {
            return;
        }
        if (!QueueTransportTest::reachable($params)) {
            $this->markTestSkipped("no {$params['db_driver']} server at {$params['db_host']}");
        }
        $this->use(new DbService(new ParameterBag($params)), fresh: true);
        $this->onServer = true;
    }

    /** The queue's table and the Journal on $db, and the services over them. */
    private function use(DbService $db, bool $fresh = false): void
    {
        $this->db = $db;
        $this->schema = new QueueSchema($db);
        $this->journal = new JournalSchema($db);
        if ($fresh) {
            $this->schema->drop();
            $this->journal->drop();
        }
        $this->schema->create();
        $this->journal->create();
        $this->transport = new QueueTransport($db, $this->schema, clock: $this->clock);
        $this->recorder = new QueueRecorder($db, $this->schema, $this->journal, $this->clock);
        $this->statistics = new QueueStatistics($db, $this->schema, $this->journal, $this->transport, $this->clock);
    }

    /** @return array<string, mixed> */
    private function journalRow(int $id): array
    {
        $row = $this->db->loadSingle("SELECT * FROM {$this->journal->table()} WHERE id = ?", [$id]);
        $this->assertNotNull($row);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function context(array $row): array
    {
        $context = json_decode((string)$row['context'], true);
        $this->assertIsArray($context);
        $this->assertNotContains(null, $context, 'a null would read back as the string "null" on MySQL');

        return $context;
    }

    /** A history row for a QueueTestMessage that took $seconds, finished now. */
    private function finish(int $id, string $status, float $seconds): void
    {
        $startedAt = (float)$this->clock->now()->format('U.u');
        $this->clock->sleep($seconds);
        $this->recorder->messageFinished($id, QueueTestMessage::class, null, $status, QueueConsumerMode::Request, $startedAt, null);
    }

    /** A run of that type that took $ms in request mode, finished now. */
    private function ran(int $id, string $type, string $status, int $ms, ?string $error = null): void
    {
        $startedAt = (float)$this->clock->now()->format('U.u');
        $this->clock->sleep($ms / 1000);
        $this->recorder->messageFinished($id, $type, null, $status, QueueConsumerMode::Request, $startedAt, $error);
    }

    /** @return array{int, ?string, ?string} the task's consecutive failures, last status and last error, as the statistics read them */
    private function taskSummary(string $taskId): array
    {
        foreach ($this->statistics->tasks() as $task) {
            if ($task['taskId'] === $taskId) {
                return [$task['consecutiveFailures'], $task['lastStatus'], $task['lastError']];
            }
        }
        $this->fail("no row for {$taskId}");
    }
}
