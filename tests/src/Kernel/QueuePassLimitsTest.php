<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use YesWiki\Kernel\Queue\QueuePassLimits;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\Queue\QueuePassLimitsFactory;

/** What one pass may spend: memory, and the instant PHP's own limit makes it stop taking messages. */
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

    #[DataProvider('memoryLimits')]
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
        $this->assertTrue((new QueuePassLimits(1))->memoryExhausted());
        $this->assertFalse((new QueuePassLimits(PHP_INT_MAX))->memoryExhausted());
    }

    /** 80 % of the limit PHP gives, counted from where the request already is. */
    public function testPhpStopsAtIsTheLimitLessTheMargin(): void
    {
        $limits = $this->nativeFactory(['max_execution_time' => '30', 'memory_limit' => '128M'], 4.0)->forPass();

        $this->assertEqualsWithDelta(microtime(true) + 30 * 0.8 - 4, $limits->phpStopsAt, 0.5);
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

    /** @return list<int> each value the climb asked of a host that accepts up to $hostAcceptsUpTo, starting from a limit of $limit */
    private function climb(int $limit, int $hostAcceptsUpTo = PHP_INT_MAX): array
    {
        $asked = [];
        $this->climbing($limit, $hostAcceptsUpTo, $asked)->raisePhpLimit();

        return $asked;
    }

    /**
     * A factory on the real clock whose set_time_limit() takes effect, as PHP's, up to what the host accepts.
     *
     * @param list<int> $asked
     */
    private function climbing(int $limit, int $hostAcceptsUpTo, array &$asked, float $elapsed = 0.0): QueuePassLimitsFactory
    {
        $ini = ['max_execution_time' => (string)$limit, 'memory_limit' => '128M'];

        return new QueuePassLimitsFactory(
            new NativeClock(),
            static function (string $name) use (&$ini) {
                return $ini[$name] ?? false;
            },
            microtime(true) - $elapsed,
            static function (int $seconds) use (&$ini, &$asked, $hostAcceptsUpTo): bool {
                $asked[] = $seconds;
                if ($seconds > $hostAcceptsUpTo) {
                    return false;
                }
                $ini['max_execution_time'] = (string)$seconds;

                return true;
            }
        );
    }

    public function testTheLimitClimbsByDoublingUpToTwentyMinutes(): void
    {
        $this->assertSame([60, 120, 240, 480, 960, 1200], $this->climb(30));
    }

    public function testTheClimbSkipsTheStepsTheLimitAlreadyPassed(): void
    {
        $this->assertSame([480, 960, 1200], $this->climb(300));
    }

    public function testTheClimbStopsAtTheFirstRefusal(): void
    {
        $this->assertSame([60, 120, 240], $this->climb(30, 120));
    }

    /** No limit (the CLI's 0) is never replaced by one, and a limit past the ceiling is never lowered to it. */
    public function testTheClimbLeavesAloneNoLimitAndALimitPastTheCeiling(): void
    {
        $this->assertSame([], $this->climb(0));
        $this->assertSame([], $this->climb(1200));
        $this->assertSame([], $this->climb(3600));
    }

    /** set_time_limit() can say yes and change nothing: only what `max_execution_time` reads back counts. */
    public function testAStepCountsOnlyIfReadBack(): void
    {
        $asked = [];
        $factory = new QueuePassLimitsFactory(
            new NativeClock(),
            static fn (string $name) => $name === 'max_execution_time' ? '30' : false,
            microtime(true),
            static function (int $seconds) use (&$asked): bool {
                $asked[] = $seconds;

                return true;
            }
        );

        $this->assertNull($factory->raisePhpLimit(), 'read back at 30: nothing was accepted');
        $this->assertSame([60], $asked);
    }

    /** set_time_limit() restarts PHP's count: the 80 % run from the last accepted step, whatever the page already used. */
    public function testARaisedLimitIsCountedFromTheRaise(): void
    {
        $asked = [];
        $factory = $this->climbing(30, PHP_INT_MAX, $asked, 29.0);

        $limits = $factory->withPhpLimitRaised($factory->forPass());

        $this->assertEqualsWithDelta(microtime(true) + 1200 * 0.8, $limits->phpStopsAt, 0.5);
        $this->assertFalse($limits->phpLimitReached());
    }

    public function testAPartlyRaisedLimitIsTheLastAcceptedStep(): void
    {
        $asked = [];
        $factory = $this->climbing(30, 120, $asked, 29.0);

        $limits = $factory->withPhpLimitRaised($factory->forPass());

        $this->assertEqualsWithDelta(microtime(true) + 120 * 0.8, $limits->phpStopsAt, 0.5);
    }

    /** A host that refuses every step: nothing restarted PHP's count, so the stop stays counted from the start of the request. */
    public function testALimitTheHostImposesIsKept(): void
    {
        $asked = [];
        $factory = $this->climbing(30, 0, $asked, 4.0);
        $limits = $factory->forPass();

        $raised = $factory->withPhpLimitRaised($limits);

        $this->assertSame($limits->phpStopsAt, $raised->phpStopsAt);
        $this->assertSame($limits->memoryCeilingBytes, $raised->memoryCeilingBytes);
    }

    public function testRaisingKeepsTheMemoryCeilingOfThePass(): void
    {
        $asked = [];
        $factory = $this->climbing(30, PHP_INT_MAX, $asked);

        $this->assertSame(1234, $factory->withPhpLimitRaised(new QueuePassLimits(1234))->memoryCeilingBytes);
    }

    /** The wiki's own ceiling is not the host's refusal: the two stops say so apart, for the `queue-php-limit` alert. */
    #[DataProvider('timeStops')]
    public function testTheStopOnTimeNamesWhoSetTheLimit(int $limit, int $hostAcceptsUpTo, string $reason): void
    {
        $asked = [];
        $factory = $this->climbing($limit, $hostAcceptsUpTo, $asked);

        $this->assertSame($reason, $factory->withPhpLimitRaised($factory->forPass())->timeStopReason());
    }

    /** @return array<string, array{int, int, string}> */
    public static function timeStops(): array
    {
        return [
            'raised to the ceiling' => [30, PHP_INT_MAX, QueuePassReport::TIME_CAP],
            'stopped on a step below it' => [30, 120, QueuePassReport::PHP_LIMIT],
            'refused outright' => [30, 0, QueuePassReport::PHP_LIMIT],
            'already past the ceiling' => [3600, 0, QueuePassReport::TIME_CAP],
        ];
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

    /** A host that hides PHP's limits: nothing bounds the pass but the queue being empty. */
    public function testUnreadableLimitsMeanNoPhpLimit(): void
    {
        $limits = $this->factory([])->forPass();

        $this->assertSame(PHP_INT_MAX, $limits->memoryCeilingBytes);
        $this->assertNull($limits->phpStopsAt);
    }
}
