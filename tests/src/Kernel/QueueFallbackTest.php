<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use YesWiki\Kernel\Command\QueueConsumeCommand;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\Queue\QueueFallback;
use YesWiki\Kernel\Service\Queue\QueueHolderLock;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTestHolders.php';
require_once 'tests/src/Kernel/QueueTestConsole.php';

/**
 * When the end of requests consumes for the consumer the admin chose: a rule with no memory, read from the pass rows and the passes' locks.
 * In `api` or `worker`, the wiki awake, that consumer not seen for longer than its mode's delay.
 */
#[CoversClass(QueueFallback::class)]
class QueueFallbackTest extends YesWikiTestCase
{
    /** The `queue_consumer` the test runs under; the settings read it as the parameter the environment resolved. */
    private string $mode = '';

    private const START = '2026-10-29 12:00:00';

    private MockClock $clock;
    private QueueTestKit $kit;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new \DateTimeImmutable(self::START));
        $this->kit = new QueueTestKit(clock: $this->clock);
    }

    protected function tearDown(): void
    {
        $this->kit->dispose();
    }

    public function testAWorkerUnseenForTenMinutesHasTheRequestsConsume(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-2 hours', '-601 seconds');

        $this->assertTrue($this->fallback('worker')->shouldConsume());
    }

    public function testAWorkerSeenWithinItsDelayIsLeftAlone(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-2 hours', '-600 seconds');

        $this->assertFalse($this->fallback('worker')->shouldConsume());
        $this->assertNull($this->fallback('worker')->status());
    }

    /** It does not beat inside one long message (a backup): the lock its pass holds shows it at work, however old its last beat. */
    public function testAWorkerAtWorkInALongMessageIsSeen(): void
    {
        $holder = QueueHolderLock::newToken();
        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-2 hours', stopReason: '', holder: $holder);
        $letGo = QueueTestHolders::lockHeldByAnotherProcess($this->kit->lockDir . '/' . QueueHolderLock::pathOf($holder));

        try {
            $this->assertFalse($this->fallback('worker')->shouldConsume());
            $this->assertNull($this->fallback('worker')->status());
        } finally {
            $this->assertSame(0, $letGo());
        }
    }

    /** A pass still open whose lock nobody holds, or that has no lock file, is only as recent as its last beat. */
    public function testAnOpenPassWhoseLockIsNotHeldIsSeenWhenItLastBeat(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-2 hours', stopReason: '', holder: QueueTestHolders::gone($this->kit->lockDir));
        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-1 hour', stopReason: '', holder: QueueTestHolders::token());

        $status = $this->fallback('worker')->status();

        $this->assertNotNull($status);
        $this->assertSame($this->at('-1 hour'), $status->lastSeen?->format('Y-m-d H:i:s'));
    }

    public function testAConsumerNeverSeenHasTheRequestsConsumeAtOnce(): void
    {
        $fallback = $this->fallback('worker');

        $this->assertTrue($fallback->shouldConsume(), 'a worker that never ran is not waited for');
        $status = $fallback->status();
        $this->assertNotNull($status);
        $this->assertSame(QueueConsumerMode::Worker, $status->mode);
        $this->assertNull($status->lastSeen);
        $this->assertNull($status->since, 'never seen, it was never given a delay that ran out');
    }

    public function testNoFallbackWhileTheWikiIsHibernated(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-2 hours', '-1 hour');
        $this->setWikiStatus('hibernate');

        $this->assertFalse($this->fallback('worker')->shouldConsume());
        $this->assertNull($this->fallback('worker')->status());
        $this->assertSame(QueueConsumerMode::Worker, $this->fallback('worker')->consumingMode());
    }

    public function testAnApiConsumerUnseenForThreeHoursHasTheRequestsConsume(): void
    {
        $this->pass(QueueConsumerMode::Api, '-3 hours -1 second', '-3 hours -1 second');

        $this->assertTrue($this->fallback('api')->shouldConsume());
    }

    public function testAnApiConsumerSeenWithinThreeHoursIsLeftAlone(): void
    {
        $this->pass(QueueConsumerMode::Api, '-2 hours', '-2 hours');

        $this->assertFalse($this->fallback('api')->shouldConsume());
    }

    public function testAPassOfAnotherModeDoesNotMakeTheConsumerSeen(): void
    {
        $this->pass(QueueConsumerMode::Api, '-1 minute', '-1 minute');
        $this->pass(QueueConsumerMode::Request, '-1 minute', '-1 minute');

        $this->assertTrue($this->fallback('worker')->shouldConsume());
    }

    /** Run by hand, for the request that pinned a message, or for the fallback itself: none of them is the worker, all are `request`. */
    public function testPassesTheConsumerDidNotStartDoNotMakeItSeen(): void
    {
        $this->mode = 'worker';
        $settings = QueueTestKit::settings($this->mode);
        $pinned = $this->kit->send('pinned');
        $this->kit->send('waiting');

        $this->command(['--message' => (string)$pinned]);
        $this->command(['--drain' => true]);
        $this->kit->send('for the fallback');
        $this->kit->requestTrigger($settings)->afterResponse();

        $this->assertSame(['pinned', 'waiting', 'for the fallback'], $this->kit->handled);
        $this->assertSame(['request', 'request', 'request'], array_column($this->kit->statistics->recentPasses(10), 'mode'));
        $this->assertNull($this->kit->statistics->lastSeenAt(QueueConsumerMode::Worker));
        $this->assertTrue($this->fallback('worker')->shouldConsume(), 'the worker is still missing');
    }

    public function testRequestNeverFallsBack(): void
    {
        $this->assertFalse($this->fallback('request')->shouldConsume());
        $this->assertNull($this->fallback('request')->status());
    }

    public function testTheFallbackEndsOnceTheConsumerIsSeenAgain(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-3 hours', '-3 hours');
        $fallback = $this->fallback('worker');
        $this->assertTrue($fallback->shouldConsume());

        $this->pass(QueueConsumerMode::Worker, '-1 minute', '-1 minute');
        $this->clock->sleep(61);
        $fallback->startNewRequest();

        $this->assertFalse($fallback->shouldConsume());
        $this->assertNull($fallback->status());
    }

    /** What the alert says: which mode, when it was last seen, and since when the requests consume, which is its delay later. */
    public function testTheStatusSaysWhenTheConsumerWasLastSeenAndSinceWhenTheRequestsConsume(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-5 hours', '-3 hours');

        $status = $this->fallback('worker')->status();

        $this->assertNotNull($status);
        $this->assertSame(QueueConsumerMode::Worker, $status->mode);
        $this->assertSame($this->at('-3 hours'), $status->lastSeen?->format('Y-m-d H:i:s'));
        $this->assertSame($this->at('-3 hours +600 seconds'), $status->since?->format('Y-m-d H:i:s'));
    }

    /** The answer kept between two checks is the mode's it was given for: a worker's fallback is not carried over to the cron the admin just set up. */
    public function testAModeChangeDoesNotCarryTheFallbackOver(): void
    {
        $this->assertTrue($this->fallback('worker')->shouldConsume());
        $this->pass(QueueConsumerMode::Api, '-1 minute', '-1 minute');

        $this->clock->sleep(10);

        $this->assertFalse($this->fallback('api')->shouldConsume());
    }

    /** The answer is kept for the request, and given anew to the next one: the instance a long-lived worker keeps serves many. */
    public function testTheStatusIsReadOncePerRequest(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-1 minute', '-1 minute');
        $fallback = $this->fallback('worker');
        $this->assertNull($fallback->status());

        $this->clock->sleep(600);
        $this->assertNull($fallback->status(), 'within the request, the first answer stands');

        $fallback->startNewRequest();

        $this->assertNotNull($fallback->status());
    }

    /** The mode that consumes is the end of requests while a fallback runs, the chosen one otherwise. */
    public function testTheConsumingModeIsTheEndOfRequestsWhileAFallbackRuns(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-1 minute', '-1 minute');
        $this->assertSame(QueueConsumerMode::Worker, $this->fallback('worker')->consumingMode());
        $this->assertSame(QueueConsumerMode::Request, $this->fallback('request')->consumingMode());

        $this->clock->sleep(600);

        $this->assertSame(QueueConsumerMode::Request, $this->fallback('worker')->consumingMode());
    }

    /** A wiki whose queue table is missing is told so once a minute, not on every request. */
    public function testAReadThatFailsIsTriedAtMostOnceAMinute(): void
    {
        $this->kit->dropTables();
        $fallback = $this->fallback('worker');
        $this->assertTrue($this->throws($fallback), 'the missing table is not hidden');

        $this->clock->sleep(30);
        $fallback->startNewRequest();

        $this->assertFalse($fallback->shouldConsume());

        $this->clock->sleep(31);
        $fallback->startNewRequest();

        $this->assertTrue($this->throws($fallback), 'a minute on, it is tried again');
    }

    /** Between two checks a request reads nothing: the answer of the last one stands. */
    public function testTheRuleIsReadAtMostOnceAMinute(): void
    {
        $this->pass(QueueConsumerMode::Worker, '-2 hours', '-9 minutes -30 seconds');
        $fallback = $this->fallback('worker');
        $this->assertFalse($fallback->shouldConsume());

        $this->clock->sleep(40);
        $fallback->startNewRequest();

        $this->assertFalse($fallback->shouldConsume(), 'the worker is now over ten minutes gone, but 40 s after the check its answer stands');

        $this->clock->sleep(21);
        $fallback->startNewRequest();

        $this->assertTrue($fallback->shouldConsume(), 'a minute on, the rule is read again');
    }

    /** The rule keeps nothing but the minute's two stamps: when it was last read, and what it answered for that mode. */
    public function testTheMinutesAnswerIsTheOnlyStateTheRuleKeeps(): void
    {
        $this->fallback('worker')->status();
        $this->assertSame([], $this->stamps(), 'a page that asks writes nothing');

        $this->fallback('worker')->shouldConsume();

        $this->assertSame([sprintf(QueueFallback::ACTIVE_STAMP, 'worker'), QueueFallback::CHECK_STAMP], $this->stamps());
    }

    private function throws(QueueFallback $fallback): bool
    {
        try {
            $fallback->shouldConsume();
        } catch (\Throwable) {
            return true;
        }

        return false;
    }

    private function fallback(string $mode): QueueFallback
    {
        $this->mode = $mode;

        return $this->kit->fallback(QueueTestKit::settings($this->mode));
    }

    /**
     * Runs `messenger:consume` with those options over the kit's queue.
     *
     * @param array<string, string|bool> $options
     */
    private function command(array $options): void
    {
        $application = new Application();
        $application->add(new QueueConsumeCommand($this->kit->services(QueueTestKit::settings($this->mode))));
        $tester = new CommandTester($application->find('messenger:consume'));
        $tester->execute(['command' => 'messenger:consume'] + $options);
        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
    }

    private function pass(QueueConsumerMode $mode, string $startedAt, string $lastSeenAt, string $stopReason = 'empty', ?string $holder = null): void
    {
        QueueTestKit::recordPass($this->kit->db, $mode, $this->at($startedAt), $this->at($lastSeenAt), $stopReason, holder: $holder);
    }

    private function setWikiStatus(string $status): void
    {
        $configuration = new ConfigurationService();
        $config = $configuration->getConfiguration($this->kit->configFile());
        $config->load();
        $config['wiki_status'] = $status;
        $this->assertTrue($configuration->write($config));
    }

    /** @return list<string> the files in the kit's cache/, as paths relative to the instance */
    private function stamps(): array
    {
        $stamps = array_map(fn (string $path): string => substr($path, strlen($this->kit->lockDir) + 1), glob($this->kit->lockDir . '/cache/*') ?: []);
        sort($stamps);

        return $stamps;
    }

    private function at(string $relative): string
    {
        return $this->clock->now()->modify($relative)->format('Y-m-d H:i:s');
    }
}
