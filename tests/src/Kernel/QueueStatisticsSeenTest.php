<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Queue\QueueHolderLock;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTestHolders.php';
require_once 'tests/src/Kernel/QueueTransportTest.php';

/**
 * When a consumer was last seen at work: now while a pass of its mode is open and its process holds its lock, its passes' last sign of life otherwise.
 * A pass the consumer did not start itself is written as `request`, so only the consumer's own passes are `api` or `worker`.
 */
#[CoversClass(QueueStatistics::class)]
class QueueStatisticsSeenTest extends YesWikiTestCase
{
    private const START = '2026-10-29 12:00:00';

    private string $file;
    private string $lockDir;
    private MockClock $clock;
    private DbService $db;
    private QueueSchema $schema;
    private QueueStatistics $statistics;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-queue-') . '.db';
        $this->lockDir = sys_get_temp_dir() . '/yw-seen-locks-' . bin2hex(random_bytes(4));
        $this->clock = new MockClock(new \DateTimeImmutable(self::START));
        $this->db = new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywq_',
            'debug' => false,
        ]));
        $this->schema = new QueueSchema($this->db);
        $this->schema->create();
        $this->statistics = $this->statisticsOn($this->db, $this->schema);
    }

    protected function tearDown(): void
    {
        unset($this->statistics, $this->schema, $this->db);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
        foreach (glob($this->lockDir . '/' . QueueHolderLock::DIRECTORY . '/*') ?: [] as $holder) {
            @unlink($holder);
        }
        @rmdir($this->lockDir . '/' . QueueHolderLock::DIRECTORY);
        @rmdir($this->lockDir . '/cache');
        @rmdir($this->lockDir);
    }

    public function testAConsumerPassIsSeenWhenItLastBeat(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-1 hour', '-2 minutes');
        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-2 hours');

        $this->assertSame($this->at('-2 minutes'), $this->format($this->statistics->lastSeenAt(QueueConsumerMode::Worker)));
    }

    public function testAnotherModesPassIsNotSeen(): void
    {
        $this->pass(QueueConsumerMode::Api, '-1 minute', '-1 minute');
        $this->pass(QueueConsumerMode::Request, '-1 minute', '-1 minute');

        $this->assertNull($this->statistics->lastSeenAt(QueueConsumerMode::Worker));
    }

    /** A worker inside one long message does not beat: the lock its process holds on its pass is what shows it at work. */
    public function testAnOpenPassWhoseLockIsHeldIsAtWorkNow(): void
    {
        $holder = QueueHolderLock::newToken();
        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-2 hours', stopReason: '', holder: $holder);
        $letGo = QueueTestHolders::lockHeldByAnotherProcess($this->lockDir . '/' . QueueHolderLock::pathOf($holder));

        try {
            $this->assertTrue($this->statistics->atWork(QueueConsumerMode::Worker));
            $this->assertTrue($this->statistics->atWork());
            $this->assertFalse($this->statistics->atWork(QueueConsumerMode::Api), 'its pass is the worker\'s');
            $this->assertSame(self::START, $this->format($this->statistics->lastSeenAt(QueueConsumerMode::Worker)));
            $this->assertSame(self::START, $this->format($this->statistics->lastActivityAt()));
        } finally {
            $this->assertSame(0, $letGo());
        }
    }

    /** A pass whose lock nobody holds is gone, and one with no lock file says nothing: either is only as recent as its last beat. */
    public function testAnOpenPassWhoseLockIsNotHeldIsNotAtWork(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-2 hours', stopReason: '', holder: QueueTestHolders::gone($this->lockDir));
        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-90 minutes', stopReason: '', holder: QueueTestHolders::token());
        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-1 hour', stopReason: '');

        $this->assertFalse($this->statistics->atWork());
        $this->assertSame($this->at('-1 hour'), $this->format($this->statistics->lastSeenAt(QueueConsumerMode::Worker)));
        $this->assertSame($this->at('-1 hour'), $this->format($this->statistics->lastActivityAt()));
    }

    /** Only a pass still open can be at work: one that has ended is its last sign of life, whoever holds the file of its token. */
    public function testAPassThatHasEndedIsNotAtWork(): void
    {
        $holder = QueueHolderLock::newToken();
        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-1 hour', holder: $holder);
        $letGo = QueueTestHolders::lockHeldByAnotherProcess($this->lockDir . '/' . QueueHolderLock::pathOf($holder));

        try {
            $this->assertFalse($this->statistics->atWork(QueueConsumerMode::Worker));
            $this->assertSame($this->at('-1 hour'), $this->format($this->statistics->lastSeenAt(QueueConsumerMode::Worker)));
        } finally {
            $this->assertSame(0, $letGo());
        }
    }

    public function testTheLastActivityIsTheLatestPassOfAnyMode(): void
    {
        $this->assertNull($this->statistics->lastActivityAt(), 'no pass ever ran');

        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-2 hours');
        $this->pass(QueueConsumerMode::Request, '-30 minutes', '-29 minutes');

        $this->assertSame($this->at('-29 minutes'), $this->format($this->statistics->lastActivityAt()));
    }

    /**
     * MySQL gives the context back reformatted, PostgreSQL keeps it as JSONB: the mode is the row's `actor`, the holder a field of its context.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testTheConsumerIsSeenOnEveryServer(array $params): void
    {
        if (!QueueTransportTest::reachable($params)) {
            $this->markTestSkipped("no {$params['db_driver']} server at {$params['db_host']}");
        }
        $db = new DbService(new ParameterBag($params));
        $schema = new QueueSchema($db);
        $schema->drop();
        $schema->create();

        try {
            $statistics = $this->statisticsOn($db, $schema);
            $holder = QueueHolderLock::newToken();
            QueueTestKit::recordPass($db, QueueConsumerMode::Api, $this->at('-4 hours'), $this->at('-4 hours'), 'empty');
            QueueTestKit::recordPass($db, QueueConsumerMode::Request, $this->at('-1 minute'), $this->at('-1 minute'), 'empty');
            QueueTestKit::recordPass($db, QueueConsumerMode::Worker, $this->at('-2 hours'), $this->at('-2 hours'), '', holder: $holder);

            $this->assertSame($this->at('-4 hours'), $this->format($statistics->lastSeenAt(QueueConsumerMode::Api)), 'a `request` pass is not the cron');
            $this->assertSame($this->at('-2 hours'), $this->format($statistics->lastSeenAt(QueueConsumerMode::Worker)));
            $this->assertSame($this->at('-1 minute'), $this->format($statistics->lastActivityAt()));

            $letGo = QueueTestHolders::lockHeldByAnotherProcess($this->lockDir . '/' . QueueHolderLock::pathOf($holder));
            try {
                $this->assertTrue($statistics->atWork(QueueConsumerMode::Worker));
                $this->assertSame(self::START, $this->format($statistics->lastSeenAt(QueueConsumerMode::Worker)));
            } finally {
                $this->assertSame(0, $letGo());
            }
        } finally {
            $schema->drop();
        }
    }

    /** @return array<string, array{array<string, string|bool>}> */
    public static function servers(): array
    {
        return QueueTransportTest::realServers();
    }

    /** The statistics over those tables, the holders' locks under this test's own directory. */
    private function statisticsOn(DbService $db, QueueSchema $schema): QueueStatistics
    {
        $transport = new QueueTransport($db, $schema, clock: $this->clock, holderLock: new QueueHolderLock(QueueTestKit::locksIn($this->lockDir)));

        return new QueueStatistics($db, $schema, $transport, $this->clock);
    }

    private function pass(QueueConsumerMode $mode, string $startedAt, string $lastSeenAt, string $stopReason = 'empty', ?string $holder = null): void
    {
        QueueTestKit::recordPass($this->db, $mode, $this->at($startedAt), $this->at($lastSeenAt), $stopReason, holder: $holder);
    }

    private function at(string $relative): string
    {
        return $this->clock->now()->modify($relative)->format('Y-m-d H:i:s');
    }

    private function format(?\DateTimeImmutable $time): ?string
    {
        return $time?->format('Y-m-d H:i:s');
    }
}
