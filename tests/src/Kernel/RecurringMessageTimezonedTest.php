<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use YesWiki\Kernel\Queue\RecurringMessageTimezoned;

require_once 'tests/src/Kernel/QueueTestTask.php';

/**
 * A recurring task and the timezone it runs in, which it checks itself: a name that does not exist (a typo in the wiki's
 * configuration) falls back to PHP's timezone rather than throwing when the Scheduler first asks for a run.
 */
#[CoversClass(RecurringMessageTimezoned::class)]
class RecurringMessageTimezonedTest extends TestCase
{
    private string $phpTimezone;

    protected function setUp(): void
    {
        $this->phpTimezone = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->phpTimezone);
    }

    public function testATimezoneThatExistsIsKept(): void
    {
        $recurring = RecurringMessageTimezoned::cron('0 7 * * *', new QueueTestTask('digest'), 'Europe/Paris');

        $this->assertSame('Europe/Paris', $recurring->timezone()->getName());
        $this->assertNull($recurring->rejectedTimezone());
    }

    public function testATimezoneGivenAsAnObjectIsKept(): void
    {
        $recurring = RecurringMessageTimezoned::every('30 minutes', new QueueTestTask('often'), new \DateTimeZone('America/New_York'));

        $this->assertSame('America/New_York', $recurring->timezone()->getName());
    }

    /** No timezone is the wiki's: its `timezone`, which YesWikiInit makes PHP's when it exists, and what its pages show times in. */
    #[DataProvider('noTimezone')]
    public function testNoTimezoneIsPhps(?string $timezone): void
    {
        $recurring = RecurringMessageTimezoned::every('24 hours', new QueueTestTask('daily'), $timezone);

        $this->assertSame('Asia/Tokyo', $recurring->timezone()->getName());
        $this->assertNull($recurring->rejectedTimezone(), 'naming none is no mistake');
    }

    /** @return array<string, array{?string}> */
    public static function noTimezone(): array
    {
        return ['null' => [null], 'empty' => ['']];
    }

    /** A name PHP does not know runs in PHP's timezone, and is kept so the Scheduler can say it. */
    #[DataProvider('unknownTimezones')]
    public function testAnUnknownTimezoneFallsBackToPhpsAndIsKept(string $timezone): void
    {
        $recurring = RecurringMessageTimezoned::cron('0 7 * * *', new QueueTestTask('digest'), $timezone);

        $this->assertSame('Asia/Tokyo', $recurring->timezone()->getName());
        $this->assertSame($timezone, $recurring->rejectedTimezone());
    }

    /** @return array<string, array{string}> */
    public static function unknownTimezones(): array
    {
        return ['a typo' => ['Europe/Pariss'], 'a trailing space' => ['Europe/Paris '], 'no zone at all' => ['UTC+2 heures']];
    }

    /** The cron of an unknown timezone gives its next run, at its hour in PHP's timezone: Symfony's would throw on the first one. */
    public function testTheCronOfAnUnknownTimezoneRunsAtItsHourInPhps(): void
    {
        $recurring = RecurringMessageTimezoned::cron('0 7 * * *', new QueueTestTask('digest'), 'Europe/Pariss');

        $next = $recurring->getTrigger()->getNextRunDate(new \DateTimeImmutable('2026-11-04 12:00:00', new \DateTimeZone('UTC')));

        $this->assertSame('2026-11-05 07:00:00', $next?->setTimezone(new \DateTimeZone('Asia/Tokyo'))->format('Y-m-d H:i:s'));
    }

    /** It is the RecurringMessage it wraps to whoever reads it: the same id, trigger and messages. */
    public function testItReadsAsTheRecurringMessageItWraps(): void
    {
        $task = new QueueTestTask('often');
        $recurring = RecurringMessageTimezoned::every('30 minutes', $task);
        $context = new MessageContext('test', $recurring->getId(), $recurring->getTrigger(), new \DateTimeImmutable());

        $this->assertInstanceOf(PeriodicalTrigger::class, $recurring->getTrigger());
        $this->assertSame($recurring->recurringMessage()->getId(), $recurring->getId());
        $this->assertSame([$task], [...$recurring->getMessages($context)]);
    }

    /** Any trigger can be declared in a timezone, for what every() and cron() do not say (a start, an end). */
    public function testAnyTriggerCanBeDeclared(): void
    {
        $trigger = CronExpressionTrigger::fromSpec('0 7 * * *', null, 'Europe/Paris');
        $recurring = RecurringMessageTimezoned::trigger($trigger, new QueueTestTask('digest'), 'Europe/Paris');

        $this->assertSame($trigger, $recurring->getTrigger());
        $this->assertSame('Europe/Paris', $recurring->timezone()->getName());
    }

    /** The wiki's timezone, for what has no task to say its own: PHP's. */
    public function testTheWikisTimezoneIsPhps(): void
    {
        $this->assertSame('Asia/Tokyo', RecurringMessageTimezoned::wikiTimezone()->getName());
    }
}
