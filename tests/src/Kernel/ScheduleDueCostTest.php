<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\Queue\ScheduleHistory;
use YesWiki\Kernel\Service\ThrowableFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestTask.php';
require_once 'tests/src/Kernel/QueueTestKit.php';

/** What the end of a request pays to know whether the schedule is due: the Journal read once, however long its queue channel has grown. */
#[CoversClass(QueueScheduleTicker::class)]
#[CoversClass(ScheduleHistory::class)]
class ScheduleDueCostTest extends YesWikiTestCase
{
    private const START = '2026-11-03 12:00:00';

    private const TASKS = 12;

    private const RUNS = 10000;

    private string $file;

    private DbService $db;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-due-cost-') . '.db';
        $this->db = new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywq_',
            // The query log is how the test counts what the check costs.
            'debug' => true,
        ]));
        $this->clock = new MockClock(self::START, 'UTC');
        (new QueueSchema($this->db))->create();
        (new JournalSchema($this->db))->create();
    }

    protected function tearDown(): void
    {
        unset($this->db);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
        @unlink(substr($this->file, 0, -3));
    }

    public function testKnowingWhetherTheScheduleIsDueCostsTwoQueriesAtMost(): void
    {
        $this->seedTheJournal();
        $ticker = $this->freshTicker();
        $before = count($this->db->getQueryLog());

        $start = hrtime(true);
        $dueAt = $ticker->dueAt();
        $seconds = (hrtime(true) - $start) / 1e9;

        $this->assertNotNull($dueAt, 'every task has its history: the next run is a date');
        $this->assertLessThanOrEqual(2, count($this->db->getQueryLog()) - $before, 'one read of the Journal, whatever the number of runs it holds');
        $this->assertLessThan(0.05, $seconds);

        $before = count($this->db->getQueryLog());
        $ticker->dueAt();
        $this->assertCount($before, $this->db->getQueryLog(), 'asked again within the minute, it reads nothing');
    }

    /** A Journal with 10 000 runs and the story of 12 tasks: each seen, sent and finished once. */
    private function seedTheJournal(): void
    {
        $recorder = new QueueRecorder($this->db, new JournalSchema($this->db), $this->clock);
        $table = (new JournalSchema($this->db))->table();
        $columns = implode(', ', array_map($this->db->quoteIdentifier(...), ['at', 'last_at', 'repeat', 'channel', 'level', 'actor', 'action', 'target', 'fingerprint', 'day', 'context']));
        $insert = $this->db->prepare(
            "INSERT INTO {$table} ({$columns}) VALUES (?, ?, 1, 'queue', 'info', '', 'queue.run.handled', ?, NULL, NULL, '{}')"
        );

        $this->db->transactional(function () use ($insert): void {
            for ($run = 0; $run < self::RUNS; $run++) {
                $at = (new \DateTimeImmutable(self::START, new \DateTimeZone('UTC')))->modify('-' . (1 + $run % 6000) . ' minutes')->format('Y-m-d H:i:s');
                $insert->execute([$at, $at, 'YesWiki\\Kernel\\Message\\Run' . $run % 40]);
            }
        });
        foreach (array_keys($this->declared()) as $taskId) {
            $task = new QueueTestTask($taskId);
            $this->clock->sleep(60);
            $recorder->taskDeclared($task);
            $recorder->taskDispatched($task);
            $recorder->taskFinished($taskId, QueueRecorder::HANDLED, null);
        }
    }

    /** @return array<string, string> the twelve tasks the provider declares, by id, each with how often it recurs */
    private function declared(): array
    {
        $tasks = [];
        for ($task = 1; $task <= self::TASKS; $task++) {
            $tasks[sprintf('task-%02d', $task)] = $task % 3 === 0 ? '24 hours' : ($task % 3 === 1 ? '1 hour' : '15 minutes');
        }

        return $tasks;
    }

    /** A ticker as a request gets one: nothing in memory, everything from the database. */
    private function freshTicker(): QueueScheduleTicker
    {
        $declared = $this->declared();
        $provider = new class($declared) implements ProvidesSchedule {
            /** @param array<string, string> $tasks */
            public function __construct(private array $tasks)
            {
            }

            public function recurringMessages(): array
            {
                $recurring = [];
                foreach ($this->tasks as $id => $every) {
                    $recurring[] = RecurringMessage::every($every, new QueueTestTask($id));
                }

                return $recurring;
            }
        };
        $journal = new Journal(new ServiceLocator([
            DbService::class => fn (): DbService => $this->db,
            JournalSchema::class => fn (): JournalSchema => new JournalSchema($this->db),
            ThrowableFormatter::class => static fn (): ThrowableFormatter => new ThrowableFormatter(),
        ]));
        $recorder = new QueueRecorder($this->db, new JournalSchema($this->db), $this->clock);
        $transport = new QueueTransport($this->db, new QueueSchema($this->db), clock: $this->clock);

        return new QueueScheduleTicker(
            [$provider],
            new QueueBus($transport, new QueueHandlerLocator([], new ServiceLocator([])), $journal, $recorder, QueueTestKit::noRequest()),
            new ScheduleHistory($this->db, new JournalSchema($this->db)),
            $recorder,
            QueueTestKit::locksIn(sys_get_temp_dir() . '/yw-due-cost-locks'),
            $this->clock,
            new HibernationService(new ParameterBag(['wiki_status' => 'running'])),
            $journal,
        );
    }
}
