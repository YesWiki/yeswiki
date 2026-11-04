<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\Severity;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Queue\QueueWording;
use YesWiki\Kernel\Queue\RecurringMessageTimezoned;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueBudgetFactory;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueFallback;
use YesWiki\Kernel\Service\Queue\QueueHealthChecks;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueRequestTrigger;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\Queue\ScheduleHistory;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTestMessage.php';
require_once 'tests/src/Kernel/QueueTestTask.php';
require_once 'tests/src/Kernel/QueueTestToleratedMessage.php';

/** What the admin is told when a background job stops, fails or is misconfigured (messenger / scheduler): each check against rows written into the queue's table and the Journal's `queue` channel. */
#[CoversClass(QueueHealthChecks::class)]
class QueueHealthChecksTest extends YesWikiTestCase
{
    private const START = '2026-10-23 12:00:00';

    private string $file;
    private MockClock $clock;
    private DbService $db;
    private QueueSchema $schema;
    private JournalSchema $journal;
    private QueueStatistics $statistics;

    private string $mode = '';
    private string $secret = '';
    private bool $hibernated = false;
    /** A schema that cannot tell whether its tables exist, when a test needs the database to be out of reach. */
    private ?QueueSchema $unreachableSchema = null;

    /** @var array<string, array{string, int}> the tasks the schedule declares: id => label, interval in seconds */
    private array $declared = [];

