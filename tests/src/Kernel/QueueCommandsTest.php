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
use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Kernel\Command\QueueConsumeCommand;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Service\Queue\QueueBudgetFactory;
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

        $limit = QueueBudgetFactory::bytes((string)ini_get('memory_limit'));
        $this->assertGreaterThan(0, $limit, 'the suite runs with a memory_limit');
        $this->assertSame((int)floor($limit * 0.8), $this->kit->statistics->lastPass()['memoryLimit'] ?? null);
    }

    /** Emptying the queue is what is already in it: a scheduled task that is due waits for the consumer of the mode. */
    public function testDrainDoesNotTickTheSchedule(): void
    {
        $this->kit->dispose();
        $clock = new MockClock();
        $this->kit = new QueueTestKit([QueueTestTask::class => function (QueueTestTask $task): void {
            $this->kit->handled[] = 'scheduled';
        }], $clock, schedule: new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [RecurringMessage::every('30 minutes', new QueueTestTask('digest'))];
            }
        });
        $this->kit->ticker->tick();
        $clock->sleep(31 * 60);
        $this->kit->send('a');

        $this->execute('messenger:consume', ['--drain' => true]);

        $this->assertSame(['a'], $this->kit->handled);
        $this->assertSame(0, $this->kit->statistics->pendingCount());
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

    public function testMessageRefusesWhatIsNoId(): void
    {
        $tester = $this->execute('messenger:consume', ['--message' => 'the backup']);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertSame([], $this->kit->handled);
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

    public function testSupervisedConsumeExitsWith78WhenTheModeIsNotBinary(): void
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

    public function testSupervisedConsumeRunsInBinaryMode(): void
    {
        putenv(QueueSettings::ENV_MODE . '=binary');
        $this->kit->send('a');

        $tester = $this->execute('messenger:consume', ['--supervised' => true, '--limit' => '1', '--time-limit' => '2']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['a'], $this->kit->handled);
        $this->assertSame('binary', $this->kit->statistics->lastPass()['mode'] ?? null);
        $this->assertStringContainsString('limit', $tester->getDisplay());
    }

    /** The binary restarts a consumer the configuration change stopped at once, however short its life: not a crash to back off from. */
    public function testSupervisedConsumeExitsWith75WhenTheConfigurationChanged(): void
    {
        $this->changeTheConfigurationOnTheFirstMessage();
        putenv(QueueSettings::ENV_MODE . '=binary');

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

    public function testConsumeRefusesAMemoryLimitItCannotRead(): void
    {
        $this->kit->send('a');

        $tester = $this->execute('messenger:consume', ['--memory-limit' => 'lots', '--limit' => '1', '--time-limit' => '2']);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertSame([], $this->kit->handled);
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
