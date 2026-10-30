<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Messenger\Envelope;
use YesWiki\Kernel\Command\QueueConsumeCommand;
use YesWiki\Kernel\Command\QueueDrainCommand;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTestOnceMessage.php';

/** `messenger:drain` (a pass that ends by itself) and `messenger:consume` (blocks; `--supervised` for the Go binary). */
#[CoversClass(QueueDrainCommand::class)]
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

        $tester = $this->execute('messenger:drain', []);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['a', 'b'], $this->kit->handled);
        $this->assertStringContainsString('handled 2', $tester->getDisplay());
        $this->assertStringContainsString('remaining 0', $tester->getDisplay());
        $this->assertStringContainsString('empty', $tester->getDisplay());
    }

    public function testDrainBudgetCapsTheTimeTheFactoryGives(): void
    {
        $this->kit->send('a');

        $capped = $this->execute('messenger:drain', ['--budget' => '0.001']);

        $this->assertSame(Command::SUCCESS, $capped->getStatusCode(), 'not having the time is not a failure');
        $this->assertSame([], $this->kit->handled);
        $this->assertStringContainsString('no-budget', $capped->getDisplay());

        $this->execute('messenger:drain', []);
        $this->assertSame(['a'], $this->kit->handled, 'without --budget the factory\'s own budget applies');
    }

    public function testDrainRunsUnderTheModeOfTheWikiWhenItIsBudgeted(): void
    {
        putenv(QueueSettings::ENV_MODE . '=request-spawn');
        $this->kit->send('a');

        $this->execute('messenger:drain', []);

        $this->assertSame('request-spawn', $this->kit->statistics->lastPass()['mode'] ?? null, 'a detached process started by a request is recorded as what it is');
    }

    public function testDrainRunsAsACommandWhenTheModeBlocks(): void
    {
        putenv(QueueSettings::ENV_MODE . '=worker');
        $this->kit->send('a');

        $this->execute('messenger:drain', []);

        $this->assertSame(['a'], $this->kit->handled);
        $this->assertSame('command', $this->kit->statistics->lastPass()['mode'] ?? null);
    }

    public function testDrainFailsWhenThePassBreaks(): void
    {
        $this->kit->dropTables();

        $tester = $this->execute('messenger:drain', []);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('error', $tester->getDisplay());
    }

    /** The process a request hands its pinned message to when its response cannot leave first (QueueRequestTrigger): that message, and nothing else. */
    public function testDrainWithAMessageHandlesThatOneOnly(): void
    {
        putenv(QueueSettings::ENV_MODE . '=command');
        $this->kit->send('other');
        $pinned = $this->kit->send('pinned');

        $tester = $this->execute('messenger:drain', ['--message' => (string)$pinned]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['pinned'], $this->kit->handled);
        $this->assertSame('pinned', $this->kit->statistics->lastPass()['origin'] ?? null, 'run for the request that pinned it, it does not make the consumer seen');
        $this->assertSame(1, $this->kit->statistics->pendingCount());
    }

    public function testDrainRefusesAMessageThatIsNoId(): void
    {
        $tester = $this->execute('messenger:drain', ['--message' => 'the backup']);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertSame([], $this->kit->handled);
    }

    public function testDrainRefusesAnUnreadableBudget(): void
    {
        $tester = $this->execute('messenger:drain', ['--budget' => 'soon']);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
    }

    public function testSupervisedConsumeExitsWith78WhenTheModeIsNotBinary(): void
    {
        putenv(QueueSettings::ENV_MODE . '=command');
        $this->kit->send('a');

        $tester = $this->execute('messenger:consume', ['--supervised' => true, '--limit' => '1', '--time-limit' => '2'], true);

        $this->assertSame(78, $tester->getStatusCode());
        $this->assertStringContainsString('command', $tester->getErrorOutput(), 'one line on stderr says which mode the wiki is in');
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
        putenv(QueueSettings::ENV_MODE . '=command');
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
            'messenger:drain' => new QueueDrainCommand($services),
            'messenger:consume' => new QueueConsumeCommand($services),
            default => throw new \InvalidArgumentException("No command $name here."),
        });
        $tester = new CommandTester($application->find($name));
        $tester->execute(['command' => $name] + $options, ['capture_stderr_separately' => $separateStderr]);

        return $tester;
    }
}
