<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Queue\QueueBudget;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Service\Queue\QueueBudgetContext;
use YesWiki\Kernel\Service\Queue\QueueBudgetFactory;

/** How long and how much memory one non-blocking pass may take, from the limits the server itself gives PHP. */
#[CoversClass(QueueBudget::class)]
#[CoversClass(QueueBudgetFactory::class)]
#[CoversClass(QueueBudgetContext::class)]
class QueueBudgetTest extends TestCase
{
    private const NOW = 1_000_000.0;

    /**
     * @param array<string, string> $ini
     * @param float                 $elapsed seconds since the request started
     */
    private function factory(array $ini, float $elapsed = 0.0): QueueBudgetFactory
    {
        return new QueueBudgetFactory(
            static fn (string $name) => $ini[$name] ?? false,
            static fn (bool $asFloat = false) => self::NOW,
            self::NOW - $elapsed
        );
    }

    /** Seconds the pass may last, counted from the (frozen) instant the factory was asked. */
    private function allowed(QueueBudget $budget): float
    {
        return $budget->deadline - self::NOW;
    }

    public function testRequestWhereTheVisitorWaitsIsCappedAtThreeSeconds(): void
    {
        $budget = $this->factory(['max_execution_time' => '30', 'memory_limit' => '128M'], 1.0)
            ->forMode(QueueConsumerMode::Request, false);

        $this->assertEqualsWithDelta(3.0, $this->allowed($budget), 0.001);
    }

    public function testRequestAfterTheResponseUsesTheLimitMinusTheMargin(): void
    {
        $budget = $this->factory(['max_execution_time' => '30', 'memory_limit' => '128M'], 4.0)
            ->forMode(QueueConsumerMode::Request, true);

        // min(20 s ceiling, 30 - 4 - (2 + 10 % of 30) = 21)
        $this->assertEqualsWithDelta(20.0, $this->allowed($budget), 0.001);
    }

    public function testTheMarginLeavesTheRenderTimeOut(): void
    {
        $budget = $this->factory(['max_execution_time' => '30', 'memory_limit' => '128M'], 25.0)
            ->forMode(QueueConsumerMode::Request, true);

        $this->assertEqualsWithDelta(0.0, $this->allowed($budget), 0.001);
        $this->assertFalse($budget->canAfford(0.1));
    }

    public function testAPageThatAlreadyUsedTheWholeLimitGetsNothingNotANegativeBudget(): void
    {
        $budget = $this->factory(['max_execution_time' => '30', 'memory_limit' => '128M'], 29.0)
            ->forMode(QueueConsumerMode::Request, true);

        $this->assertSame(0.0, $this->allowed($budget));
        $this->assertSame(0.0, $budget->remainingSeconds());
    }

    public function testRequestWithNoTimeLimitKeepsItsCeiling(): void
    {
        $factory = $this->factory(['max_execution_time' => '0', 'memory_limit' => '128M'], 10.0);

        $this->assertEqualsWithDelta(3.0, $this->allowed($factory->forMode(QueueConsumerMode::Request, false)), 0.001);
        $this->assertEqualsWithDelta(20.0, $this->allowed($factory->forMode(QueueConsumerMode::Request, true)), 0.001);
    }

    public function testCommandWithNoTimeLimitIsCappedAtFiveMinutes(): void
    {
        $budget = $this->factory(['max_execution_time' => '0', 'memory_limit' => '128M'])
            ->forMode(QueueConsumerMode::Command, false);

        $this->assertEqualsWithDelta(300.0, $this->allowed($budget), 0.001);
    }

    public function testRequestSpawnWithNoTimeLimitIsCappedAtFiveMinutes(): void
    {
        $budget = $this->factory(['max_execution_time' => '0', 'memory_limit' => '128M'])
            ->forMode(QueueConsumerMode::RequestSpawn, true);

        $this->assertEqualsWithDelta(300.0, $this->allowed($budget), 0.001);
    }

