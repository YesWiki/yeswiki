<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueFallback;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTaskRows;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\Queue\ScheduleHistory;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTestMessage.php';
require_once 'tests/src/Kernel/QueueTestTask.php';

/** When the end of requests has to consume for the consumer the admin chose, because that consumer is no longer seen at work. */
#[CoversClass(QueueFallback::class)]
class QueueFallbackTest extends YesWikiTestCase
{
    private const START = '2026-10-29 12:00:00';

    /** A holder on a machine other than this one: its claim lives until its limit. */
    private const ELSEWHERE = 'c4242@abcdef012345.777/';

    private string $file;
    private string $lockDir;
    private string $configFile;
    private string|false $previousConfigFile;
    private string|false $previousMode;
    private MockClock $clock;
    private DbService $db;
    private QueueSchema $schema;
    private QueueTransport $transport;
    private QueueStatistics $statistics;
    private JournalSchema $journal;
    private RuntimeLock $locks;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-queue-') . '.db';
        $this->lockDir = sys_get_temp_dir() . '/yw-fallback-locks-' . bin2hex(random_bytes(4));
        $this->clock = new MockClock(self::START, 'UTC');
        $this->db = new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywq_',
            'debug' => false,
        ]));
        $this->schema = new QueueSchema($this->db);
        $this->schema->create();
        $this->journal = $journal = new JournalSchema($this->db);
        $journal->create();
        $this->transport = new QueueTransport($this->db, $this->schema, clock: $this->clock);
        $this->statistics = new QueueStatistics($this->db, $this->schema, $journal, $this->transport, new QueueTaskRows($this->db, $this->schema, $this->clock), $this->clock);
        $this->locks = QueueTestKit::locksIn($this->lockDir);

        $this->configFile = sys_get_temp_dir() . '/yw-fallback-config-' . bin2hex(random_bytes(4)) . '.php';
        $this->previousConfigFile = getenv('YESWIKI_CONFIG_FILE');
        putenv('YESWIKI_CONFIG_FILE=' . $this->configFile);
        $this->setWikiStatus('running');
        $this->previousMode = getenv(QueueSettings::ENV_MODE);
    }

    protected function tearDown(): void
    {
        unset($this->statistics, $this->transport, $this->schema, $this->db);
        putenv($this->previousMode === false ? QueueSettings::ENV_MODE : QueueSettings::ENV_MODE . '=' . $this->previousMode);
        putenv($this->previousConfigFile === false ? 'YESWIKI_CONFIG_FILE' : 'YESWIKI_CONFIG_FILE=' . $this->previousConfigFile);
        @unlink($this->configFile);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
        foreach (glob($this->lockDir . '/cache/*') ?: [] as $lock) {
            @unlink($lock);
        }
        @rmdir($this->lockDir . '/cache');
        @rmdir($this->lockDir);
    }

    public function testAWorkerNotSeenForElevenMinutesWithAMessageWaitingFallsBack(): void
    {
        $this->messageWaitingSince(660);
        $this->pass(QueueConsumerMode::Worker, '-2 hours', '-11 minutes');

        $this->assertTrue($this->fallback('worker')->shouldConsume());
    }

    public function testAWorkerSeenNineMinutesAgoDoesNot(): void
    {
        $this->messageWaitingSince(660);
        $this->pass(QueueConsumerMode::Worker, '-2 hours', '-9 minutes');

        $this->assertFalse($this->fallback('worker')->shouldConsume());
    }

    /** It does not beat inside one long message (a backup): the claim its process holds shows it at work. */
    public function testAWorkerHandlingOneLongMessageDoesNot(): void
    {
        $this->messageWaitingSince(3600);
        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-30 minutes', stopReason: '', holder: self::ELSEWHERE . 'aa');
        $this->claimed('-30 minutes', QueueConsumerMode::Worker, self::ELSEWHERE . 'bb');

        $this->assertFalse($this->fallback('worker')->shouldConsume());
    }

    /** On a quiet wiki, nothing is queued while nothing ticks the schedule: its being due is what waits. */
    public function testACronNotSeenForThreeHoursWithTheScheduleDueFallsBack(): void
    {
        $this->scheduleDueSince(3 * 3600 + 60);
        $this->pass(QueueConsumerMode::Api, '-3 hours -1 minute', '-3 hours -1 minute');

        $this->assertTrue($this->fallback('api')->shouldConsume());
    }

    public function testACronSeenTwoHoursAgoDoesNot(): void
    {
        $this->scheduleDueSince(2 * 3600);
        $this->pass(QueueConsumerMode::Api, '-2 hours', '-2 hours');

        $this->assertFalse($this->fallback('api')->shouldConsume());
    }

    public function testAConsumerThatNeverRanFallsBackAfterItsDelay(): void
    {
        $fallback = $this->fallback('worker');

        $this->assertFalse($fallback->shouldConsume(), 'nothing has waited yet');

        $this->clock->sleep(660);

        $this->assertTrue($fallback->shouldConsume(), 'the schedule never ticked, and no worker ever showed up');
    }

    public function testRequestNeverFallsBack(): void
    {
        $this->messageWaitingSince(48 * 3600);

        $this->assertFalse($this->fallback('request')->shouldConsume());
    }

    public function testAHibernatedWikiNeverFallsBack(): void
    {
        $this->messageWaitingSince(3600);
        $this->pass(QueueConsumerMode::Worker, '-2 hours', '-1 hour');
        $this->setWikiStatus('archiving');

        $this->assertFalse($this->fallback('worker')->shouldConsume());
    }

    /** The fallback empties the queue, and the rule that started it no longer holds: it goes on all the same, until the worker is back. */
    public function testFallbackIsStickyWhileTheQueueIsEmpty(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-3 hours');
        $this->pass(QueueConsumerMode::Request, '-1 hour', '-1 hour', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Worker);
        $fallback = $this->fallback('worker');

        $this->assertTrue($fallback->shouldConsume());
        $status = $fallback->status();
        $this->assertNotNull($status);
        $this->assertSame($this->at('-1 hour'), $status->since->format('Y-m-d H:i:s'));
        $this->assertSame($this->at('-3 hours'), $status->lastSeen?->format('Y-m-d H:i:s'));
        $this->assertSame(QueueConsumerMode::Worker, $status->mode);
    }

    public function testFallbackEndsWhenTheConsumerIsSeenAgain(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-3 hours');
        $this->pass(QueueConsumerMode::Request, '-1 hour', '-1 hour', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Worker);
        $fallback = $this->fallback('worker');
        $this->assertTrue($fallback->shouldConsume());

        $this->pass(QueueConsumerMode::Worker, '-1 minute', '-1 minute');
        $this->clock->sleep(61);

        $this->assertFalse($fallback->shouldConsume());
        $this->assertNull($fallback->status());
    }

    /** Between the cron's return and the next check, a request still consumes for it: that pass must not start the fallback again. */
    public function testAFallbackPassJustAfterTheConsumerCameBackDoesNotKeepTheFallbackGoing(): void
    {
        $this->pass(QueueConsumerMode::Api, '-5 hours', '-5 hours');
        $this->pass(QueueConsumerMode::Request, '-1 hour', '-1 hour', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Api);
        $fallback = $this->fallback('api');
        $this->assertTrue($fallback->shouldConsume());

        $this->pass(QueueConsumerMode::Api, '-1 second', '-1 second');
        $this->clock->sleep(20);
        $this->pass(QueueConsumerMode::Request, '-1 second', '-1 second', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Api);
        $this->clock->sleep(45);

        $this->assertFalse($fallback->shouldConsume());
        $this->assertNull($fallback->status());
    }

    /** The answer kept between two checks is the mode's it was given for: a worker's fallback is not carried over to the cron the admin just set up. */
    public function testAModeChangeDoesNotCarryTheFallbackOver(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-3 hours');
        $this->pass(QueueConsumerMode::Request, '-1 hour', '-1 hour', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Worker);
        $this->assertTrue($this->fallback('worker')->shouldConsume());

        $this->clock->sleep(10);

        $this->assertFalse($this->fallback('api')->shouldConsume());
    }

    /** A wiki whose queue table is missing is told so once a minute, not on every request. */
    public function testAReadThatFailsIsTriedAtMostOnceAMinute(): void
    {
        $this->schema->drop();
        $fallback = $this->fallback('worker');
        try {
            $fallback->shouldConsume();
            $this->fail('the missing table is not hidden');
        } catch (\Throwable) {
        }

        $this->clock->sleep(30);

        $this->assertFalse($fallback->shouldConsume());
    }

    public function testFallbackEndsWhenTheAdminChangesTheMode(): void
    {
        $this->pass(QueueConsumerMode::Request, '-1 hour', '-1 hour', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Worker);
        $this->pass(QueueConsumerMode::Api, '-1 minute', '-1 minute');

        $this->assertNull($this->fallback('api')->status());
    }

    /** A cron that comes back while a fallback pass holds the lock leaves its row all the same (QueueConsumer::drain()). */
    public function testACronPassThatFoundTheLockTakenEndsTheFallback(): void
    {
        $this->pass(QueueConsumerMode::Api, '-5 hours', '-5 hours');
        $this->pass(QueueConsumerMode::Request, '-1 hour', '-1 hour', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Api);
        $this->pass(QueueConsumerMode::Api, '-1 minute', '-1 minute', stopReason: 'locked');

        $this->assertNull($this->fallback('api')->status());
    }

    /** Outside the fallback a request reads nothing between two checks: the answer of the last one stands. */
    public function testTheRuleIsReadAtMostOnceAMinute(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-2 hours', '-9 minutes -30 seconds');
        $this->messageWaitingSince(3600);
        $fallback = $this->fallback('worker');
        $this->assertFalse($fallback->shouldConsume());

        $this->clock->sleep(40);

        $this->assertFalse($fallback->shouldConsume(), 'the worker is now over ten minutes gone, but 40 s after the check its answer stands');

        $this->clock->sleep(21);

        $this->assertTrue($fallback->shouldConsume(), 'a minute on, the rule is read again');
    }

    /** A long message -- a backup an admin starts -- must not be left to a worker that is gone: the end of requests would take it on a request's budget, and PHP would cut it. */
    public function testAWorkerNotSeenForElevenMinutesIsGone(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-1 hour', '-11 minutes', stopReason: '');

        $this->assertTrue($this->fallback('worker')->consumerIsGone());
    }

    public function testAWorkerSeenAMinuteAgoIsNotGone(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-1 hour', '-1 minute', stopReason: '');

        $this->assertFalse($this->fallback('worker')->consumerIsGone());
    }

    public function testAWorkerThatNeverRanIsGone(): void
    {
        $this->assertTrue($this->fallback('worker')->consumerIsGone());
    }

    /** A cron or an API call only comes when called: nothing it left says it will come, so only a fallback already under way says it is gone. */
    public function testAConsumerThatComesWhenCalledIsGoneOnlyOnceTheFallbackRuns(): void
    {
        $this->assertFalse($this->fallback('api')->consumerIsGone());

        $this->pass(QueueConsumerMode::Request, '-1 minute', '-1 minute', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Api);

        $this->assertTrue($this->fallback('api')->consumerIsGone());
    }

    public function testTheEndOfRequestsIsNeverGone(): void
    {
        $this->assertFalse($this->fallback('request')->consumerIsGone());
    }

    /** Asked by a page, it does not move the minute's answer the end of requests reads. */
    public function testAskingWhetherTheConsumerIsGoneWritesNoStamp(): void
    {
        $this->fallback('worker')->consumerIsGone();

        $this->assertSame([], glob($this->lockDir . '/cache/*') ?: []);
    }

    private function fallback(string $mode): QueueFallback
    {
        putenv(QueueSettings::ENV_MODE . '=' . $mode);

        return new QueueFallback(
            new QueueSettings(new ParameterBag([]), static fn (): array => []),
            $this->statistics,
            $this->ticker(),
            new HibernationService(new ParameterBag([])),
            $this->locks,
            $this->clock,
        );
    }

    /** The Scheduler over this test's Journal, its one task due a day after its last dispatch. */
    private function ticker(): QueueScheduleTicker
    {
        $daily = new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [RecurringMessage::every(86400, new QueueTestTask())];
            }
        };

        return new QueueScheduleTicker([$daily], $this->createStub(QueueBus::class), new ScheduleHistory($this->db, $this->journal), $this->recorder($this->clock), $this->locks, $this->clock, new HibernationService(new ParameterBag([])));
    }

    private function recorder(MockClock $clock): QueueRecorder
    {
        return new QueueRecorder($this->db, new QueueTaskRows($this->db, $this->schema, $clock), $this->journal, $clock);
    }

    /** A message available since $seconds ago. */
    private function messageWaitingSince(int $seconds): void
    {
        $this->clock->modify("-{$seconds} seconds");
        $this->transport->send(new Envelope(new QueueTestMessage('m')));
        $this->clock->modify("+{$seconds} seconds");
    }

    /** The task was dispatched long ago, and has been due for $seconds. */
    private function scheduleDueSince(int $seconds): void
    {
        $this->recorder(new MockClock($this->at("-{$seconds} seconds -1 day"), 'UTC'))->taskDispatched(new QueueTestTask());
    }

    private function pass(
        QueueConsumerMode $mode,
        string $startedAt,
        string $lastSeenAt,
        string $stopReason = 'empty',
        ?QueuePassOrigin $origin = null,
        ?QueueConsumerMode $fallbackFor = null,
        ?string $holder = null,
    ): void {
        QueueTestKit::recordPass($this->db, $mode, $this->at($startedAt), $this->at($lastSeenAt), $stopReason, origin: $origin, fallbackFor: $fallbackFor, holder: $holder);
    }

    private function claimed(string $deliveredAt, QueueConsumerMode $mode, string $token): void
    {
        $this->db->query(
            "INSERT INTO {$this->schema->table()} (queue_name, message_type, body, headers, created_at, available_at, delivered_at, delivered_by, delivered_mode, delivered_limit, attempts)"
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            ['default', QueueTestMessage::class, '{}', '{}', $this->at('-1 days'), $this->at('-1 days'), $this->at($deliveredAt), $token, $mode->value, 21600, 0]
        );
    }

    private function setWikiStatus(string $status): void
    {
        $configuration = new ConfigurationService();
        $config = $configuration->getConfiguration($this->configFile);
        $config->load();
        $config['wiki_status'] = $status;
        $this->assertTrue($configuration->write($config));
    }

    private function at(string $relative): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->modify($relative)->format('Y-m-d H:i:s');
    }
}
