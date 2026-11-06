<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use YesWiki\Kernel\Queue\QueuePassLimits;
use YesWiki\Kernel\Service\Queue\QueuePassLimitsFactory;

/** What one pass may spend: memory, the kill limit of its claim and the instant PHP's own limit makes it stop taking messages. */
#[CoversClass(QueuePassLimits::class)]
#[CoversClass(QueuePassLimitsFactory::class)]
class QueuePassLimitsTest extends TestCase
{
    private const NOW = 1_000_000.0;

    /**
     * @param array<string, string> $ini
     * @param float                 $elapsed seconds since the request started
     */
    private function factory(array $ini, float $elapsed = 0.0): QueuePassLimitsFactory
    {
        return new QueuePassLimitsFactory(new MockClock(new \DateTimeImmutable('@' . self::NOW)), static fn (string $name) => $ini[$name] ?? false, self::NOW - $elapsed);
    }

    /**
     * On the real clock, as phpStopsAt is an instant of microtime().
     *
     * @param array<string, string> $ini
     */
    private function nativeFactory(array $ini, float $elapsed = 0.0): QueuePassLimitsFactory
    {
        return new QueuePassLimitsFactory(new NativeClock(), static fn (string $name) => $ini[$name] ?? false, microtime(true) - $elapsed);
    }