    public function testCommandWithATimeLimitUsesTheLimitMinusTheMargin(): void
    {
        $budget = $this->factory(['max_execution_time' => '100', 'memory_limit' => '128M'], 10.0)
            ->forMode(QueueConsumerMode::Command, false);

        // 100 - 10 - (2 + 10) = 78
        $this->assertEqualsWithDelta(78.0, $this->allowed($budget), 0.001);
    }

    public function testApiIsCappedAtFiftySeconds(): void
    {
        $ini = ['max_execution_time' => '0', 'memory_limit' => '128M'];
        $this->assertEqualsWithDelta(50.0, $this->allowed($this->factory($ini)->forMode(QueueConsumerMode::Api, false)), 0.001);

        $ini['max_execution_time'] = '600';
        $this->assertEqualsWithDelta(50.0, $this->allowed($this->factory($ini)->forMode(QueueConsumerMode::Api, false)), 0.001);

        $ini['max_execution_time'] = '30';
        // below the ceiling, the server's limit wins: 30 - (2 + 3) = 25
        $this->assertEqualsWithDelta(25.0, $this->allowed($this->factory($ini)->forMode(QueueConsumerMode::Api, false)), 0.001);
    }

    public function testMemoryCeilingIsEightyPercent(): void
    {
        $budget = $this->factory(['max_execution_time' => '30', 'memory_limit' => '128M'])
            ->forMode(QueueConsumerMode::Command, false);

        $this->assertSame(107374182, $budget->memoryCeilingBytes);
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
        $budget = $this->factory(['max_execution_time' => '30', 'memory_limit' => $limit])
            ->forMode(QueueConsumerMode::Command, false);

        $this->assertSame($ceiling, $budget->memoryCeilingBytes);
    }

    /** -1 means PHP imposes nothing: the pass is not stopped on memory, the host's own kill is what it is left with. */
    public function testNoMemoryLimitMeansNoMemoryCeiling(): void
    {
        $budget = $this->factory(['max_execution_time' => '30', 'memory_limit' => '-1'])
            ->forMode(QueueConsumerMode::Command, false);

        $this->assertSame(PHP_INT_MAX, $budget->memoryCeilingBytes);
        $this->assertFalse($budget->memoryExhausted());
    }

    public function testMemoryIsExhaustedPastTheCeiling(): void
    {
        $this->assertTrue((new QueueBudget(self::NOW + 10, 1, 60))->memoryExhausted());
        $this->assertFalse((new QueueBudget(self::NOW + 10, PHP_INT_MAX, 60))->memoryExhausted());
    }

    public function testKillLimitIsTheTimeLeftBeforePhpStops(): void
    {
        $budget = $this->factory(['max_execution_time' => '30', 'memory_limit' => '128M'], 4.0)
            ->forMode(QueueConsumerMode::Request, true);

        $this->assertSame(26, $budget->killLimitSeconds);
    }

    public function testKillLimitWithoutAPhpLimitIsSixHours(): void
    {
        $budget = $this->factory(['max_execution_time' => '0', 'memory_limit' => '128M'])
            ->forMode(QueueConsumerMode::Command, false);

        $this->assertSame(6 * 3600, $budget->killLimitSeconds);
    }

    /**
     * @return iterable<string, array{QueueConsumerMode}>
     */
    public static function blockingModes(): iterable
    {
        yield 'worker' => [QueueConsumerMode::Worker];
        yield 'binary' => [QueueConsumerMode::Binary];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('blockingModes')]
    public function testBlockingModesHaveNoTimeBudgetAndTheSixHourKillLimit(QueueConsumerMode $mode): void
    {
        $budget = $this->factory(['max_execution_time' => '30', 'memory_limit' => '128M'], 10.0)
            ->forMode($mode, false);

        $this->assertSame(6 * 3600, $budget->killLimitSeconds);
        $this->assertTrue($budget->canAfford(1e9));
        $this->assertSame(INF, $budget->remainingSeconds());
        // a long-lived process still stops before PHP's own memory limit kills it in the middle of a handler
        $this->assertSame(107374182, $budget->memoryCeilingBytes);
    }

    public function testUnlimitedHasNoDeadlineNorMemoryCeiling(): void
    {
        $budget = QueueBudget::unlimited(120);

        $this->assertSame(120, $budget->killLimitSeconds);
        $this->assertSame(INF, $budget->remainingSeconds());
        $this->assertFalse($budget->memoryExhausted());
    }

