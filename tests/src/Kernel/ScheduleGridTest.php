<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use YesWiki\Kernel\Service\Queue\ScheduleGrid;

/** A task declared with every() runs on fixed slots counted from midnight in the wiki's timezone, never a period after its last run. */
#[CoversClass(ScheduleGrid::class)]
class ScheduleGridTest extends TestCase
{
    private const PARIS = 'Europe/Paris';

    /** @return iterable<string, array{int|string, string, string}> period, after, next run (Paris wall clock) */
    public static function slots(): iterable
    {
        yield 'a late tick does not push the next slot' => [1800, '2026-11-04 07:03:00', '2026-11-04 07:30:00'];
        yield 'a run on its slot waits for the next one' => [1800, '2026-11-04 07:30:00', '2026-11-04 08:00:00'];
        yield 'the last slot of the day is followed by midnight' => [1800, '2026-11-04 23:45:00', '2026-11-05 00:00:00'];
        yield '45 minutes, within the day' => [2700, '2026-11-04 22:31:00', '2026-11-04 23:15:00'];
        yield '45 minutes, the grid restarts at midnight' => [2700, '2026-11-04 23:15:00', '2026-11-05 00:00:00'];
        yield '7 hours, the short last slot of the day ends at midnight' => [7 * 3600, '2026-11-04 21:00:00', '2026-11-05 00:00:00'];
        yield '24 hours, at midnight' => ['24 hours', '2026-11-04 07:00:00', '2026-11-05 00:00:00'];
        yield '24 hours, from midnight itself' => ['24 hours', '2026-11-04 00:00:00', '2026-11-05 00:00:00'];
        yield 'spring forward: the 2:00 that does not exist is 3:00' => [1800, '2026-03-29 01:45:00', '2026-03-29 03:00:00'];
        yield 'spring forward: 24 hours is the next midnight, 23 hours later' => ['24 hours', '2026-03-29 00:00:00', '2026-03-30 00:00:00'];
        yield 'fall back: 24 hours is the next midnight, 25 hours later' => ['24 hours', '2026-10-25 00:00:00', '2026-10-26 00:00:00'];
        // The reference is a Monday: every 7 days falls on Mondays, and the 2-day slots keep the parity of the days counted from it.
        yield '7 days, the next Monday' => ['7 days', '2026-11-04 07:03:00', '2026-11-09 00:00:00'];
        yield '7 days, from a Monday midnight, the Monday after' => ['7 days', '2026-11-09 00:00:00', '2026-11-16 00:00:00'];
        yield '2 days, the next even day from the reference' => ['2 days', '2026-11-04 07:03:00', '2026-11-06 00:00:00'];
        yield '2 days, from the slot before it' => ['2 days', '2026-11-04 00:00:00', '2026-11-06 00:00:00'];
        yield '2 days, spring forward: midnight to midnight is 47 hours' => ['2 days', '2026-03-29 12:00:00', '2026-03-31 00:00:00'];
        yield '2 days, the slot that precedes spring forward' => ['2 days', '2026-03-28 12:00:00', '2026-03-29 00:00:00'];
        yield '2 days, fall back: midnight to midnight is 49 hours' => ['2 days', '2026-10-25 12:00:00', '2026-10-27 00:00:00'];
        yield '2 days, the slot that precedes fall back' => ['2 days', '2026-10-24 12:00:00', '2026-10-25 00:00:00'];
        yield '25 hours, counted in elapsed time from the reference' => [25 * 3600, '2026-11-04 07:03:00', '2026-11-05 03:00:00'];
        yield '25 hours, the slot after' => [25 * 3600, '2026-11-05 03:00:00', '2026-11-06 04:00:00'];
        yield '25 hours, across spring forward the wall clock moves' => [25 * 3600, '2026-03-28 12:00:00', '2026-03-29 08:00:00'];
        yield '25 hours, across fall back the wall clock moves' => [25 * 3600, '2026-10-24 12:00:00', '2026-10-24 17:00:00'];
    }

    #[DataProvider('slots')]
    public function testATaskRunsOnTheNextSlotOfItsGrid(int|string $every, string $after, string $expected): void
    {
        $next = $this->grid()->nextRun(new PeriodicalTrigger($every), self::paris($after));

        $this->assertSame($expected, $next?->setTimezone(new \DateTimeZone(self::PARIS))->format('Y-m-d H:i:s'));
    }

    /** An anchor inside the hour that comes twice goes on with that hour's half hours; a grid run from before it skips the hour (see the sweep below). */
    public function testFromTheHourThatComesTwiceTheSecondTimeKeepsItsHalfHour(): void
    {
        $next = $this->grid()->nextRun(new PeriodicalTrigger(1800), self::paris('2026-10-25 02:15:00 +01:00'));

        $this->assertSame('2026-10-25T02:30:00+01:00', $next?->setTimezone(new \DateTimeZone(self::PARIS))->format('c'));
    }

