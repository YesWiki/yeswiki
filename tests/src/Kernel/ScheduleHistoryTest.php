<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Kernel\Queue\QueueHistoryEntry;
use YesWiki\Kernel\Queue\TaskHistory;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\ScheduleHistory;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestTask.php';
require_once 'tests/src/Kernel/QueueTransportTest.php';

/** What the history's task rows say of each scheduled task: when it appeared, was last queued, and how its last run ended. */
#[CoversClass(ScheduleHistory::class)]
#[CoversClass(TaskHistory::class)]
class ScheduleHistoryTest extends YesWikiTestCase
{
    private const START = '2026-10-23 12:00:00';

    private string $file;
    private MockClock $clock;
    private DbService $db;
    private QueueSchema $schema;
    private QueueRecorder $recorder;
    private ScheduleHistory $history;
    /** Whether the tables are on a database server, which the test must clean up, rather than in its own file. */
    private bool $onServer = false;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-history-') . '.db';
        $this->clock = new MockClock(new \DateTimeImmutable(self::START));
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
        }
        unset($this->history, $this->recorder, $this->schema, $this->db);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
    }

    /** @param array<string, string|bool> $params */
    #[DataProviderExternal(QueueTransportTest::class, 'servers')]
    public function testATaskNeverSeenHasNoHistory(array $params): void
    {
        $this->on($params);

        $this->assertNull($this->history->of('x'));
        $this->assertSame([], $this->history->all());
    }

    /** @param array<string, string|bool> $params */
    #[DataProviderExternal(QueueTransportTest::class, 'servers')]
    public function testTheAnchorIsTheLastDispatchElseTheDeclaration(array $params): void
    {
        $this->on($params);
        $task = new QueueTestTask('x');

        $this->recorder->taskDeclared($task);
        $declared = $this->history->of('x');
        $this->assertNotNull($declared);
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
        $this->assertSame(date_default_timezone_get(), $dispatched->anchor()?->getTimezone()->getName(), 'read back in the wiki\'s zone');
    }

    /** SQLite only: MySQL and PostgreSQL refuse to store a context that is not JSON, so there it cannot happen. */
    public function testARowWhoseContextIsNotJsonSaysNothingAndBreaksNothing(): void
    {
        $this->historyRow(QueueHistoryEntry::TASK_FINISHED, 'z', 'not json');

        $run = $this->history->of('z');

        $this->assertNotNull($run, 'the task is still known');
        $this->assertNull($run->lastStatus);
        $this->assertNull($run->lastError);
    }

    /**
     * The last run of a task is its last finished row, whatever its status: a retry, a failure, a success.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProviderExternal(QueueTransportTest::class, 'servers')]
    public function testTheLastRunIsTheLastFinishedRow(array $params): void
    {
        $this->on($params);

        $this->recorder->taskFinished('x', QueueRecorder::FAILED, 'boom');
        $this->recorder->taskFinished('x', QueueRecorder::RETRIED, 'again');
        $retried = $this->history->of('x');
        $this->assertNotNull($retried);
        $this->assertSame([QueueRecorder::RETRIED, 'again'], [$retried->lastStatus, $retried->lastError]);

        $this->clock->sleep(90);
        $this->recorder->taskFinished('x', QueueRecorder::HANDLED, null);

        $handled = $this->history->of('x');
        $this->assertNotNull($handled);
        $this->assertSame('x', $handled->taskId);
        $this->assertEquals($this->clock->now(), $handled->lastFinishedAt);
        $this->assertSame(QueueRecorder::HANDLED, $handled->lastStatus);
        $this->assertNull($handled->lastError);
        $this->assertNull($handled->declaredAt);
        $this->assertNull($handled->anchor());
        $this->assertNull($handled->lastDispatchedAt);
    }

    /** @param array<string, string|bool> $params */
    #[DataProviderExternal(QueueTransportTest::class, 'servers')]
    public function testAllAnswersEachTaskOnceByItsId(array $params): void
    {
        $this->on($params);
        $this->recorder->taskDeclared(new QueueTestTask('b'));
        $this->recorder->taskDeclared(new QueueTestTask('a'));
        $this->recorder->taskDispatched(new QueueTestTask('a'));
        $this->recorder->taskFinished('a', QueueRecorder::HANDLED, null);
        $this->recorder->taskFinished('a', QueueRecorder::FAILED, 'late');
        $this->historyRow(QueueHistoryEntry::run(QueueRecorder::HANDLED), 'intruder', '{"status":"handled"}');

        $all = $this->history->all();

        $this->assertEqualsCanonicalizing(['a', 'b'], array_keys($all), 'a run row named like a task is not a task');
        $this->assertSame('late', $all['a']->lastError, 'of its rows, the last of each kind');
        $this->assertSame(QueueRecorder::FAILED, $all['a']->lastStatus);
        $this->assertNotNull($all['a']->lastDispatchedAt);
        $this->assertNull($all['b']->lastDispatchedAt);
        $this->assertNull($all['b']->lastStatus);
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
        if ($fresh) {
            $this->schema->drop();
        }
        $this->schema->create();
        $this->recorder = new QueueRecorder($db, $this->schema, $this->clock);
        $this->history = new ScheduleHistory($db, $this->schema);
    }

    /** A row of that action and target, with that raw context. */
    private function historyRow(string $action, string $target, string $context): void
    {
        $columns = implode(', ', array_map($this->db->quoteIdentifier(...), ['at', 'last_at', 'actor', 'action', 'target', 'context']));
        $this->db->query(
            "INSERT INTO {$this->schema->historyTable()} ({$columns}) VALUES (?, ?, ?, ?, ?, ?)",
            [self::START, self::START, '', $action, $target, $context]
        );
    }
}