    public function testCappedToNeverGrantsMoreThanTheBudgetHad(): void
    {
        $budget = new QueueBudget(microtime(true) + 10, 1000, 60);

        $shorter = $budget->cappedTo(2);
        $this->assertEqualsWithDelta(2.0, $shorter->remainingSeconds(), 0.2);
        $this->assertSame(1000, $shorter->memoryCeilingBytes);
        $this->assertSame(60, $shorter->killLimitSeconds);
        $this->assertEqualsWithDelta(10.0, $budget->cappedTo(30)->remainingSeconds(), 0.2, 'a cap above the deadline changes nothing');
        $this->assertEqualsWithDelta(5.0, QueueBudget::unlimited(60)->cappedTo(5)->remainingSeconds(), 0.2);
    }

    public function testBytesReadsTheSizeNotationOfPhpIni(): void
    {
        $this->assertSame(128 * 1024 ** 2, QueueBudgetFactory::bytes('128M'));
        $this->assertSame(512 * 1024, QueueBudgetFactory::bytes('512k'));
        $this->assertSame(1024 ** 3, QueueBudgetFactory::bytes('1G'));
        $this->assertSame(2048, QueueBudgetFactory::bytes('2048'));
        $this->assertSame(0, QueueBudgetFactory::bytes('-1'));
        $this->assertSame(0, QueueBudgetFactory::bytes(''));
    }

    public function testRemainingSecondsCountsDownToZeroAndStaysThere(): void
    {
        $this->assertEqualsWithDelta(5.0, (new QueueBudget(microtime(true) + 5, PHP_INT_MAX, 60))->remainingSeconds(), 0.2);
        $this->assertSame(0.0, (new QueueBudget(microtime(true) - 5, PHP_INT_MAX, 60))->remainingSeconds());
    }

    public function testCanAffordAMessageOnlyWhenItsExpectedDurationFits(): void
    {
        $budget = new QueueBudget(microtime(true) + 5, PHP_INT_MAX, 60);

        $this->assertTrue($budget->canAfford(1.0));
        $this->assertFalse($budget->canAfford(10.0));
    }

    public function testTheContextKnowsNoRemainingTimeOutsideABudgetedPass(): void
    {
        $this->assertNull((new QueueBudgetContext())->remainingSeconds());
    }

    public function testTheContextHandsHandlersTheRemainingTimeOfThePass(): void
    {
        $context = new QueueBudgetContext();
        $context->start(new QueueBudget(microtime(true) + 5, PHP_INT_MAX, 60));

        $this->assertEqualsWithDelta(5.0, $context->remainingSeconds(), 0.2);
    }

    public function testTheContextDoesNotSurviveTheRequest(): void
    {
        $context = new QueueBudgetContext();
        $context->start(new QueueBudget(microtime(true) + 5, PHP_INT_MAX, 60));
        $context->startNewRequest();

        $this->assertNull($context->remainingSeconds());
    }

    /** With no start injected, a request is measured from the one PHP recorded. */
    public function testTheRequestStartDefaultsToWhatPhpRecorded(): void
    {
        $before = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 25.0;
        try {
            $budget = (new QueueBudgetFactory(static fn (string $name) => ['max_execution_time' => '30', 'memory_limit' => '128M'][$name]))
                ->forMode(QueueConsumerMode::Request, true);
        } finally {
            if ($before === null) {
                unset($_SERVER['REQUEST_TIME_FLOAT']);
            } else {
                $_SERVER['REQUEST_TIME_FLOAT'] = $before;
            }
        }

        // 30 - 25 - (2 + 3) = 0: the render used what the margin leaves out
        $this->assertLessThan(0.5, $budget->remainingSeconds());
        $this->assertFalse($budget->canAfford(1.0));
    }

    public function testTheContextIsPartOfTheRequestScope(): void
    {
        $this->assertInstanceOf(\YesWiki\Kernel\Service\RequestScopedState::class, new QueueBudgetContext());
    }
}
