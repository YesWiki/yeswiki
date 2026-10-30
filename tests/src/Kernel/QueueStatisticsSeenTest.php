<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTestMessage.php';
require_once 'tests/src/Kernel/QueueTransportTest.php';

/** When the consumer the admin chose was last seen at work, and since when the end of requests has been consuming in its place. */
#[CoversClass(QueueStatistics::class)]
class QueueStatisticsSeenTest extends YesWikiTestCase
{
    private const START = '2026-10-29 12:00:00';

    /** A holder on a machine other than this one: its process can be neither seen alive nor seen gone, so its claim lives until its limit. */
    private const ELSEWHERE = 'c4242@abcdef012345.777/';

    private string $file;
    private MockClock $clock;
    private DbService $db;
    private QueueSchema $schema;
    private QueueStatistics $statistics;

    protected function setUp(): void
    {
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
        $journal = new JournalSchema($this->db);
        $journal->create();
        $this->statistics = new QueueStatistics($this->db, $this->schema, $journal, new QueueTransport($this->db, $this->schema, clock: $this->clock), $this->clock);
    }

    protected function tearDown(): void
    {
        unset($this->statistics, $this->schema, $this->db);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
    }

    public function testAConsumerPassIsSeen(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-1 hour', '-2 minutes');

        $this->assertSame($this->at('-2 minutes'), $this->format($this->statistics->lastSeenAt(QueueConsumerMode::Worker)));
    }

    public function testPinnedManualAndFallbackPassesAreNotSeen(): void
    {
        $this->pass(QueueConsumerMode::Command, '-4 hours', '-4 hours');
        $this->pass(QueueConsumerMode::Command, '-1 minute', '-1 minute', origin: QueuePassOrigin::Pinned);
        $this->pass(QueueConsumerMode::Command, '-1 minute', '-1 minute', origin: QueuePassOrigin::Manual);
        $this->pass(QueueConsumerMode::Command, '-1 minute', '-1 minute', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Command);

        $this->assertSame($this->at('-4 hours'), $this->format($this->statistics->lastSeenAt(QueueConsumerMode::Command)));
    }

    public function testAnotherModesPassIsNotSeen(): void
    {
        $this->pass(QueueConsumerMode::Command, '-1 minute', '-1 minute');

        $this->assertNull($this->statistics->lastSeenAt(QueueConsumerMode::Worker));
    }

    /** A worker inside one long message does not beat: the claim its own process holds is what shows it at work. */
    public function testALiveClaimOfTheConsumerIsSeenNow(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-30 minutes', stopReason: '', holder: self::ELSEWHERE . 'aa');
        $this->claimed('-30 minutes', QueueConsumerMode::Worker, self::ELSEWHERE . 'bb');

        $this->assertSame(self::START, $this->format($this->statistics->lastSeenAt(QueueConsumerMode::Worker)));
    }

    /** A backup an admin started runs in a web process under the configured mode: its claim is not the worker's. */
    public function testAPinnedClaimIsNotSeen(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-30 minutes', '-30 minutes', stopReason: '', origin: QueuePassOrigin::Pinned, holder: self::ELSEWHERE . 'aa');
        $this->claimed('-30 minutes', QueueConsumerMode::Worker, self::ELSEWHERE . 'bb');

        $this->assertNull($this->statistics->lastSeenAt(QueueConsumerMode::Worker));
    }

    public function testFallbackSinceIsTheFirstFallbackPassAfterTheConsumerWasLastSeen(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-3 hours');
        $this->pass(QueueConsumerMode::Request, '-2 hours', '-2 hours', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Worker);
        $this->pass(QueueConsumerMode::Request, '-1 hour', '-1 hour', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Worker);
        $this->pass(QueueConsumerMode::Request, '-90 minutes', '-90 minutes', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Command);

        $this->assertSame($this->at('-2 hours'), $this->format($this->statistics->fallbackSince(QueueConsumerMode::Worker)));

        $this->pass(QueueConsumerMode::Worker, '-10 minutes', '-10 minutes');

        $this->assertNull($this->statistics->fallbackSince(QueueConsumerMode::Worker), 'the worker came back after the last fallback pass');
    }

