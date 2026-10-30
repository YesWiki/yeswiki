<?php

namespace YesWiki\Test\Kernel;

use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ServiceLocator;
use YesWiki\Admin\Message\RunMaintenanceHooks;
use YesWiki\Admin\Message\RunMaintenanceHooksHandler;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Queue\RetryPolicy;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\ThrowableFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';

/**
 * `maintenance.before` / `maintenance.after`: where an extension hangs its own housekeeping, now called by the `extension-hooks` task.
 *
 * The handler runs over a dispatcher of its own, so that the listeners of the wiki's extensions
 * do not take part, and over the kit's queue statistics for the task's last run.
 */
class MaintenanceEventsTest extends YesWikiTestCase
{
    private ?QueueTestKit $kit = null;

    protected function tearDown(): void
    {
        $this->kit?->dispose();
    }

    public function testHooksKeepTheirContext(): void
    {
        $dispatcher = $this->dispatcher();
        $seen = [];
        $dispatcher->addListener('maintenance.before', function (Event $event) use (&$seen): void {
            $seen[] = ['phase' => 'before'] + $event->getData();
        });
        $dispatcher->addListener('maintenance.after', function (Event $event) use (&$seen): void {
            $seen[] = ['phase' => 'after'] + $event->getData();
        });
        $handler = $this->handler($dispatcher, $this->journal());

        $startedAt = time();
        $handler(new RunMaintenanceHooks());

        $this->assertSame(['before', 'after'], array_column($seen, 'phase'), 'one of each, in order');
        foreach ($seen as $event) {
            $this->assertGreaterThanOrEqual($startedAt, $event['startedAt']);
            $this->assertLessThanOrEqual(time(), $event['startedAt']);
            $this->assertSame(1800, $event['interval']);
            $this->assertArrayHasKey('previousRun', $event, 'null is an answer; a missing key is not');
            $this->assertNull($event['previousRun'], 'the task never ran');
        }
        $this->assertSame($seen[0]['startedAt'], $seen[1]['startedAt'], 'one run, one start');
        $this->assertArrayNotHasKey('duration', $seen[0]);
        $this->assertArrayHasKey('duration', $seen[1], 'only the end knows how long it took');
        $this->assertIsFloat($seen[1]['duration']);
        $this->assertGreaterThanOrEqual(0, $seen[1]['duration']);
    }

    /** The extension that wants to know how long ago it was last called reads it here. */
    public function testPreviousRunIsWhenTheTaskLastFinished(): void
    {
        $dispatcher = $this->dispatcher();
        $previous = [];
        $dispatcher->addListener('maintenance.before', function (Event $event) use (&$previous): void {
            $previous[] = $event->getData()['previousRun'];
        });
        $handler = $this->handler($dispatcher, $this->journal());

        $handler(new RunMaintenanceHooks());
        $this->kit?->recorder->taskFinished('extension-hooks', QueueRecorder::HANDLED, null);
        $handler(new RunMaintenanceHooks());

        $this->assertNull($previous[0]);
        $this->assertIsInt($previous[1]);
        $this->assertEqualsWithDelta(time(), $previous[1], 5);
    }

    /** An extension having a bad afternoon is neither the visitor's problem nor its neighbours': the next listener runs, and the failure is traced. */
    public function testAThrowingListenerIsTracedAndTheNextOneStillRuns(): void
    {
        $dispatcher = $this->dispatcher();
        $ran = [];
        $dispatcher->addListener('maintenance.before', function () {
            throw new \RuntimeException('an extension having a bad afternoon');
        });
        $dispatcher->addListener('maintenance.before', function () use (&$ran): void {
            $ran[] = 'before, after the one that threw';
        });
        $dispatcher->addListener('maintenance.after', [new BadAfternoonListener(), 'afterwards']);
        $dispatcher->addListener('maintenance.after', function () use (&$ran): void {
            $ran[] = 'after, after the one that threw';
        });
        $journal = $this->journal();
        $handler = $this->handler($dispatcher, $journal);

        try {
            $handler(new RunMaintenanceHooks());
            $this->fail('the task must show as failed');
        } catch (\RuntimeException $summary) {
            $this->assertStringStartsWith('2 maintenance hook(s) failed', $summary->getMessage());
            $this->assertStringContainsString('an extension having a bad afternoon', $summary->getMessage());
            $this->assertStringContainsString(BadAfternoonListener::class . '::afterwards', $summary->getMessage());
        }

        $this->assertSame(['before, after the one that threw', 'after, after the one that threw'], $ran);
        $this->assertCount(2, $journal->errors);
        $this->assertSame('maintenance.hook_failed', $journal->errors[0]['action']);
        $this->assertStringStartsWith('closure in MaintenanceEventsTest.php:', $journal->errors[0]['target'], 'a closure is named by where it was written');
        $this->assertInstanceOf(\RuntimeException::class, $journal->errors[0]['exception']);
        $this->assertSame('maintenance.hook_failed', $journal->errors[1]['action']);
        $this->assertSame(BadAfternoonListener::class . '::afterwards', $journal->errors[1]['target']);
    }