    /** @var array<string, string|false> */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        foreach ([QueueSettings::ENV_MODE, QueueSettings::ENV_BINARY] as $name) {
            $this->savedEnv[$name] = getenv($name);
            putenv($name);
        }
        $this->file = tempnam(sys_get_temp_dir(), 'yw-queue-') . '.db';
        $this->clock = new MockClock(self::START, 'UTC');
        $this->db = new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywq_',
            'debug' => false,
        ]));
        $this->schema = new QueueSchema($this->db);
        $this->schema->create();
        $this->journal = new JournalSchema($this->db);
        $this->journal->create();
        $this->statistics = new QueueStatistics($this->db, $this->schema, $this->journal, new QueueTransport($this->db, $this->schema, clock: $this->clock), $this->clock);
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $name => $before) {
            putenv($before === false ? $name : "$name=$before");
        }
        $this->savedEnv = [];
        unset($this->statistics, $this->journal, $this->schema, $this->db);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
    }

    // --- The queue is healthy ----------------------------------------------------------------

    public function testAHealthyQueueReportsNothing(): void
    {
        $this->mode = 'worker';
        $this->message('default', '-1 minutes');
        $this->task('purge', everySeconds: 1800, nextDue: '+10 minutes');

        foreach ($this->ids() as $id) {
            $this->assertNull($this->failure($id), "$id should pass on a healthy queue");
        }
    }

    public function testEveryCheckPointsToTheJobsPage(): void
    {
        $this->assertEqualsCanonicalizing([
            'queue-tables', 'queue-fallback', 'queue-stalled', 'queue-failed', 'queue-killed', 'queue-task-overdue', 'queue-task-failing',
            'queue-backlog', 'queue-consumer-restarts', 'queue-consumer-memory', 'queue-config', 'queue-no-budget', 'queue-visitor-cost',
        ], $this->ids(), 'a check dropped, or one added without the alert button it needs on the jobs page (JobsOverview::actionsFor)');
        foreach ($this->checks() as $check) {
            $this->assertSame('admin/jobs', $check->route(), $check->id());
        }
    }

    /** The `command` and `request-spawn` modes are gone: an admin who follows an alert's advice must not be sent to a mode the wiki no longer knows. */
    public function testNoAdviceNamesARemovedMode(): void
    {
        foreach (['en', 'fr'] as $language) {
            $translations = (array)require dirname(__DIR__, 3) . "/src/lang/yeswiki_{$language}.php";
            foreach ($translations as $key => $text) {
                if (str_starts_with((string)$key, 'QUEUE_') || str_starts_with((string)$key, 'ADMIN_JOBS_')) {
                    $this->assertDoesNotMatchRegularExpression('/\brequest-spawn\b|la tâche planifiée ou|the scheduled task or/iu', (string)$text, "{$language}: {$key}");
                }
            }
        }
    }

    public function testSeverities(): void
    {
        $degraded = ['queue-backlog', 'queue-consumer-memory', 'queue-no-budget', 'queue-visitor-cost'];
        foreach ($this->checks() as $check) {
            $this->assertSame(
                in_array($check->id(), $degraded, true) ? Severity::Degraded : Severity::Broken,
                $check->severity(),
                $check->id()
            );
        }
    }

    // --- queue-tables ------------------------------------------------------------------------

    public function testMissingTablesAreBroken(): void
    {
        $this->message('default', '-5 hours');
        $this->schema->drop();

        $failure = $this->failure('queue-tables');

        $this->assertSame(_t('QUEUE_HEALTH_TABLES_MISSING'), $failure);
        // The other checks leave it to this one: a wiki not yet migrated does not get an alert per check.
        foreach (array_diff($this->ids(), ['queue-tables']) as $id) {
            $this->assertNull($this->failure($id), $id);
        }
    }

    // --- queue-fallback ----------------------------------------------------------------------

    public function testFallbackIsBrokenAndSaysTheMode(): void
    {
        $this->mode = 'worker';
        $this->pass('worker', startedAt: '-5 hours', finishedAt: '-3 hours');
        $this->pass('request', startedAt: '-2 hours', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Worker);

        $this->assertSame(
            _t('QUEUE_HEALTH_FALLBACK_FAILED', ['mode' => 'worker', 'since' => QueueWording::duration(7200), 'idle' => QueueWording::duration(3 * 3600), 'budget' => $this->requestBudget()]) . $this->visitorsWait(),
            $this->failure('queue-fallback'),
            'the mode that is gone, since when the requests consume, and for how long the consumer was not seen'
        );
    }

    public function testAFallbackForAConsumerNeverSeenSaysSo(): void
    {
        $this->mode = 'api';
        $this->pass('request', startedAt: '-2 hours', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Api);

        $this->assertSame(
            _t('QUEUE_HEALTH_FALLBACK_NEVER', ['mode' => 'api', 'since' => QueueWording::duration(7200), 'budget' => $this->requestBudget()]) . $this->visitorsWait(),
            $this->failure('queue-fallback')
        );
    }

    public function testNoFallbackNoAlert(): void
    {
        $this->mode = 'worker';
        $this->pass('worker', startedAt: '-1 hour', finishedAt: '-1 minute', stopReason: '');

        $this->assertNull($this->failure('queue-fallback'));
    }

    /** One cause, one red alert: the fallback says the worker is gone, and the queue is moving. */
    public function testStalledIsSilentWhileTheFallbackConsumes(): void
    {
        $this->mode = 'worker';
        $this->message('default', '-20 minutes');
        $this->pass('worker', startedAt: '-5 hours', finishedAt: '-3 hours');
        $this->pass('request', startedAt: '-5 minutes', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Worker);

        $this->assertNull($this->failure('queue-stalled'));
    }

    /** With no visit, the fallback consumes nothing either: then the queue is stalled, as `request`'s delay tells. */
    public function testStalledSpeaksAgainWhenTheFallbackItselfStops(): void
    {
        $this->mode = 'worker';
        $this->message('default', '-25 hours');
        $this->pass('worker', startedAt: '-30 hours', finishedAt: '-26 hours');
        $this->pass('request', startedAt: '-25 hours', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Worker);

        $this->assertNotNull($this->failure('queue-stalled'));
    }

    /** A cron that finds the lock held by a pass stuck in a handler leaves a row to say it came: that row is no sign the queue moves. */
    public function testPassesThatFoundTheLockTakenDoNotHideAStall(): void
    {
        $this->mode = 'api';
        $this->message('default', '-4 hours');
        $this->pass('api', startedAt: '-5 hours', finishedAt: '-4 hours');
        $this->pass('api', startedAt: '-1 minute', stopReason: 'locked');

        $this->assertNotNull($this->failure('queue-stalled'));
    }

    /** @return array<string, array{string}> */
    public static function passesThatDidNothing(): array
    {
        return ['the lock was taken' => ['locked'], 'the wiki was paused' => ['paused']];
    }

    #[DataProvider('passesThatDidNothing')]
    public function testPassesThatCouldNotStartDoNotHideStarvedPasses(string $stopReason): void
    {
        $this->mode = 'api';
        foreach (['-50', '-40', '-30'] as $minutes) {
            $this->pass('api', startedAt: "$minutes minutes", stopReason: 'no-budget');
        }
        foreach (['-3', '-2', '-1'] as $minutes) {
            $this->pass('api', startedAt: "$minutes minutes", stopReason: $stopReason);
        }

        $this->assertNotNull($this->failure('queue-no-budget'));
    }

    // --- queue-stalled -----------------------------------------------------------------------

    public function testStalledInWorkerModeAfterTenMinutes(): void
    {
        $this->mode = 'worker';
        $this->message('default', '-20 minutes');
        $this->pass('worker', startedAt: '-3 hours', finishedAt: '-11 minutes');

        $this->assertNotNull($this->failure('queue-stalled'));
    }

    public function testNotStalledInWorkerModeBeforeTenMinutes(): void
    {
        $this->mode = 'worker';
        $this->message('default', '-20 minutes');
        $this->pass('worker', startedAt: '-3 hours', finishedAt: '-9 minutes');

        $this->assertNull($this->failure('queue-stalled'));
    }

    public function testStalledInRequestModeAfterADay(): void
    {
        $this->mode = 'request';
        $this->message('default', '-30 hours');
        $this->pass('request', startedAt: '-25 hours');

        $this->assertNotNull($this->failure('queue-stalled'));
    }

    public function testNotStalledInRequestModeAfterAnHour(): void
    {
        $this->mode = 'request';
        $this->message('default', '-30 hours');
        $this->pass('request', startedAt: '-70 minutes');

        $this->assertNull($this->failure('queue-stalled'), 'a pass an hour ago is activity, however old the message');
    }

    #[DataProvider('stalledAfter')]
    public function testEachModeHasItsOwnPatience(string $mode, int $seconds): void
    {
        $this->mode = $mode;
        $this->message('default', '-3 days');

        $this->pass($mode, startedAt: '-' . ($seconds - 60) . ' seconds');
        $this->assertNull($this->failure('queue-stalled'), 'just under');

        $this->db->query("DELETE FROM {$this->journal->table()} WHERE channel = 'queue'");
        $this->pass($mode, startedAt: '-' . ($seconds + 60) . ' seconds');
        $this->assertNotNull($this->failure('queue-stalled'), 'just over');
    }

    /** What a live worker looks like: one row, started hours ago, whose heartbeat is seconds old. */
    public function testALiveWorkerDrainingAnOldMessageIsABacklogNotAStall(): void
    {
        $this->mode = 'worker';
        $this->message('default', '-2 hours');
        $this->pass('worker', startedAt: '-3 hours', finishedAt: '-30 seconds', stopReason: '');

        $this->assertNull($this->failure('queue-stalled'));
        $this->assertNotNull($this->failure('queue-backlog'));
    }

    public function testWithNoPassEverTheOldestMessageStandsInForTheActivity(): void
    {
        $this->mode = 'worker';
        $this->message('default', '-9 minutes');
        $this->assertNull($this->failure('queue-stalled'), 'a message just sent to a fresh wiki is not a stall');

        $this->db->query("DELETE FROM {$this->schema->table()} WHERE queue_name = 'default'");
        $this->message('default', '-11 minutes');
        $this->assertNotNull($this->failure('queue-stalled'), 'a fresh install with messages and no consumer must alert');
    }

    public function testAMessageThatIsBeingHandledIsNotWaiting(): void
    {
        $this->mode = 'worker';
        $this->pass('worker', startedAt: '-3 hours', finishedAt: '-2 hours');
        $this->claimed('-30 minutes', limit: 3600, mode: 'worker');

        $this->assertNull($this->failure('queue-stalled'), 'nothing is available, the consumer has it');
    }

    /** A blocking consumer inside one long handler (a backup) does not beat: the message it holds says it is at work, until its limit has passed. */
    public function testALongJobInProgressIsActivity(): void
    {
        $this->mode = 'worker';
        $this->message('default', '-40 minutes');
        $this->pass('worker', startedAt: '-3 hours', finishedAt: '-50 minutes', stopReason: '');
        $this->claimed('-50 minutes', limit: 3600, mode: 'worker');

        $this->assertNull($this->failure('queue-stalled'), 'the consumer is busy, not gone');

        $this->db->query("DELETE FROM {$this->schema->table()} WHERE delivered_at IS NOT NULL");
        $this->claimed('-2 hours', limit: 3600, mode: 'worker');

        $this->assertNotNull($this->failure('queue-stalled'), 'a claim past its limit is a dead consumer, not a busy one');
    }

    /** @return iterable<string, array{string, int}> */
    public static function stalledAfter(): iterable
    {
        yield 'worker' => ['worker', 600];
        yield 'api' => ['api', 3 * 3600];
        yield 'request' => ['request', 24 * 3600];
    }

    public function testAnEmptyQueueIsNeverStalled(): void
    {
        $this->mode = 'worker';
        $this->pass('worker', startedAt: '-5 days');

        $this->assertNull($this->failure('queue-stalled'));
    }

    public function testADelayedRetryIsNotWaitingYet(): void
    {
        $this->mode = 'worker';
        $this->message('default', '+20 minutes');
        $this->pass('worker', startedAt: '-5 days');

        $this->assertNull($this->failure('queue-stalled'));
    }

    public function testHibernationIsNotAStall(): void
    {
        $this->mode = 'worker';
        $this->hibernated = true;
        $this->message('default', '-3 days');
        $this->pass('worker', startedAt: '-3 days');
        $this->task('purge', everySeconds: 1800, nextDue: '-3 days');

        foreach (['queue-stalled', 'queue-backlog', 'queue-task-overdue'] as $id) {
            $this->assertNull($this->failure($id), "$id must not blame the consumer while the wiki sleeps");
        }

        $this->hibernated = false;
        $this->assertNotNull($this->failure('queue-stalled'), 'and the alert is back when the wiki wakes');
    }

    // --- queue-failed, queue-killed ----------------------------------------------------------

    public function testFailedMessagesTripTheGenericCheck(): void
    {
        $this->message('failed', '-1 hours', QueueTestMessage::class, 'HTTP 500');

        $failure = $this->failure('queue-failed');

        $this->assertNotNull($failure);
        $this->assertStringContainsString('QueueTestMessage', $failure);
        $this->assertNull($this->failure('queue-killed'));
    }

    public function testFailedToleratedMessagesDoNotTripTheGenericCheck(): void
    {
        $this->message('failed', '-1 hours', QueueTestToleratedMessage::class, 'dead inbox');
        $this->message('failed', '-1 hours', 'Gone\\Message\\ThatNoLongerExists', null);
        $this->assertNotNull($this->failure('queue-failed'), 'a class that is gone cannot claim tolerance');

        $this->db->query("DELETE FROM {$this->schema->table()} WHERE message_type = ?", ['Gone\\Message\\ThatNoLongerExists']);

        $this->assertNull($this->failure('queue-failed'));
    }

    public function testKilledMessagesBelongToTheirOwnCheck(): void
    {
        $this->message('failed', '-1 hours', QueueTestMessage::class, killedIn: 'request');

        $this->assertNull($this->failure('queue-failed'), 'a killed message is not a generic failure, it has its own alert');
        $this->assertNotNull($this->failure('queue-killed'));
    }

    public function testKilledSuggestsAnotherMode(): void
    {
        $this->message('failed', '-1 hours', QueueTestMessage::class, killedIn: 'request');

        $failure = (string)$this->failure('queue-killed');

        $this->assertStringContainsString('QueueTestMessage', $failure);
        $this->assertStringContainsString($this->switchAdvice(), $failure);
    }

    /** The part of QUEUE_HEALTH_KILLED_FAILED after the mode: the advice to switch, whatever the language. */
    private function switchAdvice(): string
    {
        $template = _t('QUEUE_HEALTH_KILLED_FAILED');
        $advice = trim(substr($template, strrpos($template, '%{mode}') + strlen('%{mode}')));
        $this->assertNotSame('', $advice);

        return $advice;
    }

    public function testAMessageKilledInABlockingModeIsNotToldToMoveToIt(): void
    {
        $this->message('failed', '-1 hours', QueueTestMessage::class, killedIn: 'worker');

        $failure = (string)$this->failure('queue-killed');

        $this->assertStringContainsString('QueueTestMessage', $failure);
        $this->assertStringNotContainsString($this->switchAdvice(), $failure);
    }

    public function testAMessageKilledUnderAnotherModeIsNotToldToSwitchAgain(): void
    {
        $this->message('failed', '-1 hours', QueueTestMessage::class, killedIn: 'request');

        $this->mode = 'worker';
        $failure = (string)$this->failure('queue-killed');
        $this->assertStringContainsString('QueueTestMessage', $failure);
        $this->assertStringNotContainsString($this->switchAdvice(), $failure, 'the wiki already switched');

        $this->mode = 'api';
        $this->assertStringNotContainsString($this->switchAdvice(), (string)$this->failure('queue-killed'), 'and it is not the mode the job was killed in');

        $this->mode = 'request';
        $this->assertStringContainsString($this->switchAdvice(), (string)$this->failure('queue-killed'), 'still in the mode that kills it: the advice stands');
    }

    public function testAClaimNobodyReapedYetIsKilledToo(): void
    {
        $this->mode = 'request';
        $this->claimed('-30 minutes', limit: 3600, mode: 'request');
        $this->assertNull($this->failure('queue-killed'), 'within its limit');

        $this->claimed('-2 hours', limit: 3600, mode: 'request');
        $failure = $this->failure('queue-killed');

        $this->assertNotNull($failure);
        $this->assertStringContainsString('QueueTestMessage', $failure);
        $this->assertNull($this->failure('queue-failed'));
    }

    // --- scheduled tasks ---------------------------------------------------------------------

    public function testOverdueTask(): void
    {
        $this->mode = 'worker';
        // Every 30 minutes, the mode waits 10: the grace is the longer of the two.
        $this->task('purge', label: 'Purge des révisions', everySeconds: 1800, nextDue: '-29 minutes');
        $this->assertNull($this->failure('queue-task-overdue'), 'within one period of its due time');

        $this->task('purge', label: 'Purge des révisions', everySeconds: 1800, nextDue: '-31 minutes');
        $failure = $this->failure('queue-task-overdue');

        $this->assertNotNull($failure);
        $this->assertStringContainsString('Purge des révisions', $failure);
    }

    public function testATaskIsGivenTheModesPatienceWhenItIsLonger(): void
    {
        $this->mode = 'request';
        $this->task('purge', everySeconds: 1800, nextDue: '-5 hours');

        $this->assertNull($this->failure('queue-task-overdue'), 'a wiki that nobody visits does not run its tasks');

        $this->task('purge', everySeconds: 1800, nextDue: '-25 hours');
        $this->assertNotNull($this->failure('queue-task-overdue'));
    }

    public function testATaskThatNeverDispatchedIsNotOverdue(): void
    {
        $this->mode = 'worker';
        $this->task('fresh', everySeconds: 1800, nextDue: null);

        $this->assertNull($this->failure('queue-task-overdue'));
    }

    public function testFailingTask(): void
    {
        $this->task('sync', label: 'Synchro « Agenda RSS »', everySeconds: 3600, nextDue: '+1 hours', failures: 2, error: 'HTTP 500');
        $this->assertNull($this->failure('queue-task-failing'));

        $this->task('sync', label: 'Synchro « Agenda RSS »', everySeconds: 3600, nextDue: '+1 hours', failures: 3, error: 'HTTP 500');
        $failure = $this->failure('queue-task-failing');

        $this->assertNotNull($failure);
        $this->assertStringContainsString('Synchro « Agenda RSS »', $failure);
        $this->assertStringContainsString('HTTP 500', $failure);
    }

    /** A deleted data source or a disabled extension leaves its row behind, and nobody could clear its alert. */
    public function testATaskThatLeftTheScheduleRaisesNothing(): void
    {
        $this->mode = 'worker';
        $this->task('gone', everySeconds: 1800, nextDue: '-3 days', failures: 5, error: 'boom', declared: false);

        $this->assertNull($this->failure('queue-task-overdue'));
        $this->assertNull($this->failure('queue-task-failing'));

        $this->task('gone', everySeconds: 1800, nextDue: '-3 days', failures: 5, error: 'boom', declared: true);
        $this->assertNotNull($this->failure('queue-task-overdue'));
        $this->assertNotNull($this->failure('queue-task-failing'));
    }

    // --- queue-backlog -----------------------------------------------------------------------

    public function testBacklogIsDegradedWhileTheConsumerWorks(): void
    {
        $this->mode = 'request';
        $this->pass('request', startedAt: '-1 minutes');
        $this->message('default', '-5 hours');
        $this->assertNull($this->failure('queue-backlog'));

        $this->db->query("DELETE FROM {$this->schema->table()} WHERE queue_name = 'default'");
        $this->message('default', '-7 hours');
        $this->assertNotNull($this->failure('queue-backlog'));
        $this->assertNull($this->failure('queue-stalled'));
    }

    public function testBacklogIsLeftToTheStalledCheckWhenNothingConsumes(): void
    {
        $this->mode = 'request';
        $this->message('default', '-7 hours');
        $this->pass('request', startedAt: '-25 hours');

        $this->assertNull($this->failure('queue-backlog'), 'once it is stalled, the Broken check speaks');
        $this->assertNotNull($this->failure('queue-stalled'));
    }

    public function testBacklogThresholdIsAnHourForBlockingModes(): void
    {
        $this->mode = 'worker';
        $this->pass('worker', startedAt: '-2 hours', finishedAt: '-10 seconds', stopReason: '');
        $this->message('default', '-50 minutes');
        $this->assertNull($this->failure('queue-backlog'));

        $this->db->query("DELETE FROM {$this->schema->table()} WHERE queue_name = 'default'");
        $this->message('default', '-70 minutes');
        $this->assertNotNull($this->failure('queue-backlog'));
    }

    // --- consumer health ---------------------------------------------------------------------

    public function testRestartLoop(): void
    {
        $this->mode = 'worker';
        foreach (range(1, 5) as $minutesAgo) {
            $this->pass('worker', startedAt: "-{$minutesAgo} minutes");
        }
        $this->assertNull($this->failure('queue-consumer-restarts'), 'five starts in ten minutes is not yet a loop');

        $this->pass('worker', startedAt: '-6 minutes');
        $this->assertNotNull($this->failure('queue-consumer-restarts'));

        $this->mode = 'api';
        $this->assertNull($this->failure('queue-consumer-restarts'), 'only the blocking modes have consumers that restart');
    }

    /** R17: a consumer that stopped because the configuration changed asked for its restart; an admin saving settings six times is not a loop. */
    public function testRestartsTheConfigurationAskedForAreNotALoop(): void
    {
        $this->mode = 'worker';
        foreach (range(2, 7) as $minutesAgo) {
            $this->pass('worker', startedAt: "-{$minutesAgo} minutes", stopReason: 'config-changed');
        }
        $this->pass('worker', startedAt: '-1 minute', stopReason: '');
        $this->assertNull($this->failure('queue-consumer-restarts'));

        foreach (range(1, 5) as $secondsAgo) {
            $this->pass('worker', startedAt: "-{$secondsAgo} seconds", stopReason: 'error');
        }
        $this->assertNotNull($this->failure('queue-consumer-restarts'), 'the lives that ended otherwise still count');
    }

    public function testRestartsOlderThanTenMinutesDoNotCount(): void
    {
        $this->mode = 'worker';
        foreach (range(11, 20) as $minutesAgo) {
            $this->pass('worker', startedAt: "-{$minutesAgo} minutes");
        }

        $this->assertNull($this->failure('queue-consumer-restarts'));
    }

    public function testConsumerMemory(): void
    {
        $this->mode = 'worker';
        $this->pass('worker', startedAt: '-2 hours', peakMemory: 120_000_000, memoryLimit: 128_000_000);
        $this->assertNotNull($this->failure('queue-consumer-memory'));

        $this->pass('worker', startedAt: '-1 hours', peakMemory: 100_000_000, memoryLimit: 128_000_000);
        $this->assertNull($this->failure('queue-consumer-memory'), 'it is the last blocking pass that tells');
    }

    public function testConsumerMemoryIgnoresPassesWithoutALimit(): void
    {
        $this->mode = 'worker';
        $this->pass('worker', startedAt: '-1 hours', peakMemory: 500_000_000, memoryLimit: 0);

        $this->assertNull($this->failure('queue-consumer-memory'));
    }

    // --- Wording and edge cases ---------------------------------------------------------------

    public function testAnUnreachableDatabaseIsBrokenAndSilencesTheOthers(): void
    {
        $this->message('default', '-5 hours');
        $this->unreachableSchema = $this->createStub(QueueSchema::class);
        $this->unreachableSchema->method('exists')->willThrowException(new \RuntimeException('no route to host'));

        $this->assertSame(_t('QUEUE_HEALTH_TABLES_UNREACHABLE', ['error' => 'no route to host']), $this->failure('queue-tables'));
        foreach (array_diff($this->ids(), ['queue-tables', 'queue-config']) as $id) {
            $this->assertNull($this->failure($id), $id);
        }
    }

    public function testFailedMessagesAreCountedAndTheBusiestTypesComeFirstUpToFive(): void
    {
        foreach (range(1, 7) as $n) {
            foreach (range(1, $n) as $unused) {
                $this->message('failed', '-1 hours', "Gone\\Message\\Type{$n}", 'HTTP 500');
            }
        }

        $listed = [];
        foreach ([7, 6, 5, 4, 3] as $n) {
            $listed[] = QueueWording::type("Gone\\Message\\Type{$n}") . " ($n)";
        }

        $this->assertSame(
            _t('QUEUE_HEALTH_FAILED_FAILED', ['count' => 28, 'types' => implode(', ', $listed) . ', ' . _t('QUEUE_HEALTH_AND_OTHERS', ['count' => 2])]),
            $this->failure('queue-failed')
        );
    }

    /** @return array<string, array{string, string, string}> the mode of the wiki, the mode that killed the message, the sentence it gets */
    public static function killedSentences(): array
    {
        return [
            'still in the mode that kills it' => ['request', 'request', 'QUEUE_HEALTH_KILLED_FAILED'],
            'since moved to another mode' => ['worker', 'request', 'QUEUE_HEALTH_KILLED_PREVIOUS'],
            'killed in a blocking mode' => ['worker', 'worker', 'QUEUE_HEALTH_KILLED_BLOCKING'],
            'killed in a mode that no longer exists' => ['request', 'warp', 'QUEUE_HEALTH_KILLED_PREVIOUS'],
        ];
    }

    #[DataProvider('killedSentences')]
    public function testAKilledMessageIsToldInTheWordsOfItsMode(string $mode, string $killedIn, string $key): void
    {
        $this->mode = $mode;
        $this->message('failed', '-1 hours', QueueTestMessage::class, killedIn: $killedIn);
        $this->message('failed', '-1 hours', QueueTestMessage::class, killedIn: $killedIn);

        $this->assertSame(
            _t($key, ['types' => QueueWording::type(QueueTestMessage::class) . ' (2)', 'mode' => $killedIn, 'current' => $mode]),
            $this->failure('queue-killed')
        );
    }

    public function testKilledSaysWhatIsFailedAndWhatIsStuckInOneSentenceEach(): void
    {
        $this->mode = 'request';
        $this->message('failed', '-1 hours', QueueTestMessage::class, killedIn: 'request');
        $this->claimed('-2 hours', limit: 3600, mode: 'request');

        $this->assertSame(
            _t('QUEUE_HEALTH_KILLED_FAILED', ['types' => QueueWording::type(QueueTestMessage::class) . ' (1)', 'mode' => 'request', 'current' => 'request'])
            . ' ' . _t('QUEUE_HEALTH_KILLED_STUCK', ['types' => QueueWording::type(QueueTestMessage::class) . ' (1)', 'mode' => 'request']),
            $this->failure('queue-killed')
        );
    }

    public function testAnUnknownModeIsNamedWithWhereItWasSet(): void
    {
        $this->mode = 'wroker';

        $this->assertSame(
            _t('QUEUE_HEALTH_CONFIG_UNKNOWN', ['value' => 'wroker', 'origin' => _t('QUEUE_ORIGIN_' . strtoupper($this->settings()->origin()))]),
            $this->failure('queue-config')
        );
    }

    /** A consumer is near its memory limit above 90 % of it, not at 90 %. */
    public function testConsumerMemoryAlertsAboveNinetyPercentOfTheLimitOnly(): void
    {
        $this->mode = 'worker';
        $this->pass('worker', startedAt: '-1 hours', peakMemory: 90 * 1048576, memoryLimit: 100 * 1048576);
        $this->assertNull($this->failure('queue-consumer-memory'), 'exactly 90 %');

        $this->pass('worker', startedAt: '-30 minutes', peakMemory: 90 * 1048576 + 1048576, memoryLimit: 100 * 1048576);
        $this->assertSame(
            _t('QUEUE_HEALTH_MEMORY_FAILED', ['used' => _t('QUEUE_HEALTH_MEGABYTES', ['count' => 91]), 'limit' => _t('QUEUE_HEALTH_MEGABYTES', ['count' => 100])]),
            $this->failure('queue-consumer-memory')
        );
    }

    // --- configuration -----------------------------------------------------------------------

    public function testUnknownModeIsBroken(): void
    {
        $this->mode = 'wroker';

        $failure = $this->failure('queue-config');

        $this->assertNotNull($failure);
        $this->assertStringContainsString('wroker', $failure);
    }

    public function testAWorkerIsWellConfiguredUnderTheBinaryOrNot(): void
    {
        $this->mode = 'worker';
        $this->assertNull($this->failure('queue-config'));

        putenv(QueueSettings::ENV_BINARY . '=1');
        $this->assertNull($this->failure('queue-config'));
    }

    public function testBinaryIsAnUnknownMode(): void
    {
        $this->mode = 'binary';
        putenv(QueueSettings::ENV_BINARY . '=1');

        $this->assertStringContainsString('binary', (string)$this->failure('queue-config'));
    }

    public function testApiWithoutSecretIsBroken(): void
    {
        $this->mode = 'api';
        $this->assertNotNull($this->failure('queue-config'));

        $this->secret = 'long-and-random';
        $this->assertNull($this->failure('queue-config'));
    }

    // --- cost to the wiki's users -------------------------------------------------------------

    public function testNoBudget(): void
    {
        $this->mode = 'request';
        foreach ([5, 4, 3] as $minutesAgo) {
            $this->pass('request', startedAt: "-{$minutesAgo} minutes", stopReason: 'empty');
        }
        foreach ([2, 1] as $minutesAgo) {
            $this->pass('request', startedAt: "-{$minutesAgo} minutes", stopReason: 'no-budget');
        }
        $this->assertNull($this->failure('queue-no-budget'), 'two of the last five');

        $this->pass('request', startedAt: '-30 seconds', stopReason: 'no-budget');
        $this->assertNotNull($this->failure('queue-no-budget'), 'three of the last five');
    }

    public function testNoBudgetLooksOnlyAtBudgetedPasses(): void
    {
        $this->mode = 'api';
        foreach ([1, 2, 3] as $minutesAgo) {
            $this->pass('api', startedAt: "-{$minutesAgo} minutes", stopReason: 'no-budget');
        }
        // A blocking consumer started since: its row is not a budgeted pass and must not push the others out of the window.
        foreach ([1, 2, 3, 4, 5] as $secondsAgo) {
            $this->pass('worker', startedAt: "-{$secondsAgo} seconds");
        }

        $this->assertNotNull($this->failure('queue-no-budget'));

        $this->mode = 'worker';
        $this->assertNull($this->failure('queue-no-budget'), 'the budgeted modes are gone, so is the problem');
    }

    public function testVisitorCost(): void
    {
        $this->mode = 'request';
        $this->pass('request', startedAt: '-2 hours', finishedAt: '-2 hours', beforeResponse: true);
        $this->pass('request', startedAt: '-1 hours', finishedAt: '-1 hours +2 seconds', beforeResponse: true);
        $this->assertNull($this->failure('queue-visitor-cost'), 'exactly a second on average is not over it');

        $this->pass('request', startedAt: '-30 minutes', finishedAt: '-30 minutes +4 seconds', beforeResponse: true);
        $failure = $this->failure('queue-visitor-cost');

        $this->assertNotNull($failure);
        $this->assertStringContainsString('2.0', $failure, 'the average is in the sentence');
    }

    public function testVisitorCostCountsOnlyWhatVisitorsWaitedFor(): void
    {
        $this->mode = 'request';
        // After the response left: nobody waited.
        $this->pass('request', startedAt: '-1 hours', finishedAt: '-1 hours +20 seconds', beforeResponse: false);
        // Before it, but yesterday.
        $this->pass('request', startedAt: '-26 hours', finishedAt: '-26 hours +20 seconds', beforeResponse: true);

        $this->assertNull($this->failure('queue-visitor-cost'));

        $this->pass('request', startedAt: '-1 hours', finishedAt: '-1 hours +20 seconds', beforeResponse: true);
        $this->mode = 'api';
        $this->assertNull($this->failure('queue-visitor-cost'), 'only the request mode makes visitors wait');
    }

    // --- Helpers -----------------------------------------------------------------------------

    /** @return list<HealthCheck> */
    private function checks(): array
    {
        $hibernation = $this->createStub(HibernationService::class);
        $hibernation->method('isWikiHibernatedNow')->willReturnCallback(fn (): bool => $this->hibernated);

        $settings = $this->settings();

        return (new QueueHealthChecks(
            $settings,
            $this->statistics,
            $this->unreachableSchema ?? $this->schema,
            $hibernation,
            $this->ticker(),
            new QueueFallback($settings, $this->statistics, $this->ticker(), $hibernation, new RuntimeLock(), $this->clock),
            new QueueBudgetFactory(new NativeClock()),
            $this->clock
        ))->healthChecks();
    }

    private function settings(): QueueSettings
    {
        return new QueueSettings(
            new ParameterBag(['queue_consumer' => $this->mode, 'queue_consume_secret' => $this->secret]),
            static fn (): array => []
        );
    }

    /** What a fallback pass may spend, as its alert says it. */
    private function requestBudget(): int
    {
        return (int)round((new QueueBudgetFactory(new NativeClock()))->forMode(QueueConsumerMode::Request, QueueRequestTrigger::respondsEarly())->remainingSeconds());
    }

    /** What the fallback's alert adds on a server that cannot hand the page over before the pass: the CLI, here. */
    private function visitorsWait(): string
    {
        return QueueRequestTrigger::respondsEarly() ? '' : _t('QUEUE_HEALTH_FALLBACK_VISITORS_WAIT');
    }

    /** A ticker over the tasks the test says are declared and the Journal's rows: what the checks list the tasks from. */
    private function ticker(): QueueScheduleTicker
    {
        $provider = new class(fn (): array => $this->declared) implements ProvidesSchedule {
            /** @param \Closure(): array<string, array{string, int}> $declared */
            public function __construct(private \Closure $declared)
            {
            }

            public function recurringMessages(): array
            {
                $recurring = [];
                foreach (($this->declared)() as $id => [$label, $every]) {
                    $recurring[] = RecurringMessageTimezoned::every($every, new QueueTestTask((string)$id, $label === '' ? null : $label));
                }

                return $recurring;
            }
        };

        return new QueueScheduleTicker(
            [$provider],
            $this->createStub(QueueBus::class),
            new ScheduleHistory($this->db, $this->journal),
            $this->recorder($this->clock),
            new RuntimeLock(),
            $this->clock,
            $this->createStub(HibernationService::class),
        );
    }

    private function recorder(MockClock $clock): QueueRecorder
    {
        return new QueueRecorder($this->db, $this->journal, $clock);
    }

    /** @return list<string> */
    private function ids(): array
    {
        return array_map(static fn (HealthCheck $check): string => $check->id(), $this->checks());
    }

    private function check(string $id): HealthCheck
    {
        foreach ($this->checks() as $check) {
            if ($check->id() === $id) {
                return $check;
            }
        }
        $this->fail("No check named $id");
    }

    private function failure(string $id): ?string
    {
        return $this->check($id)->failure();
    }

    private function at(string $relative): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->modify($relative)->format('Y-m-d H:i:s');
    }

    /** @param string|null $killedIn the mode its consumer was killed in, for a message the transport failed as killed (its cause is then that sentence) */
    private function message(string $queue, string $availableAt, string $type = QueueTestMessage::class, ?string $error = null, ?string $killedIn = null): void
    {
        $this->db->query(
            "INSERT INTO {$this->schema->table()} (queue_name, message_type, body, headers, created_at, available_at, attempts, last_error, killed_in)"
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$queue, $type, '{}', '{}', $this->at($availableAt), $this->at($availableAt), $queue === 'failed' ? 3 : 0, $killedIn === null ? $error : QueueTransport::killedCause($killedIn), $killedIn]
        );
    }

    /** A task, declared or not, whose last dispatch makes it due at $nextDue (null: never seen), after $failures failed runs. */
    private function task(string $id, string $label = '', int $everySeconds = 1800, ?string $nextDue = null, int $failures = 0, ?string $error = null, bool $declared = true): void
    {
        unset($this->declared[$id]);
        if ($declared) {
            $this->declared[$id] = [$label, $everySeconds];
        }
        if ($nextDue !== null) {
            $this->recorder(new MockClock($this->at($nextDue . " -{$everySeconds} seconds"), 'UTC'))->taskDispatched(new QueueTestTask($id));
        }
        if ($failures > 0) {
            $recorder = $this->recorder($this->clock);
            $recorder->taskFinished($id, QueueRecorder::HANDLED, null);
            for ($run = 0; $run < $failures; $run++) {
                $recorder->taskFinished($id, QueueRecorder::FAILED, $error);
            }
        }
    }

    /** A message a consumer has taken and not acknowledged: how long ago, and the time it was given. */
    private function claimed(string $deliveredAt, int $limit, string $mode): void
    {
        $this->db->query(
            "INSERT INTO {$this->schema->table()} (queue_name, message_type, body, headers, created_at, available_at, delivered_at, delivered_by, delivered_mode, delivered_limit, attempts)"
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            ['default', QueueTestMessage::class, '{}', '{}', $this->at('-1 days'), $this->at('-1 days'), $this->at($deliveredAt), 'token', $mode, $limit, 0]
        );
    }

    /** A pass as the recorder writes it; a blocking mode's pass is its consumer's whole life. */
    private function pass(
        string $mode,
        string $startedAt,
        ?string $finishedAt = null,
        string $stopReason = 'empty',
        int $peakMemory = 0,
        int $memoryLimit = 0,
        bool $beforeResponse = false,
        ?QueuePassOrigin $origin = null,
        ?QueueConsumerMode $fallbackFor = null,
    ): void {
        QueueTestKit::recordPass(
            $this->db,
            QueueConsumerMode::from($mode),
            $this->at($startedAt),
            $this->at($finishedAt ?? $startedAt),
            $stopReason,
            $peakMemory,
            $memoryLimit,
            $beforeResponse,
            $origin,
            $fallbackFor,
        );
    }
}
