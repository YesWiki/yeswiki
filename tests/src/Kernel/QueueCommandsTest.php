<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Messenger\Envelope;
use YesWiki\Kernel\Command\QueueConsumeCommand;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\RecurringMessageTimezoned;
use YesWiki\Kernel\Service\Queue\QueuePassLimitsFactory;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTestOnceMessage.php';
require_once 'tests/src/Kernel/QueueTestTask.php';

/** `messenger:consume`: blocks (`--supervised` for the Go binary), or empties the queue and exits (`--drain`), or runs one pinned message (`--message`). */
#[CoversClass(QueueConsumeCommand::class)]
class QueueCommandsTest extends YesWikiTestCase
{
    private QueueTestKit $kit;

    protected function setUp(): void
    {
        $this->kit = new QueueTestKit();
    }

    protected function tearDown(): void
    {
        $this->kit->dispose();
    }

    public function testDrainPrintsWhatItDid(): void
    {
        $this->kit->send('a');
        $this->kit->send('b');

        $tester = $this->execute('messenger:consume', ['--drain' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['a', 'b'], $this->kit->handled);
        $this->assertStringContainsString('handled 2', $tester->getDisplay());
        $this->assertStringContainsString('remaining 0', $tester->getDisplay());
        $this->assertStringContainsString('empty', $tester->getDisplay());
    }

    /** Run by hand under a worker, it must neither count as that worker starting nor make a dead one look alive. */
    public function testDrainIsAManualRequestPassWhateverTheMode(): void
    {
        putenv(QueueSettings::ENV_MODE . '=worker');
        $this->kit->send('a');

        $this->execute('messenger:consume', ['--drain' => true]);

        $this->assertSame(['a'], $this->kit->handled);
        $this->assertSame('request', $this->kit->statistics->lastPass()['mode'] ?? null);
        $this->assertSame('manual', $this->kit->statistics->lastPass()['origin'] ?? null);
        $this->assertNull($this->kit->statistics->lastSeenAt(QueueConsumerMode::Worker));
    }

    /** A long drain (a whole reindex) stops cleanly on memory, as every other pass does, rather than dying in PHP's fatal error mid-message. */
    public function testDrainKeepsTheMemoryCeiling(): void
    {
        $this->kit->send('a');

        $this->execute('messenger:consume', ['--drain' => true]);

        $limit = QueuePassLimitsFactory::bytes((string)ini_get('memory_limit'));
        $this->assertGreaterThan(0, $limit, 'the suite runs with a memory_limit');
        $ceiling = $this->kit->statistics->lastPass()['memoryLimit'] ?? null;
        $this->assertIsInt($ceiling);
        $this->assertGreaterThan(0, $ceiling, 'a drain is not unbounded in memory');
        $this->assertLessThan($limit, $ceiling, 'and stops short of PHP\'s own limit, which would be a fatal error');
    }

    /** A drain ticks the schedule first: a scheduled task that is due is queued and handled in the same pass. */
    public function testDrainTicksTheScheduleThenHandlesWhatIsDue(): void
    {
        $clock = $this->kitWithAHalfHourlyTask();
        $clock->sleep(31 * 60);
        $this->kit->send('a');

        $this->execute('messenger:consume', ['--drain' => true]);

        $this->assertEqualsCanonicalizing(['a', 'scheduled'], $this->kit->handled);
        $this->assertSame(0, $this->kit->statistics->pendingCount());
    }

    /** Only the tasks that are due: one whose next run is ahead is not run early. */
    public function testDrainLeavesATaskThatIsNotDueYet(): void
    {
        $clock = $this->kitWithAHalfHourlyTask();
        $clock->sleep(10 * 60);
        $this->kit->send('a');

        $this->execute('messenger:consume', ['--drain' => true]);

        $this->assertSame(['a'], $this->kit->handled);
    }

    public function testDrainWhileAnotherPassRunsSaysLocked(): void
    {
        $this->kit->send('a');
        $this->kit->holdThePassLock();

        $tester = $this->execute('messenger:consume', ['--drain' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), 'another pass is at it: not a failure');
        $this->assertSame([], $this->kit->handled);
        $this->assertStringContainsString('locked', $tester->getDisplay());
    }

    public function testDrainFailsWhenThePassBreaks(): void
    {
        $this->kit->dropTables();

        $tester = $this->execute('messenger:consume', ['--drain' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('error', $tester->getDisplay());
    }

    /** @param array<string, mixed> $option */
    #[DataProvider('limitsOfABlockingConsumer')]
    public function testDrainRefusesTheLimitsOfABlockingConsumer(array $option): void
    {
        $this->kit->send('a');

        $tester = $this->execute('messenger:consume', ['--drain' => true] + $option);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertSame([], $this->kit->handled);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function limitsOfABlockingConsumer(): array
    {
        return [
            'time limit' => [['--time-limit' => '5']],
            'memory limit' => [['--memory-limit' => '64M']],
            'message limit' => [['--limit' => '1']],
            'supervised' => [['--supervised' => true]],
        ];
    }

    /** The process a request hands its pinned message to when its response cannot leave first (QueueRequestTrigger): that message, and nothing else. */
    public function testMessageHandlesThatOneOnly(): void
    {
        putenv(QueueSettings::ENV_MODE . '=api');
        $this->kit->send('other');
        $pinned = $this->kit->send('pinned');

        $tester = $this->execute('messenger:consume', ['--message' => (string)$pinned]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['pinned'], $this->kit->handled);
        $this->assertSame('pinned', $this->kit->statistics->lastPass()['origin'] ?? null, 'run for the request that pinned it, it does not make the consumer seen');
        $this->assertSame(1, $this->kit->statistics->pendingCount());
    }

    #[DataProvider('whatIsNoId')]
    public function testMessageRefusesWhatIsNoId(string $value): void
    {
        $this->kit->send('a');

        $tester = $this->execute('messenger:consume', ['--message' => $value]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertSame([], $this->kit->handled);
    }

    /** @return array<string, array{string}> */
    public static function whatIsNoId(): array
    {
        return ['words' => ['the backup'], 'zero' => ['0'], 'digits then letters' => ['12abc'], 'negative' => ['-1']];
    }

    /** @param array<string, mixed> $option */
    #[DataProvider('notWithAMessage')]
    public function testMessageCannotBeCombinedWithDrainOrSupervised(array $option): void
    {
        $pinned = $this->kit->send('pinned');

        $tester = $this->execute('messenger:consume', ['--message' => (string)$pinned] + $option);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertSame([], $this->kit->handled);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function notWithAMessage(): array
    {
        return [
            'drain' => [['--drain' => true]],
            'supervised' => [['--supervised' => true]],
        ];
    }

    public function testSupervisedConsumeExitsWith78WhenTheModeIsNotWorker(): void
    {
        putenv(QueueSettings::ENV_MODE . '=api');
        $this->kit->send('a');

        $tester = $this->execute('messenger:consume', ['--supervised' => true, '--limit' => '1', '--time-limit' => '2'], true);

        $this->assertSame(78, $tester->getStatusCode());
        $this->assertStringContainsString('api', $tester->getErrorOutput(), 'one line on stderr says which mode the wiki is in');
        $this->assertSame(1, substr_count(trim($tester->getErrorOutput()), "\n") + 1);
        $this->assertSame([], $this->kit->handled, 'nothing is consumed before it is known to be this process\'s job');
        $this->assertNull($this->kit->statistics->lastPass(), 'not even a pass row');
    }

    public function testSupervisedConsumeRunsInWorkerMode(): void
    {
        putenv(QueueSettings::ENV_MODE . '=worker');
        $this->kit->send('a');

        $tester = $this->execute('messenger:consume', ['--supervised' => true, '--limit' => '1', '--time-limit' => '2']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['a'], $this->kit->handled);
        $this->assertSame('worker', $this->kit->statistics->lastPass()['mode'] ?? null, 'supervised or not, it is the worker');
        $this->assertStringContainsString('limit', $tester->getDisplay());
    }

    /** The binary restarts a consumer the configuration change stopped at once, however short its life: not a crash to back off from. */
    public function testSupervisedConsumeExitsWith75WhenTheConfigurationChanged(): void
    {
        $this->changeTheConfigurationOnTheFirstMessage();
        putenv(QueueSettings::ENV_MODE . '=worker');

        $tester = $this->execute('messenger:consume', ['--supervised' => true, '--time-limit' => '30', '--memory-limit' => '-1']);

        $this->assertSame(QueueConsumeCommand::EXIT_CONFIGURATION_CHANGED, $tester->getStatusCode());
        $this->assertSame(75, QueueConsumeCommand::EXIT_CONFIGURATION_CHANGED);
        $this->assertSame(['a'], $this->kit->handled, 'the second message waits for the consumer that knows the new configuration');
        $this->assertStringContainsString('config-changed', $tester->getDisplay());
    }

    /** systemd restarts a worker whatever its exit; a status of 0 keeps its journal from calling it a failure. */
    public function testAWorkerTheConfigurationChangeStoppedExitsWithSuccess(): void
    {
        $this->changeTheConfigurationOnTheFirstMessage();

        $tester = $this->execute('messenger:consume', ['--time-limit' => '30', '--memory-limit' => '-1']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['a'], $this->kit->handled);
        $this->assertStringContainsString('config-changed', $tester->getDisplay());
    }

    public function testConsumeWithoutSupervisedRunsAsAWorkerWhateverTheMode(): void
    {
        putenv(QueueSettings::ENV_MODE . '=api');
        $this->kit->send('a');

        $tester = $this->execute('messenger:consume', ['--limit' => '1', '--time-limit' => '2', '--memory-limit' => '128M']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['a'], $this->kit->handled);
        $this->assertSame('worker', $this->kit->statistics->lastPass()['mode'] ?? null);
    }

    /**
     * A limit that is not what it says would make a consumer that never stops itself (0), or one that stops at once.
     *
     * @param array<string, string> $option
     */
    #[DataProvider('optionsItCannotRead')]
    public function testConsumeRefusesAnOptionItCannotRead(array $option): void
    {
        $this->kit->send('a');

        $tester = $this->execute('messenger:consume', $option + ['--limit' => '1', '--time-limit' => '2']);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertSame([], $this->kit->handled);
    }

    /** @return array<string, array{array<string, string>}> */
    public static function optionsItCannotRead(): array
    {
        return [
            'a time limit of zero' => [['--time-limit' => '0']],
            'a time limit that is no number' => [['--time-limit' => 'abc']],
            'a negative time limit' => [['--time-limit' => '-5']],
            'a fractional time limit' => [['--time-limit' => '1.5']],
            'a message limit of zero' => [['--limit' => '0']],
            'a fractional message limit' => [['--limit' => '1.5']],
            'a negative message limit' => [['--limit' => '-1']],
            'a memory limit that is no size' => [['--memory-limit' => 'lots']],
            'a memory limit of zero' => [['--memory-limit' => '0']],
            'a memory limit of zero megabytes' => [['--memory-limit' => '0M']],
            'a memory limit with a unit nobody knows' => [['--memory-limit' => '12x']],
        ];
    }

    /** @param array<string, string> $option */
    #[DataProvider('sizesItReads')]
    public function testConsumeReadsTheSizesPhpReads(array $option): void
    {
        $this->kit->send('a');

        $tester = $this->execute('messenger:consume', $option + ['--limit' => '1', '--time-limit' => '2']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['a'], $this->kit->handled);
    }

    /** @return array<string, array{array<string, string>}> */
    public static function sizesItReads(): array
    {
        return [
            'gigabytes' => [['--memory-limit' => '1G']],
            'megabytes in lower case' => [['--memory-limit' => '4096m']],
            'bytes' => [['--memory-limit' => '4294967296']],
            'no limit' => [['--memory-limit' => '-1']],
        ];
    }

    /** A size below what the process already uses is a size, not an error: the consumer stops on it once the message in hand is done. */
    public function testAMemoryLimitBelowWhatIsUsedStopsTheConsumerOnMemory(): void
    {
        $this->kit->send('a');
        $this->kit->send('b');

        $tester = $this->execute('messenger:consume', ['--memory-limit' => '512K', '--time-limit' => '2']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['a'], $this->kit->handled, 'the second waits for the consumer that replaces this one');
        $this->assertStringContainsString('memory', $tester->getDisplay());
    }

    public function testAConsumerWhosePassBreaksFails(): void
    {
        $this->kit->dropTables();

        $tester = $this->execute('messenger:consume', ['--limit' => '1', '--time-limit' => '2']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    /**
     * An id no one waits on any more (already handled, never there, or given up on) is not an error for the request that pinned it: nothing is left to do.
     *
     * @param 'missing'|'handled'|'failed' $which
     */
    #[DataProvider('messagesThatAreNotWaiting')]
    public function testMessageThatIsNotWaitingHandlesNothing(string $which): void
    {
        $id = match ($which) {
            'missing' => 999999,
            'handled', 'failed' => $this->kit->send('gone'),
        };
        if ($which === 'handled') {
            $this->execute('messenger:consume', ['--drain' => true]);
            $this->kit->handled = [];
        }
        if ($which === 'failed') {
            $this->kit->transport->moveToFailed($id, 'RuntimeException: boom');
        }
        $other = $this->kit->send('other');

        $tester = $this->execute('messenger:consume', ['--message' => (string)$id]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame([], $this->kit->handled);
        $this->assertSame('pending', $this->kit->statistics->messageState($other), 'it takes no other message in its place');
    }

    /** @return array<string, array{'missing'|'handled'|'failed'}> */
    public static function messagesThatAreNotWaiting(): array
    {
        return ['never there' => ['missing'], 'already handled' => ['handled'], 'failed' => ['failed']];
    }

    public function testMessageOnAQueueThatCannotBeReadFails(): void
    {
        $id = $this->kit->send('a');
        $this->kit->dropTables();

        $tester = $this->execute('messenger:consume', ['--message' => (string)$id]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    /** Two messages; handling the first changes the configuration, and moves the consumer's clock past the moment it asks again. */
    private function changeTheConfigurationOnTheFirstMessage(): void
    {
        $this->kit->dispose();
        $clock = new MockClock();
        $this->kit = new QueueTestKit([QueueTestOnceMessage::class => function (QueueTestOnceMessage $message) use ($clock): void {
            $this->kit->handled[] = $message->value;
            $this->kit->configurationChanged = true;
            $clock->sleep(6);
        }], $clock);
        $this->kit->transport->send(new Envelope(new QueueTestOnceMessage('a')));
        $this->kit->send('b');
    }

    /** A kit whose schedule has one task every 30 minutes, seen once at 12:01 so that its next run is the 12:30 slot; returns its clock. */
    private function kitWithAHalfHourlyTask(): MockClock
    {
        $this->kit->dispose();
        $clock = new MockClock('2026-01-05 12:01:00');
        $this->kit = new QueueTestKit([QueueTestTask::class => function (QueueTestTask $task): void {
            $this->kit->handled[] = 'scheduled';
        }], $clock, schedule: new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [RecurringMessageTimezoned::every('30 minutes', new QueueTestTask('digest'))];
            }
        });
        $this->kit->ticker->tick();

        return $clock;
    }

    /** @param array<string, mixed> $options */
    private function execute(string $name, array $options, bool $separateStderr = false): CommandTester
    {
        $settings = new QueueSettings(new ParameterBag([]), static fn (): array => []);
        $application = new Application();
        $services = $this->kit->services($settings);
        $application->add(match ($name) {
            'messenger:consume' => new QueueConsumeCommand($services),
            default => throw new \InvalidArgumentException("No command $name here."),
        });
        $tester = new CommandTester($application->find($name));
        $tester->execute(['command' => $name] + $options, ['capture_stderr_separately' => $separateStderr]);

        return $tester;
    }
}