    public function testTheTaskSucceedsWhenEveryListenerDoes(): void
    {
        $dispatcher = $this->dispatcher();
        $journal = $this->journal();

        $this->handler($dispatcher, $journal)(new RunMaintenanceHooks());

        $this->assertSame([], $journal->errors, 'nothing to trace, and no exception: the task is handled');
    }

    public function testAListenerThatStopsPropagationSpareTheNextOnesAsAnyDispatchWould(): void
    {
        $dispatcher = $this->dispatcher();
        $ran = [];
        $dispatcher->addListener('maintenance.before', function (Event $event) use (&$ran): void {
            $ran[] = 'first';
            $event->stopPropagation();
        });
        $dispatcher->addListener('maintenance.before', function () use (&$ran): void {
            $ran[] = 'second';
        });
        $dispatcher->addListener('maintenance.after', function () use (&$ran): void {
            $ran[] = 'after';
        });

        $this->handler($dispatcher, $this->journal())(new RunMaintenanceHooks());

        $this->assertSame(['first', 'after'], $ran, 'a stopped event stops, but only that event');
    }

    /** Never retried: some listeners ran, and a second run would do their work again. */
    public function testTheTaskIsNeverRetried(): void
    {
        $task = new RunMaintenanceHooks();

        $this->assertInstanceOf(RetryPolicy::class, $task);
        $this->assertSame(0, $task->maxRetries());
        $this->assertSame('extension-hooks', $task->taskId());
        $this->assertContains(RunMaintenanceHooks::class, $this->getWiki()->services->get(QueueHandlerLocator::class)->handledTypes());
    }

    /** What an extension hooked on the events keeps hearing, on the wiki's own dispatcher. */
    public function testTheWikiDispatcherListenersAreTheOnesCalled(): void
    {
        $services = $this->getWiki()->services;
        $dispatcher = $services->get(EventDispatcher::class);
        $heard = [];
        $listener = function (Event $event) use (&$heard): void {
            $heard[] = $event->getData()['interval'];
        };
        $dispatcher->addListener('maintenance.before', $listener);

        try {
            $this->kit = new QueueTestKit();
            $handler = new RunMaintenanceHooksHandler($dispatcher, $this->journal(), $this->kit->statistics);
            $handler(new RunMaintenanceHooks());
        } finally {
            $dispatcher->removeListener('maintenance.before', $listener);
        }

        $this->assertSame([1800], $heard);
    }

    private function dispatcher(): EventDispatcher
    {
        return new EventDispatcher($this->getWiki()->services, new ThrowableFormatter());
    }

    private function handler(EventDispatcher $dispatcher, Journal $journal): RunMaintenanceHooksHandler
    {
        $this->kit ??= new QueueTestKit();

        return new RunMaintenanceHooksHandler($dispatcher, $journal, $this->kit->statistics);
    }

    private function journal(): ErrorsJournal
    {
        return new ErrorsJournal(new ServiceLocator([]));
    }
}

/** A Journal that keeps what it is told to log as an error, and writes nothing. */
final class ErrorsJournal extends Journal
{
    /** @var list<array<string, mixed>> */
    public array $errors = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ($level === LogLevel::ERROR) {
            $this->errors[] = $context + ['message' => (string)$message];
        }
    }
}
/** An extension's listener, as the subscribers of the extensions are: an object and a method. */
final class BadAfternoonListener
{
    public function afterwards(): void
    {
        throw new \LogicException('this one too');
    }
}