    public function testTheAnchorsTimezoneDoesNotMatter(): void
    {
        $next = $this->grid()->nextRun(new PeriodicalTrigger('24 hours'), new \DateTimeImmutable('2026-11-04 06:03:00', new \DateTimeZone('UTC')));

        $this->assertSame('2026-11-04 23:00:00', $next?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'midnight in Paris');
    }

    public function testTheGridIsTheWikisTimezone(): void
    {
        $next = (new ScheduleGrid(new ParameterBag(['timezone' => 'UTC'])))->nextRun(new PeriodicalTrigger('24 hours'), self::paris('2026-11-04 07:03:00'));

        $this->assertSame('2026-11-05 00:00:00', $next?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
    }

    public function testAWikiWithNoTimezoneUsesPhps(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/New_York');

        try {
            $next = (new ScheduleGrid(new ParameterBag([])))->nextRun(new PeriodicalTrigger('24 hours'), self::paris('2026-11-04 07:03:00'));
        } finally {
            date_default_timezone_set($previous);
        }

        $this->assertSame('2026-11-05 00:00:00', $next?->setTimezone(new \DateTimeZone('America/New_York'))->format('Y-m-d H:i:s'));
    }

    public function testAnUnknownTimezoneFallsBackToPhps(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/New_York');

        try {
            $next = (new ScheduleGrid(new ParameterBag(['timezone' => 'Not/AZone'])))->nextRun(new PeriodicalTrigger('24 hours'), self::paris('2026-11-04 07:03:00'));
        } finally {
            date_default_timezone_set($previous);
        }

        $this->assertSame('2026-11-05 00:00:00', $next?->setTimezone(new \DateTimeZone('America/New_York'))->format('Y-m-d H:i:s'));
    }

    /** @return iterable<string, array{string}> */
    public static function changesOfTime(): iterable
    {
        yield 'spring forward' => ['2026-03-29'];
        yield 'fall back' => ['2026-10-25'];
    }

    /**
     * Across a change of time the slots keep moving forward, on the half hours of the wall clock: the 2:00 and 2:30 that do not exist
     * in spring are 3:00, and run from midnight the hour that comes twice in autumn gets no slots of its own, an hour and a half between two runs.
     */
    #[DataProvider('changesOfTime')]
    public function testThirtyMinutesAcrossAChangeOfTime(string $day): void
    {
        $trigger = new PeriodicalTrigger(1800);
        $run = self::paris($day . ' 00:00:00');
        $end = self::paris($day . ' 00:00:00')->modify('+1 day');
        $grid = $this->grid();
        while ($run < $end) {
            $next = $grid->nextRun($trigger, $run) ?? $this->fail('the grid ended');
            $this->assertGreaterThan($run, $next);
            $this->assertLessThanOrEqual(5400, $next->getTimestamp() - $run->getTimestamp(), $run->format(DATE_ATOM));
            $this->assertContains($next->setTimezone(new \DateTimeZone(self::PARIS))->format('i:s'), ['00:00', '30:00']);
            $run = $next;
        }
        $this->assertEquals($end, $run, 'the day ends on midnight');
    }

    public function testATriggerPastItsEndHasNoNextRun(): void
    {
        $trigger = new PeriodicalTrigger(1800, null, self::paris('2026-11-04 07:20:00'));

        $this->assertNull($this->grid()->nextRun($trigger, self::paris('2026-11-04 07:03:00')));
    }

    public function testACronTriggerKeepsItsOwnTimes(): void
    {
        $next = $this->grid()->nextRun(CronExpressionTrigger::fromSpec('0 7 * * *', null, self::PARIS), self::paris('2026-11-04 07:03:00'));

        $this->assertSame('2026-11-05 07:00:00', $next?->setTimezone(new \DateTimeZone(self::PARIS))->format('Y-m-d H:i:s'));
    }

    public function testTheGridLeavesTheTriggerAsItWas(): void
    {
        $trigger = new PeriodicalTrigger(1800);
        $this->grid()->nextRun($trigger, self::paris('2026-11-04 07:03:00'));

        $this->assertEquals(self::paris('2026-11-04 07:10:00')->modify('+30 minutes'), $trigger->getNextRunDate(self::paris('2026-11-04 07:10:00')));
    }

    public function testThePeriodOfAnIntervalIsItsLength(): void
    {
        $this->assertSame(1800, $this->grid()->everySeconds(new PeriodicalTrigger('30 minutes')));
        $this->assertSame(86400, $this->grid()->everySeconds(new PeriodicalTrigger('24 hours')));
        $this->assertNull($this->grid()->everySeconds(CronExpressionTrigger::fromSpec('0 7 * * *')));
    }

    private function grid(): ScheduleGrid
    {
        return new ScheduleGrid(new ParameterBag(['timezone' => self::PARIS]));
    }

    private static function paris(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable($time, new \DateTimeZone(self::PARIS));
    }
}
