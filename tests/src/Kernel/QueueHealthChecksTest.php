<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\Severity;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueWording;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Lock\FileLocks;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueFallback;
use YesWiki\Kernel\Service\Queue\QueueHealthChecks;
use YesWiki\Kernel\Service\Queue\QueueHolderLock;
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
require_once 'tests/src/Kernel/QueueTestHolders.php';
require_once 'tests/src/Kernel/QueueTestMessage.php';
require_once 'tests/src/Kernel/QueueTestTask.php';
require_once 'tests/src/Kernel/QueueTestSenderMessage.php';

/** What the admin is told when a background job stops, fails or is misconfigured (messenger / scheduler): each check against rows written into the queue's table and in its history table. */
#[CoversClass(QueueHealthChecks::class)]
class QueueHealthChecksTest extends YesWikiTestCase
{
    private const START = '2026-10-23 12:00:00';

    private string $file;
    private MockClock $clock;
    private DbService $db;
    private QueueSchema $schema;
    private QueueStatistics $statistics;

    private string $mode = '';
    private string $secret = '';
    private bool $hibernated = false;
    /** A schema that cannot tell whether its tables exist, when a test needs the database to be out of reach. */
    private ?QueueSchema $unreachableSchema = null;

    /** @var array<string, array{string, int}> the tasks the schedule declares: id => label, interval in seconds */
    private array $declared = [];

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-queue-') . '.db';
        $this->clock = new MockClock(new \DateTimeImmutable(self::START));
        $this->db = new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywq_',
            'debug' => false,
        ]));
        $this->schema = new QueueSchema($this->db);
        $this->schema->create();
        $this->statistics = new QueueStatistics($this->db, $this->schema, new QueueTransport($this->db, $this->schema, clock: $this->clock), $this->clock);
    }

    protected function tearDown(): void
    {
        unset($this->statistics, $this->schema, $this->db);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
    }

    // --- The queue is healthy ----------------------------------------------------------------

    public function testAHealthyQueueReportsNothing(): void
    {
        $this->mode = 'worker';
        $this->pass('worker', startedAt: '-1 hour', finishedAt: '-1 minute', stopReason: '');
        $this->message('default', '-1 minutes');
        $this->task('purge', everySeconds: 1800, nextDue: '+10 minutes');

        foreach ($this->ids() as $id) {
            $this->assertNull($this->failure($id), "$id should pass on a healthy queue");
        }
    }

    public function testEveryCheckPointsToTheJobsPage(): void
    {
        $this->assertEqualsCanonicalizing([
            'queue-tables', 'queue-fallback', 'queue-stalled', 'queue-failed', 'queue-killed', 'queue-task-overdue',
            'queue-backlog', 'queue-consumer-restarts', 'queue-config', 'queue-php-limit', 'queue-visitor-cost',
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
        $degraded = ['queue-backlog', 'queue-php-limit', 'queue-visitor-cost'];
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

        $this->assertSame(
            _t('QUEUE_HEALTH_FALLBACK_FAILED', ['mode' => 'worker', 'since' => QueueWording::duration(3 * 3600 - 600), 'idle' => QueueWording::duration(3 * 3600)]) . $this->visitorsWait(),
            $this->failure('queue-fallback'),
            'the mode that is gone, since when the requests consume (its delay after it was last seen), and for how long it was not seen'
        );
    }

    public function testAFallbackForAConsumerNeverSeenSaysSo(): void
    {
        $this->mode = 'api';
        $this->pass('request', startedAt: '-2 hours');

        $this->assertSame(
            _t('QUEUE_HEALTH_FALLBACK_NEVER', ['mode' => 'api']) . $this->visitorsWait(),
            $this->failure('queue-fallback'),
            'the passes the end of requests ran are not the cron'
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
        $this->pass('request', startedAt: '-5 minutes');

        $this->assertNotNull($this->failure('queue-fallback'));
        $this->assertNull($this->failure('queue-stalled'));
    }

    /** With no visit, the fallback consumes nothing either: then the queue is stalled, as `request`'s delay tells. */
    public function testStalledSpeaksAgainWhenTheFallbackItselfStops(): void
    {
        $this->mode = 'worker';
        $this->message('default', '-25 hours');
        $this->pass('worker', startedAt: '-30 hours', finishedAt: '-26 hours');
        $this->pass('request', startedAt: '-25 hours');

        $this->assertNotNull($this->failure('queue-stalled'));
    }

    /** @return array<string, array{string}> */
    public static function passesThatDidNothing(): array
    {
        return ['the wiki was paused' => ['paused']];
    }

    #[DataProvider('passesThatDidNothing')]
    public function testPassesThatCouldNotStartDoNotHidePhpLimitPasses(string $stopReason): void
    {
        $this->mode = 'api';
        foreach (['-50', '-40', '-30'] as $minutes) {
            $this->pass('api', startedAt: "$minutes minutes", stopReason: 'php-limit');
        }
        foreach (['-3', '-2', '-1'] as $minutes) {
            $this->pass('api', startedAt: "$minutes minutes", stopReason: $stopReason);
        }

        $this->assertNotNull($this->failure('queue-php-limit'));
    }

    // --- queue-stalled -----------------------------------------------------------------------

    /** A worker unseen past its delay has the end of requests stand in for it: the fallback says so, and the queue is judged by `request`'s delay. */
    public function testAWorkerUnseenForTenMinutesIsTheFallbacksAlertNotAStall(): void
    {
        $this->mode = 'worker';
        $this->message('default', '-20 minutes');
        $this->pass('worker', startedAt: '-3 hours', finishedAt: '-11 minutes');

        $this->assertNotNull($this->failure('queue-fallback'));
        $this->assertNull($this->failure('queue-stalled'));
    }

    public function testNotStalledInWorkerModeBeforeTenMinutes(): void
    {
        $this->mode = 'worker';
        $this->message('default', '-20 minutes');
        $this->pass('worker', startedAt: '-3 hours', finishedAt: '-9 minutes');
        $this->assertNull($this->failure('queue-fallback'));

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

    /** Past its mode's delay, the end of requests is stalled; the cron or the worker has the end of requests stand in for it, which the fallback says. */
    #[DataProvider('stalledAfter')]
    public function testEachModeHasItsOwnPatience(string $mode, int $seconds, string $alert): void
    {
        $this->mode = $mode;
        $this->message('default', '-3 days');

        $this->pass($mode, startedAt: '-' . ($seconds - 60) . ' seconds');
        $this->assertNull($this->failure('queue-stalled'), 'just under');
        $this->assertNull($this->failure('queue-fallback'), 'just under');

        $this->db->query("DELETE FROM {$this->schema->historyTable()}");
        $this->pass($mode, startedAt: '-' . ($seconds + 60) . ' seconds');
        $this->assertNotNull($this->failure($alert), 'just over');
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
        $this->mode = 'request';
        $this->message('default', '-23 hours');
        $this->assertNull($this->failure('queue-stalled'), 'a message sent to a fresh wiki is not a stall within the delay');

        $this->db->query("DELETE FROM {$this->schema->table()} WHERE queue_name = 'default'");
        $this->message('default', '-25 hours');
        $this->assertNotNull($this->failure('queue-stalled'), 'a fresh install with messages and no pass must alert');
    }

    public function testAMessageThatIsBeingHandledIsNotWaiting(): void
    {
        $this->mode = 'worker';
        $this->pass('worker', startedAt: '-3 hours', finishedAt: '-2 hours');
        $this->claimed('-30 minutes', mode: 'worker');

        $this->assertNull($this->failure('queue-stalled'), 'nothing is available, the consumer has it');
    }

    /** A blocking consumer inside one long handler (a backup) does not beat: the lock its pass holds says it is at work, as long as it holds it. */
    public function testALongJobInProgressIsActivity(): void
    {
        $this->mode = 'worker';
        $dir = sys_get_temp_dir() . '/yw-health-holders-' . bin2hex(random_bytes(4));
        $this->statistics = new QueueStatistics($this->db, $this->schema, new QueueTransport($this->db, $this->schema, clock: $this->clock, holderLock: new QueueHolderLock(new FileLocks(QueueTestKit::locksIn($dir)))), $this->clock);
        $holder = QueueTestHolders::token();
        $this->message('default', '-40 minutes');
        QueueTestKit::recordPass($this->db, QueueConsumerMode::Worker, $this->at('-3 hours'), $this->at('-50 minutes'), '', holder: $holder);
        $this->claimed('-50 minutes', mode: 'worker', holder: $holder);
        $letGo = QueueTestHolders::lockHeldByAnotherProcess($dir . '/' . QueueHolderLock::pathOf($holder));

        try {
            try {
                $this->assertNull($this->failure('queue-fallback'), 'the consumer is busy, not gone');
                $this->assertNull($this->failure('queue-stalled'));
            } finally {
                $this->assertSame(0, $letGo());
            }

            $this->assertNotNull($this->failure('queue-fallback'), 'once its lock is let go, it was last seen when it last beat');
        } finally {
            @unlink($dir . '/' . QueueHolderLock::pathOf($holder));
            @rmdir($dir . '/' . QueueHolderLock::DIRECTORY);
            @rmdir($dir . '/cache');
            @rmdir($dir);
        }
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function stalledAfter(): iterable
    {
        yield 'worker' => ['worker', 600, 'queue-fallback'];
        yield 'api' => ['api', 3 * 3600, 'queue-fallback'];
        yield 'request' => ['request', 24 * 3600, 'queue-stalled'];
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

    /**
     * A backup pinned in `api` runs in a `request` pass, in the admin's request or in the process it hands the backup to: killed there, it is not blamed
     * on a mode the wiki no longer uses, since `request` passes run whatever the mode.
     */
    public function testAPinnedMessageKilledInApiModeIsNotSaidToBeOfAModeTheWikiLeft(): void
    {
        $this->mode = 'api';
        $sent = (new QueueTransport($this->db, $this->schema, clock: $this->clock))->send(new Envelope(new QueueTestSenderMessage('backup')));
        QueueTestKit::failAsKilled($this->db, $this->schema, $this->clock, (int)$sent->last(TransportMessageIdStamp::class)?->getId(), QueueConsumerMode::Request);
        $values = ['types' => QueueWording::type(QueueTestSenderMessage::class) . ' (1)', 'mode' => 'request', 'current' => 'api'];

        $failure = $this->failure('queue-killed');

        $this->assertNotSame(_t('QUEUE_HEALTH_KILLED_PREVIOUS', $values), $failure);
        $this->assertSame(_t('QUEUE_HEALTH_KILLED_REQUEST_PASS', $values), $failure);
    }

    public function testAClaimNobodyReapedYetIsKilledToo(): void
    {
        $this->mode = 'request';
        $this->claimed('-30 minutes', mode: 'request');
        $this->assertNull($this->failure('queue-killed'), 'within its limit');

        $this->claimed('-2 hours', mode: 'request');
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

    /** A deleted data source or a disabled extension leaves its row behind, and nobody could clear its alert. */
    public function testATaskThatLeftTheScheduleRaisesNothing(): void
    {
        $this->mode = 'worker';
        $this->task('gone', everySeconds: 1800, nextDue: '-3 days', declared: false);

        $this->assertNull($this->failure('queue-task-overdue'));

        $this->task('gone', everySeconds: 1800, nextDue: '-3 days', declared: true);
        $this->assertNotNull($this->failure('queue-task-overdue'));
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

    /** A consumer that stopped because the configuration changed asked for its restart; an admin saving settings six times is not a loop. */
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
            'since moved to another mode' => ['worker', 'api', 'QUEUE_HEALTH_KILLED_PREVIOUS'],
            'in a request pass, the wiki in api' => ['api', 'request', 'QUEUE_HEALTH_KILLED_REQUEST_PASS'],
            'in a request pass, the wiki in worker' => ['worker', 'request', 'QUEUE_HEALTH_KILLED_REQUEST_PASS'],
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
        $this->claimed('-2 hours', mode: 'request');

        $this->assertSame(
            _t('QUEUE_HEALTH_KILLED_FAILED', ['types' => QueueWording::type(QueueTestMessage::class) . ' (1)', 'mode' => 'request', 'current' => 'request'])
            . ' ' . _t('QUEUE_HEALTH_KILLED_STUCK', ['types' => QueueWording::type(QueueTestMessage::class) . ' (1)', 'mode' => 'request']),
            $this->failure('queue-killed')
        );
    }

    /** A server's process past its limit that still holds its lock is at work, not stuck: no sentence about it until it lets go. */
    public function testAClaimWhoseLockIsHeldPastItsLimitIsNotStuck(): void
    {
        $this->mode = 'request';
        $dir = sys_get_temp_dir() . '/yw-health-holders-' . bin2hex(random_bytes(4));
        $this->statistics = new QueueStatistics($this->db, $this->schema, new QueueTransport($this->db, $this->schema, clock: $this->clock, holderLock: new QueueHolderLock(new FileLocks(QueueTestKit::locksIn($dir)))), $this->clock);
        $token = QueueTestHolders::token();
        $this->claimed('-10 minutes', mode: 'request', holder: $token);
        $letGo = QueueTestHolders::lockHeldByAnotherProcess($dir . '/' . QueueHolderLock::pathOf($token));

        try {
            try {
                $this->assertNull($this->failure('queue-killed'), 'its limit and the grace are long past, but its lock says it is at work');
            } finally {
                $this->assertSame(0, $letGo());
            }

            $this->assertSame(
                _t('QUEUE_HEALTH_KILLED_STUCK', ['types' => QueueWording::type(QueueTestMessage::class) . ' (1)', 'mode' => 'request']),
                $this->failure('queue-killed'),
                'once it let go of its lock without its ack, it is gone'
            );
        } finally {
            @unlink($dir . '/' . QueueHolderLock::pathOf($token));
            @rmdir($dir . '/' . QueueHolderLock::DIRECTORY);
            @rmdir($dir . '/cache');
            @rmdir($dir);
        }
    }

    public function testAnUnknownModeIsNamedInTheAlert(): void
    {
        $this->mode = 'wroker';

        $this->assertSame(
            _t('QUEUE_HEALTH_CONFIG_UNKNOWN', ['value' => 'wroker']),
            $this->failure('queue-config')
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

    public function testAWorkerIsWellConfigured(): void
    {
        $this->mode = 'worker';
        $this->assertNull($this->failure('queue-config'));

    }

    public function testBinaryIsAnUnknownMode(): void
    {
        $this->mode = 'binary';

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

    public function testPhpLimitTripsOnOneOfTheLastFivePasses(): void
    {
        $this->mode = 'request';
        foreach ([5, 4, 3, 2] as $minutesAgo) {
            $this->pass('request', startedAt: "-{$minutesAgo} minutes", stopReason: 'empty');
        }
        $this->assertNull($this->failure('queue-php-limit'), 'no pass stopped on the limit');

        $this->pass('request', startedAt: '-1 minutes', stopReason: 'php-limit');

        $this->assertSame(
            _t('QUEUE_HEALTH_PHP_LIMIT_FAILED', ['count' => 1, 'passes' => 5]),
            $this->failure('queue-php-limit'),
            'one of the last five'
        );
    }

    /** A pass that ran out the wiki's own ceiling had all it asked for: the host refused nothing. */
    public function testPhpLimitIgnoresAStopOnTheWikisOwnCeiling(): void
    {
        $this->mode = 'request';
        $this->pass('request', startedAt: '-1 minutes', stopReason: 'time-cap');

        $this->assertNull($this->failure('queue-php-limit'));
    }

    public function testPhpLimitIgnoresAnOlderStop(): void
    {
        $this->mode = 'request';
        $this->pass('request', startedAt: '-6 minutes', stopReason: 'php-limit');
        foreach ([5, 4, 3, 2, 1] as $minutesAgo) {
            $this->pass('request', startedAt: "-{$minutesAgo} minutes", stopReason: 'empty');
        }

        $this->assertNull($this->failure('queue-php-limit'), 'the stop is the sixth pass back');
    }

    public function testPhpLimitIsSilentForTheWorker(): void
    {
        $this->mode = 'worker';
        $this->pass('request', startedAt: '-1 minutes', stopReason: 'php-limit');

        $this->assertNull($this->failure('queue-php-limit'), 'a worker is not under a request\'s limit');
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
            new QueueFallback($settings, $this->statistics, $hibernation, new RuntimeLock(), $this->clock),
            $this->clock
        ))->healthChecks();
    }

    private function settings(): QueueSettings
    {
        return new QueueSettings(
            new ParameterBag(['queue_consumer' => $this->mode, 'queue_consume_secret' => $this->secret]));
    }

    /** What the fallback's alert adds on a server that cannot hand the page over before the pass: the CLI, here. */
    private function visitorsWait(): string
    {
        return QueueRequestTrigger::respondsEarly() ? '' : _t('QUEUE_HEALTH_FALLBACK_VISITORS_WAIT');
    }

    /** A ticker over the tasks the test says are declared and the history's rows: what the checks list the tasks from. */
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
                    $recurring[] = RecurringMessage::every($every, new QueueTestTask((string)$id, $label === '' ? null : $label));
                }

                return $recurring;
            }
        };

        return new QueueScheduleTicker(
            [$provider],
            $this->createStub(QueueBus::class),
            new ScheduleHistory($this->db, $this->schema),
            $this->recorder($this->clock),
            new FileLocks(),
            $this->clock,
            $this->createStub(HibernationService::class),
        );
    }

    private function recorder(MockClock $clock): QueueRecorder
    {
        return new QueueRecorder($this->db, $this->schema, $clock);
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
        return $this->clock->now()->modify($relative)->format('Y-m-d H:i:s');
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

    /** A task, declared or not, whose last dispatch makes it due at $nextDue, a period after it (null: never seen). */
    private function task(string $id, string $label = '', int $everySeconds = 1800, ?string $nextDue = null, bool $declared = true): void
    {
        unset($this->declared[$id]);
        if ($declared) {
            $this->declared[$id] = [$label, $everySeconds];
        }
        if ($nextDue !== null) {
            $this->recorder(new MockClock(new \DateTimeImmutable($this->at($nextDue . " -{$everySeconds} seconds"))))->taskDispatched(new QueueTestTask($id));
        }
    }

    /** A message a consumer has taken and not acknowledged: how long ago, and the time it was given. */
    private function claimed(string $deliveredAt, string $mode, string $holder = 'token'): void
    {
        $this->db->query(
            "INSERT INTO {$this->schema->table()} (queue_name, message_type, body, headers, created_at, available_at, delivered_at, delivered_by, delivered_mode, attempts)"
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            ['default', QueueTestMessage::class, '{}', '{}', $this->at('-1 days'), $this->at('-1 days'), $this->at($deliveredAt), $holder, $mode, 0]
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
        );
    }
}
