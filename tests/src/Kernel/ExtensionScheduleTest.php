<?php

namespace YesWiki\Test\Kernel;

use Symfony\Component\Clock\MockClock;
use YesWiki\HelloWorld\Scheduler\HelloworldSchedule;
use YesWiki\Kernel\Scheduler\KernelSchedule;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';

/** An extension declares its own recurring task in schedulers/, as helloworld does. */
class ExtensionScheduleTest extends YesWikiTestCase
{
    private ?QueueTestKit $kit = null;

    /** The wiki boots first: it registers the loader of the extensions' classes. */
    public static function setUpBeforeClass(): void
    {
        self::getWiki();
    }

    protected function tearDown(): void
    {
        $this->kit?->dispose();
    }

    public function testAnExtensionDeclaresItsOwnScheduledTask(): void
    {
        $clock = new MockClock(new \DateTimeImmutable('2026-10-23 12:00:00'));
        $this->kit = new QueueTestKit(clock: $clock, schedule: new HelloworldSchedule());
        $ticker = $this->kit->ticker;

        $this->assertSame(0, $ticker->tick(), 'a task first seen waits a period');
        $task = $ticker->task('helloworld-greet');
        $this->assertNotNull($task, 'the ticker declares the extension\'s task');
        $this->assertSame(KernelSchedule::HOUSEKEEPING_EVERY, $task['everySeconds']);
        $this->assertNotSame('', $task['label']);

        $clock->sleep(KernelSchedule::HOUSEKEEPING_EVERY + 1);
        $this->assertSame(1, $ticker->tick(), 'sent once its period is over');
        $this->assertSame(0, $ticker->tick(), 'and only once');
        $this->assertSame(1, $this->kit->statistics->pendingCount());
    }
}
