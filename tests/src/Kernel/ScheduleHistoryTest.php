<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Kernel\Queue\TaskHistory;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueTaskRows;
use YesWiki\Kernel\Service\Queue\ScheduleHistory;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestTask.php';
require_once 'tests/src/Kernel/QueueTransportTest.php';

/** What the Journal's task rows say of each scheduled task: when it appeared, was last queued, and how its last run ended. */
#[CoversClass(ScheduleHistory::class)]
#[CoversClass(TaskHistory::class)]
class ScheduleHistoryTest extends YesWikiTestCase
{
    private const START = '2026-10-23 12:00:00';

    private string $file;
    private MockClock $clock;
    private DbService $db;
    private QueueSchema $schema;
    private JournalSchema $journal;
    private QueueRecorder $recorder;
    private ScheduleHistory $history;
    /** Whether the tables are on a database server, which the test must clean up, rather than in its own file. */
    private bool $onServer = false;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-history-') . '.db';
        $this->clock = new MockClock(self::START, 'UTC');
        $this->use(new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywh_',
            'debug' => false,
        ])));
    }

    protected function tearDown(): void
    {
        if ($this->onServer) {
            $this->schema->drop();
            $this->journal->drop();
        }
        unset($this->history, $this->recorder, $this->journal, $this->schema, $this->db);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testATaskNeverSeenHasNoHistory(array $params): void
    {
        $this->on($params);

        $this->assertNull($this->history->of('x'));
        $this->assertSame([], $this->history->all());
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testTheAnchorIsTheLastDispatchElseTheDeclaration(array $params): void
    {
        $this->on($params);
        $task = new QueueTestTask('x');

        $this->recorder->taskDeclared($task);
        $declared = $this->history->of('x');
        $this->assertNotNull($declared);
        $this->assertSame('Test task x', $declared->label);
        $this->assertNull($declared->lastDispatchedAt);
        $this->assertSame('2026-10-23 12:00:00', $declared->anchor()?->format('Y-m-d H:i:s'));

        $this->clock->sleep(1800);
        $this->recorder->taskDispatched($task);
        $this->clock->sleep(1800);
        $this->recorder->taskDispatched($task);

        $dispatched = $this->history->of('x');
        $this->assertNotNull($dispatched);
        $this->assertSame('2026-10-23 13:00:00', $dispatched->lastDispatchedAt?->format('Y-m-d H:i:s'));
        $this->assertEquals($dispatched->lastDispatchedAt, $dispatched->anchor());
        $this->assertSame('2026-10-23 12:00:00', $dispatched->declaredAt?->format('Y-m-d H:i:s'), 'its appearance is not forgotten');
        $this->assertSame('UTC', $dispatched->anchor()?->getTimezone()->getName());
        $this->assertSame('Test task x', $dispatched->label);
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testConsecutiveFailuresCountFromTheLastHandledRun(array $params): void
    {
        $this->on($params);

        foreach ([QueueRecorder::FAILED, QueueRecorder::FAILED, QueueRecorder::RETRIED, QueueRecorder::KILLED] as $status) {
            $this->recorder->taskFinished('x', $status, 'boom');
        }
        $failing = $this->history->of('x');
        $this->assertNotNull($failing);
        $this->assertSame(3, $failing->consecutiveFailures, 'a retry changes nothing, a failure or a kill adds one');

        $this->recorder->taskFinished('x', QueueRecorder::HANDLED, null);

        $handled = $this->history->of('x');
        $this->assertNotNull($handled);
        $this->assertSame(0, $handled->consecutiveFailures);
        $this->assertSame(QueueRecorder::HANDLED, $handled->lastStatus);
        $this->assertNull($handled->lastError);
        $this->assertNull($handled->declaredAt);
        $this->assertNull($handled->label, 'a task whose rows say nothing of its label has none');
        $this->assertNull($handled->anchor());
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testTheLastRunSaysHowItEnded(array $params): void
    {
        $this->on($params);
        $this->clock->sleep(90);

        $this->recorder->taskFinished('x', QueueRecorder::FAILED, 'boom');

        $run = $this->history->of('x');
        $this->assertNotNull($run);
        $this->assertSame('x', $run->taskId);
        $this->assertSame(QueueRecorder::FAILED, $run->lastStatus);
        $this->assertSame('boom', $run->lastError);
        $this->assertSame(1, $run->consecutiveFailures);
        $this->assertEquals($this->clock->now(), $run->lastFinishedAt);
        $this->assertNull($run->lastDispatchedAt);
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testAllAnswersEachTaskOnceByItsId(array $params): void
    {
        $this->on($params);
        $this->recorder->taskDeclared(new QueueTestTask('b'));
        $this->recorder->taskDeclared(new QueueTestTask('a'));
        $this->recorder->taskDispatched(new QueueTestTask('a'));
        $this->recorder->taskFinished('a', QueueRecorder::HANDLED, null);
        $this->recorder->taskFinished('a', QueueRecorder::FAILED, 'late');
        $this->journalRowOfAnotherChannel();

        $all = $this->history->all();

        $this->assertEqualsCanonicalizing(['a', 'b'], array_keys($all), 'a row of another channel is not a task');
        $this->assertSame('late', $all['a']->lastError, 'of its rows, the last of each kind');
        $this->assertSame(1, $all['a']->consecutiveFailures);
        $this->assertNotNull($all['a']->lastDispatchedAt);
        $this->assertNull($all['b']->lastDispatchedAt);
        $this->assertNull($all['b']->lastStatus);
        $this->assertSame(0, $all['b']->consecutiveFailures);
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

    /** @param array<string, string|bool> $params */
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

    /** The tables on $db, and the services over them. */
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
        $this->recorder = new QueueRecorder($db, new QueueTaskRows($db, $this->schema, $this->clock), $this->journal, $this->clock);
        $this->history = new ScheduleHistory($db, $this->journal);
    }

    /** An audit row named like a task, which the history must not take for one. */
    private function journalRowOfAnotherChannel(): void
    {
        $columns = implode(', ', array_map($this->db->quoteIdentifier(...), ['at', 'last_at', 'repeat', 'channel', 'level', 'actor', 'action', 'target', 'context']));
        $this->db->query(
            "INSERT INTO {$this->journal->table()} ({$columns}) VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?)",
            [self::START, self::START, 'audit', 'info', 'admin', 'queue.task.finished', 'intruder', '{"status":"handled"}']
        );
    }
}