    public function testMemoryCeilingIsEightyPercent(): void
    {
        $limits = $this->factory(['max_execution_time' => '30', 'memory_limit' => '128M'])->forPass();

        $this->assertSame(107374182, $limits->memoryCeilingBytes);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function memoryLimits(): iterable
    {
        yield 'bytes' => ['1000', 800];
        yield 'kilobytes' => ['512K', 419430];
        yield 'lowercase megabytes' => ['64m', 53687091];
        yield 'gigabytes' => ['2G', 1717986918];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('memoryLimits')]
    public function testMemoryLimitShorthandIsUnderstood(string $limit, int $ceiling): void
    {
        $limits = $this->factory(['max_execution_time' => '30', 'memory_limit' => $limit])->forPass();

        $this->assertSame($ceiling, $limits->memoryCeilingBytes);
    }

    /** -1 means PHP imposes nothing: the pass is not stopped on memory, the host's own kill is what it is left with. */
    public function testNoMemoryLimitMeansNoMemoryCeiling(): void
    {
        $limits = $this->factory(['max_execution_time' => '30', 'memory_limit' => '-1'])->forPass();

        $this->assertSame(PHP_INT_MAX, $limits->memoryCeilingBytes);
        $this->assertFalse($limits->memoryExhausted());
    }

    public function testMemoryIsExhaustedPastTheCeiling(): void
    {
        $this->assertTrue((new QueuePassLimits(1, 60))->memoryExhausted());
        $this->assertFalse((new QueuePassLimits(PHP_INT_MAX, 60))->memoryExhausted());
    }

    public function testKillLimitIsTheTimeLeftBeforePhpStops(): void
    {
        $limits = $this->factory(['max_execution_time' => '30', 'memory_limit' => '128M'], 4.0)->forPass();

        $this->assertSame(26, $limits->killLimitSeconds);
    }

    public function testKillLimitWithoutAPhpLimitIsOneHour(): void
    {
        $limits = $this->factory(['max_execution_time' => '0', 'memory_limit' => '128M'])->forPass();

        $this->assertSame(3600, $limits->killLimitSeconds);
    }

    /** The limit PHP gives less the margin (2 s + 10 %), counted from where the request already is. */
    public function testPhpStopsAtIsTheLimitLessTheMargin(): void
    {
        $limits = $this->nativeFactory(['max_execution_time' => '30', 'memory_limit' => '128M'], 4.0)->forPass();

        $this->assertEqualsWithDelta(microtime(true) + 30 - 4 - (2 + 3), $limits->phpStopsAt, 0.5);
        $this->assertFalse($limits->phpLimitReached());
    }

    public function testNoPhpLimitMeansNoPhpStop(): void
    {
        $limits = $this->nativeFactory(['max_execution_time' => '0', 'memory_limit' => '128M'], 10.0)->forPass();

        $this->assertNull($limits->phpStopsAt);
        $this->assertFalse($limits->phpLimitReached());
    }

    public function testAPageThatUsedItsWholeLimitStopsAtOnce(): void
    {
        $limits = $this->nativeFactory(['max_execution_time' => '30', 'memory_limit' => '128M'], 29.0)->forPass();

        $this->assertTrue($limits->phpLimitReached());
    }

    /** The jobs page's button: no memory ceiling, only PHP's own limit (or one hour without) for the claim. */
    public function testUntilEmptyHasNoMemoryCeilingAndPhpsKillLimit(): void
    {
        $limits = $this->factory(['max_execution_time' => '30', 'memory_limit' => '128M'], 4.0)->untilEmpty();

        $this->assertSame(PHP_INT_MAX, $limits->memoryCeilingBytes);
        $this->assertFalse($limits->memoryExhausted());
        $this->assertSame(26, $limits->killLimitSeconds);

        $this->assertSame(3600, $this->factory(['max_execution_time' => '0', 'memory_limit' => '128M'], 4.0)->untilEmpty()->killLimitSeconds);
    }

    public function testLiftedDropsThePhpStopAndKeepsTheMemoryCeiling(): void
    {
        $limits = $this->nativeFactory(['max_execution_time' => '30', 'memory_limit' => '128M'], 4.0)->forPass();
        $lifted = $limits->lifted(3600);

        $this->assertNotNull($limits->phpStopsAt);
        $this->assertNull($lifted->phpStopsAt);
        $this->assertSame(3600, $lifted->killLimitSeconds);
        $this->assertSame($limits->memoryCeilingBytes, $lifted->memoryCeilingBytes);
        $this->assertFalse($lifted->phpLimitReached());
    }

    /** A pass nobody waits for lifts PHP's limit, then asks again what limit holds: none left means the one-hour ceiling. */
    public function testLiftingTheLimitAnswersTheHoursCeiling(): void
    {
        $ini = ['max_execution_time' => '30', 'memory_limit' => '128M'];
        $asked = [];
        $factory = new QueuePassLimitsFactory(
            new MockClock(new \DateTimeImmutable('@' . self::NOW)),
            static function (string $name) use (&$ini) {
                return $ini[$name] ?? false;
            },
            self::NOW,
            static function (int $seconds) use (&$ini, &$asked): bool {
                $asked[] = $seconds;
                $ini['max_execution_time'] = (string)$seconds;

                return true;
            }
        );

        $this->assertSame(QueuePassLimitsFactory::KILL_LIMIT_NO_PHP_LIMIT, $factory->liftPhpLimit());
        $this->assertSame([0], $asked);
    }

    /** A limit the host imposes (`php_admin_value`) ignores set_time_limit(), and the claim keeps what is left of it. */
    public function testALimitTheHostImposesIsKept(): void
    {
        $factory = new QueuePassLimitsFactory(
            new MockClock(new \DateTimeImmutable('@' . self::NOW)),
            static fn (string $name) => $name === 'max_execution_time' ? '30' : false,
            self::NOW,
            static fn (int $seconds): bool => false
        );

        $this->assertNull($factory->liftPhpLimit());
    }

    public function testBytesReadsTheSizeNotationOfPhpIni(): void
    {
        $this->assertSame(128 * 1024 ** 2, QueuePassLimitsFactory::bytes('128M'));
        $this->assertSame(512 * 1024, QueuePassLimitsFactory::bytes('512k'));
        $this->assertSame(1024 ** 3, QueuePassLimitsFactory::bytes('1G'));
        $this->assertSame(2048, QueuePassLimitsFactory::bytes('2048'));
        $this->assertSame(0, QueuePassLimitsFactory::bytes('-1'));
        $this->assertSame(0, QueuePassLimitsFactory::bytes(''));
    }

    /** With no start injected, a request is measured from the one PHP recorded. */
    public function testTheRequestStartDefaultsToWhatPhpRecorded(): void
    {
        $before = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 25.0;
        try {
            $limits = (new QueuePassLimitsFactory(new NativeClock(), static fn (string $name) => ['max_execution_time' => '30', 'memory_limit' => '128M'][$name]))
                ->forPass();
        } finally {
            if ($before === null) {
                unset($_SERVER['REQUEST_TIME_FLOAT']);
            } else {
                $_SERVER['REQUEST_TIME_FLOAT'] = $before;
            }
        }

        // 30 - 25 - (2 + 3) = 0: the render used what the margin leaves out
        $this->assertTrue($limits->phpLimitReached());
    }

    /** A request that has used more than PHP gives it still hands its pass a kill limit of one second, not zero (which would mean no limit). */
    public function testKillLimitNeverDropsBelowOneSecond(): void
    {
        $over = $this->factory(['max_execution_time' => '30', 'memory_limit' => '128M'], 35.0)->forPass();
        $this->assertSame(1, $over->killLimitSeconds);

        $almost = $this->factory(['max_execution_time' => '30', 'memory_limit' => '128M'], 29.5)->forPass();
        $this->assertSame(1, $almost->killLimitSeconds);
    }

    /** A host that hides PHP's limits: nothing bounds the pass but the queue being empty. */
    public function testUnreadableLimitsMeanNoPhpLimit(): void
    {
        $limits = $this->factory([])->forPass();

        $this->assertSame(QueuePassLimitsFactory::KILL_LIMIT_NO_PHP_LIMIT, $limits->killLimitSeconds);
        $this->assertSame(PHP_INT_MAX, $limits->memoryCeilingBytes);
        $this->assertNull($limits->phpStopsAt);
    }
}