    public function testFallbackSinceWhenTheConsumerWasNeverSeen(): void
    {
        $this->pass(QueueConsumerMode::Request, '-2 hours', '-2 hours', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Api);

        $this->assertSame($this->at('-2 hours'), $this->format($this->statistics->fallbackSince(QueueConsumerMode::Api)));
    }

    public function testRecentPassesSayWhatStartedThem(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-3 hours');
        $this->pass(QueueConsumerMode::Request, '-1 hour', '-1 hour', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Worker);

        [$fallback, $consumer] = $this->statistics->recentPasses(2);

        $this->assertSame('fallback', $fallback['origin']);
        $this->assertSame('worker', $fallback['fallbackFor']);
        $this->assertSame('consumer', $consumer['origin']);
        $this->assertNull($consumer['fallbackFor']);
    }

    /**
     * MySQL keeps the context as JSON and gives it back reformatted, PostgreSQL as JSONB: the origin is read as a field, never matched as text.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testTheOriginIsReadOnEveryServer(array $params): void
    {
        if (!QueueTransportTest::reachable($params)) {
            $this->markTestSkipped("no {$params['db_driver']} server at {$params['db_host']}");
        }
        $db = new DbService(new ParameterBag($params));
        $schema = new QueueSchema($db);
        $journal = new JournalSchema($db);
        foreach ([$schema, $journal] as $tables) {
            $tables->drop();
            $tables->create();
        }

        try {
            $statistics = new QueueStatistics($db, $schema, $journal, new QueueTransport($db, $schema, clock: $this->clock), $this->clock);
            $record = fn (QueueConsumerMode $mode, string $at, ?QueuePassOrigin $origin = null, ?QueueConsumerMode $fallbackFor = null) => QueueTestKit::recordPass($db, $mode, $this->at($at), $this->at($at), 'empty', origin: $origin, fallbackFor: $fallbackFor);
            $record(QueueConsumerMode::Command, '-4 hours');
            $record(QueueConsumerMode::Command, '-1 minute', QueuePassOrigin::Pinned);
            $record(QueueConsumerMode::Request, '-2 hours', QueuePassOrigin::Fallback, QueueConsumerMode::Command);

            $this->assertSame($this->at('-4 hours'), $this->format($statistics->lastSeenAt(QueueConsumerMode::Command)), 'a pinned pass is not the cron');
            $this->assertSame($this->at('-2 hours'), $this->format($statistics->fallbackSince(QueueConsumerMode::Command)));
        } finally {
            $journal->drop();
            $schema->drop();
        }
    }

    /** @return array<string, array{array<string, string|bool>}> */
    public static function servers(): array
    {
        return QueueTransportTest::realServers();
    }

    /** A fallback pass in the minute after the consumer came back, before the next check saw it, is a leftover: it does not start a fallback again. */
    public function testAFallbackPassTooSoonAfterTheConsumerWasSeenIsALeftover(): void
    {
        $this->pass(QueueConsumerMode::Command, '-5 hours', '-5 hours');
        $this->pass(QueueConsumerMode::Request, '-2 hours', '-2 hours', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Command);
        $this->pass(QueueConsumerMode::Command, '-90 seconds', '-90 seconds');
        $this->pass(QueueConsumerMode::Request, '-60 seconds', '-60 seconds', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::Command);

        $this->assertNull($this->statistics->fallbackSince(QueueConsumerMode::Command, 3 * 3600));
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

    private function at(string $relative): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->modify($relative)->format('Y-m-d H:i:s');
    }

    private function format(?\DateTimeImmutable $time): ?string
    {
        return $time?->format('Y-m-d H:i:s');
    }
}
