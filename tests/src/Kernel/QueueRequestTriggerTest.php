<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Process\Process;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueBudgetContext;
use YesWiki\Kernel\Service\Queue\QueueBudgetFactory;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueCheckpoint;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueueFallback;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueRequestTrigger;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\ThrowableFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTestMessage.php';
require_once 'tests/src/Kernel/QueueTestConsole.php';
require_once 'tests/src/Kernel/QueueTestHolders.php';

/** What the end of a web request does with the queue: consume it (`request`), start a process that does (`request-spawn`), and run what the request pinned, in any mode. */
#[CoversClass(QueueRequestTrigger::class)]
class QueueRequestTriggerTest extends YesWikiTestCase
{
    private const START = '2026-10-23 12:00:00';

    private string $file;
    /** Where the test's locks live, instead of the dev wiki's cache/. */
    private string $lockDir;
    private string $configFile;
    private string|false $previousConfigFile;
    private string|false $previousMode;
    /** @var resource */
    private $log;

    private DbService $db;
    private QueueSchema $schema;
    private MockClock $clock;
    private QueueTransport $transport;
    private QueueStatistics $statistics;
    private QueueConsumer $consumer;
    private Journal $journal;
    private RuntimeLock $locks;

    /** What the test handler does after noting the message; null to just note it. */
    private ?\Closure $behaviour = null;
    /** @var list<string> */
    private array $handled = [];
    /** @var list<string> each step of the end of the request, in order: `finish` when the response is handed over, then each message handled */
    private array $steps = [];
    /** What the server answers when asked to hand the response over early. */
    private bool $canFinishEarly = true;

    private QueueTestConsole $console;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/yw-trigger-' . bin2hex(random_bytes(6)) . '.db';
        $this->lockDir = sys_get_temp_dir() . '/yw-trigger-locks-' . bin2hex(random_bytes(4));
        $this->db = new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywq_',
            'debug' => false,
        ]));
        $this->schema = new QueueSchema($this->db);
        $this->schema->create();
        (new JournalSchema($this->db))->create();

        $this->configFile = sys_get_temp_dir() . '/yw-trigger-config-' . bin2hex(random_bytes(4)) . '.php';
        $this->previousConfigFile = getenv('YESWIKI_CONFIG_FILE');
        putenv('YESWIKI_CONFIG_FILE=' . $this->configFile);
        $configuration = new ConfigurationService();
        $config = $configuration->getConfiguration($this->configFile);
        $config->load();
        $config['wiki_status'] = 'running';
        $this->assertTrue($configuration->write($config));

        $this->previousMode = getenv(QueueSettings::ENV_MODE);
        $this->clock = new MockClock(self::START, 'UTC');
        $this->locks = QueueTestKit::locksIn($this->lockDir);
        $this->build();
    }

    protected function tearDown(): void
    {
        unset($this->consumer, $this->statistics, $this->transport, $this->schema, $this->db);
        putenv($this->previousMode === false ? QueueSettings::ENV_MODE : QueueSettings::ENV_MODE . '=' . $this->previousMode);
        putenv($this->previousConfigFile === false ? 'YESWIKI_CONFIG_FILE' : 'YESWIKI_CONFIG_FILE=' . $this->previousConfigFile);
        @unlink($this->configFile);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
        foreach (glob($this->lockDir . '/cache/*') ?: [] as $lock) {
            @unlink($lock);
        }
        @rmdir($this->lockDir . '/cache');
        @rmdir($this->lockDir);
    }

    public function testInRequestModeAPendingMessageIsHandledAfterTheResponse(): void
    {
        $this->send('m0');

        $this->trigger('request')->afterResponse();

        $this->assertSame(['m0'], $this->handled);
        $this->assertSame(['finish', 'm0'], $this->steps, 'the visitor has the response before the queue runs');
        $this->assertSame(0, $this->statistics->pendingCount());
        $this->assertFalse($this->statistics->lastPass()['beforeResponse'] ?? true);
    }

    /**
     * Shared hosts put ignore_user_abort() or set_time_limit() in `disable_functions`, where PHP 8 throws on a call: the end of the request has to do without them.
     *
     * A disabled function cannot be put back nor taken away inside a process, so the test above
     * runs again in a PHP started with them disabled.
     */
    public function testOnAHostThatDisablesWhatTheEndOfTheRequestWouldCallThePassStillRuns(): void
    {
        $phpunit = new Process([
            PHP_BINARY, '-d', 'disable_functions=ignore_user_abort,set_time_limit',
            'vendor/bin/phpunit', '--do-not-cache-result',
            '--filter', 'testInRequestModeAPendingMessageIsHandledAfterTheResponse$',
            __FILE__,
        ], self::wikiDir());
        $phpunit->setTimeout(60);
        $phpunit->run();

        $this->assertSame(0, $phpunit->getExitCode(), $phpunit->getOutput() . $phpunit->getErrorOutput());
        $this->assertStringContainsString('OK (1 test', $phpunit->getOutput(), 'the test ran, it was not filtered out');
    }

    /**
     * On mod_php, CGI or `php -S` the response is still being written while the pass runs: an `echo` or a displayed warning would end up in the page, or break an API's JSON.
     *
     * Checked with `php -S` (the shutdown function's output reached the client); where the
     * response has been handed over (FPM, LiteSpeed, FrankenPHP), PHP drops what is printed after.
     */
    public function testWhatAPassPrintsWhileTheVisitorWaitsNeverReachesTheResponse(): void
    {
        $this->canFinishEarly = false;
        $this->send('m0');
        $this->behaviour = static function (): void {
            echo str_repeat('leaked ', 2000);
            ob_start(); // a handler that leaves its own buffer open
            echo 'leaked too';
        };

        ob_start();
        $level = ob_get_level();
        $this->trigger('request')->afterResponse();
        $levelAfter = ob_get_level();
        $printed = (string)ob_get_clean();

        $this->assertSame(['m0'], $this->handled);
        $this->assertSame('', $printed);
        $this->assertSame($level, $levelAfter, 'no buffer is left open');
    }

    /**
     * Apache's mod_php may run shutdown functions in another folder than the request (PHP's manual says so of register_shutdown_function()): what a handler finds by a relative path, an extension's class among them, was not found.
     *
     * FPM and `php -S` keep the request's folder (checked); the pass works from the wiki's either way.
     */
    public function testThePassRunsInTheWikisFolderWhereverTheServerLeftTheProcess(): void
    {
        $this->send('m0');
        $seenIn = null;
        $this->behaviour = static function () use (&$seenIn): void {
            $seenIn = getcwd();
        };
        $previous = (string)getcwd();

        chdir(sys_get_temp_dir());
        try {
            $this->trigger('request')->afterResponse();
            $leftIn = getcwd();
        } finally {
            chdir($previous);
        }

        $this->assertSame(self::wikiDir(), $seenIn);
        $this->assertSame(realpath(sys_get_temp_dir()), $leftIn, 'and the process is left where it was found: a worker serves its next request from there');
    }

    public function testWhenTheResponseCannotLeaveEarlyThePassSaysTheVisitorWaited(): void
    {
        $this->canFinishEarly = false;
        $this->send('m0');

        $this->trigger('request')->afterResponse();

        $this->assertSame(['m0'], $this->handled);
        $this->assertTrue($this->statistics->lastPass()['beforeResponse'] ?? false);
    }

    public function testInTheWorkerTheResponseHasAlreadyGone(): void
    {
        $this->canFinishEarly = false;
        $this->send('m0');
        $trigger = $this->trigger('request');
        $trigger->servedByWorker();

        $trigger->afterResponse();

        $this->assertSame(['m0'], $this->steps, 'FrankenPHP sent the response when frankenphp_handle_request() returned: nothing to finish');
        $this->assertFalse($this->statistics->lastPass()['beforeResponse'] ?? true);
    }

    public function testNothingWaitingAndNothingDueCostsNoPass(): void
    {
        $this->scheduleDueIn(3600);

        $this->trigger('request')->afterResponse();

        $this->assertNull($this->statistics->lastPass(), 'no pass row for a request that had nothing to do');
    }

    public function testADelayedRetryIsNotWorkYet(): void
    {
        $this->scheduleDueIn(3600);
        $this->transport->send(new Envelope(new QueueTestMessage('retry'), [new DelayStamp(60000)]));

        $this->trigger('request')->afterResponse();
        $this->trigger('request-spawn')->afterResponse();

        $this->assertNull($this->statistics->lastPass(), 'no pass on every request while a retry waits for its time');
        $this->assertSame([], $this->console->commands);
        $this->assertSame(1, $this->statistics->pendingCount());
    }

    public function testADelayedMessageWhoseTimeHasComeIsWork(): void
    {
        $this->scheduleDueIn(3600);
        $this->transport->send(new Envelope(new QueueTestMessage('retry'), [new DelayStamp(60000)]));
        $this->clock->sleep(60);

        $this->trigger('request')->afterResponse();

        $this->assertSame(['retry'], $this->handled);
    }

    public function testADueScheduleRunsAPassWithNoMessageWaiting(): void
    {
        $this->scheduleDueIn(3600);
        $this->clock->sleep(3600);

        $this->trigger('request')->afterResponse();

        $this->assertNotNull($this->statistics->lastPass());
    }

    public function testAScheduleThatNeverTickedRunsAPass(): void
    {
        $this->trigger('request')->afterResponse();

        $this->assertNotNull($this->statistics->lastPass(), 'the first tick starts the schedule');
    }

    public function testInCommandModeTheRequestConsumesNothing(): void
    {
        $this->send('m0');

        $this->trigger('command')->afterResponse();

        $this->assertSame([], $this->handled);
        $this->assertSame(1, $this->statistics->pendingCount());
        $this->assertSame([], $this->console->commands);
    }

    public function testPinnedMessagesRunInEveryNonBlockingMode(): void
    {
        $this->send('other');
        $pinned = $this->send('pinned');
        $trigger = $this->trigger('api');

        $trigger->pin($pinned);
        $trigger->afterResponse();

        $this->assertSame(['pinned'], $this->handled, 'only the pinned message: `api` consumes nothing at the end of a request');
        $this->assertSame(1, $this->statistics->pendingCount());
    }

    /**
     * Where the response cannot leave before the script ends (mod_php, CGI), the admin would wait for the whole backup, and a wiki fetching one from this one would give up: a process of its own runs it.
     */
    public function testWhenTheResponseCannotLeaveEarlyAPinnedMessageGoesToAProcessOfItsOwn(): void
    {
        $this->canFinishEarly = false;
        $pinned = $this->send('pinned');
        $trigger = $this->trigger('api');

        $trigger->pin($pinned);
        $trigger->afterResponse();

        $this->assertSame([], $this->handled, 'not in this request');
        $this->assertSame(['messenger:drain'], $this->console->commands);
        $this->assertSame([['--message', (string)$pinned]], $this->console->arguments);
        $this->assertSame([true], $this->console->detached, 'detached: the request ends long before the backup');
        $this->assertSame(1, $this->statistics->pendingCount(), 'still waiting, for that process');
    }

    /**
     * @param \Closure(): ?Process $answer
     */
    #[DataProvider('spawnFailures')]
    public function testAPinnedMessageNoProcessCanTakeRunsInTheRequest(\Closure $answer): void
    {
        $this->canFinishEarly = false;
        $this->console->answer = $answer;
        $trigger = $this->trigger('api');

        $trigger->pin($this->send('pinned'));
        $trigger->afterResponse();

        $this->assertSame(['pinned'], $this->handled, 'the admin waits for it, as before there was a queue when no process could be started');
    }

    public function testWhenTheResponseHasLeftAPinnedMessageRunsInTheRequest(): void
    {
        $trigger = $this->trigger('api');

        $trigger->pin($this->send('pinned'));
        $trigger->afterResponse();

        $this->assertSame(['pinned'], $this->handled);
        $this->assertSame([], $this->console->commands, 'no process: nobody waits for this one');
    }

    public function testAPinnedMessageRunsOnce(): void
    {
        $trigger = $this->trigger('api');
        $trigger->pin($this->send('pinned'));

        $trigger->afterResponse();
        $trigger->afterResponse();

        $this->assertSame(['pinned'], $this->handled);
    }

    public function testPinnedMessagesDoNotSurviveTheRequest(): void
    {
        $trigger = $this->trigger('api');
        $trigger->pin($this->send('pinned'));

        $trigger->startNewRequest();
        $trigger->afterResponse();

        $this->assertSame([], $this->handled, 'the next visitor of a FrankenPHP worker does not run what the request before pinned');
        $this->assertSame(1, $this->statistics->pendingCount());
    }

    public function testAHandlerThatThrowsNeverReachesTheVisitor(): void
    {
        $this->behaviour = static function (): void {
            throw new \RuntimeException('handler broke');
        };
        $this->send('m0');
        $trigger = $this->trigger('request');
        $trigger->pin($this->send('pinned'));

        $trigger->afterResponse();

        $this->assertSame(['pinned', 'm0'], $this->handled, 'the pinned message first, then the pass: both broke, and neither stopped the other');
    }

    public function testMissingTablesCostOneJournaledFailure(): void
    {
        $this->schema->drop();

        $this->trigger('request')->afterResponse();

        $journal = $this->journaled();
        $this->assertSame(1, substr_count($journal, '"action":"queue.trigger_failed"'));
        $this->assertStringNotContainsString('queue.pass_failed', $journal, 'the cheap checks failed first: no pass was attempted');
    }

    /** @return iterable<string, array{\Closure(): ?Process}> */
    public static function spawnFailures(): iterable
    {
        yield 'no PHP binary found' => [static fn (): ?Process => null];
        yield 'proc_open disabled' => [static fn (): ?Process => throw new \RuntimeException('The Process class relies on proc_open, which is not available on your PHP installation.')];
    }

    /** @param \Closure(): ?Process $answer */
    #[DataProvider('spawnFailures')]
    public function testASpawnThatCannotStartIsJournaledAndConsumesNothing(\Closure $answer): void
    {
        $this->console->answer = $answer;
        $this->send('m0');
        $trigger = $this->trigger('request-spawn');

        $trigger->afterResponse();
        $trigger->afterResponse();

        $this->assertSame([], $this->handled, 'the fallback takes over once the launch has gone unanswered, not this request');
        $this->assertStringContainsString('"action":"queue.spawn_unavailable"', $this->journaled());
        $this->assertSame(['messenger:drain'], $this->console->commands, 'a failed launch is throttled as a started one');
    }

    public function testInWorkerModeTheRequestConsumesWhenTheWorkerIsGone(): void
    {
        $this->sendSince('m0', 660);
        QueueTestKit::recordPass($this->db, QueueConsumerMode::Worker, $this->at('-2 hours'), $this->at('-11 minutes'), '');

        $this->trigger('worker')->afterResponse();

        $this->assertSame(['m0'], $this->handled);
        $pass = $this->statistics->lastPass();
        $this->assertSame('request', $pass['mode'] ?? null, 'as `request` does: in the visitor\'s process, on its budget');
        $this->assertSame('fallback', $pass['origin'] ?? null);
        $this->assertSame('worker', $pass['fallbackFor'] ?? null);
    }

    public function testInWorkerModeTheRequestConsumesNothingWhileTheWorkerIsAlive(): void
    {
        $this->sendSince('m0', 660);
        QueueTestKit::recordPass($this->db, QueueConsumerMode::Worker, $this->at('-2 hours'), $this->at('-1 minute'), '');

        $this->trigger('worker')->afterResponse();

        $this->assertSame([], $this->handled);
        $this->assertSame('worker', $this->statistics->lastPass()['mode'] ?? null, 'no pass row of its own');
    }

    /** Outside the fallback, a request between two checks of the rule costs a file's `stat`: not even a missing table is noticed. */
    public function testBetweenTwoChecksARequestReadsNothing(): void
    {
        $this->locks->stampAt(QueueFallback::CHECK_STAMP, $this->clock->now()->getTimestamp());
        $this->schema->drop();

        $this->trigger('worker')->afterResponse();

        $this->assertStringNotContainsString('queue.trigger_failed', $this->journaled());
    }

    public function testInRequestSpawnASilentProcessLeadsToFallback(): void
    {
        $this->send('m0');
        $this->trigger('request-spawn')->afterResponse();
        $this->assertSame([], $this->handled, 'the process it started is to do it');

        $this->clock->sleep(301);
        $this->trigger('request-spawn')->afterResponse();

        $this->assertSame(['m0'], $this->handled, 'five minutes on, nothing of that process has been seen');
        $this->assertSame('fallback', $this->statistics->lastPass()['origin'] ?? null);
        $this->assertSame(['messenger:drain'], $this->console->commands, 'the request that consumes leaves the launch to the next one');
    }

    public function testTheRequestThatLaunchesAProcessLeavesThePassToIt(): void
    {
        $this->send('m0');
        QueueTestKit::recordPass($this->db, QueueConsumerMode::Request, $this->at('-10 minutes'), $this->at('-10 minutes'), 'empty', origin: QueuePassOrigin::Fallback, fallbackFor: QueueConsumerMode::RequestSpawn);

        $this->trigger('request-spawn')->afterResponse();

        $this->assertSame(['messenger:drain'], $this->console->commands, 'in fallback, a launch is still tried, to see the process come back');
        $this->assertSame([], $this->handled);
    }

    public function testSpawnIsThrottled(): void
    {
        $this->send('m0');
        $trigger = $this->trigger('request-spawn');

        $trigger->afterResponse();
        $trigger->afterResponse();

        $this->assertSame([], $this->handled, 'the process it started does the work, not the request');
        $this->assertSame(['messenger:drain'], $this->console->commands);
        $this->assertSame([true], $this->console->detached, 'detached: it outlives the request');
    }

    public function testSpawnWithNothingToDoStartsNothing(): void
    {
        $this->scheduleDueIn(3600);

        $this->trigger('request-spawn')->afterResponse();

        $this->assertSame([], $this->console->commands);
    }

    public function testAHibernatedWikiStartsNothing(): void
    {
        $this->send('m0');

        $this->trigger('request-spawn', 'hibernate')->afterResponse();
        $this->trigger('request', 'archiving')->afterResponse();

        $this->assertSame([], $this->console->commands, 'no process started only to find the wiki paused');
        $this->assertSame([], $this->handled);
    }

    /** R18: a pass reaps even while the wiki is paused, so a page view runs one when a dead consumer left a claim: a killed backup holds the wiki `archiving` until then. */
    public function testAHibernatedWikiStillReapsWhatWasKilled(): void
    {
        $this->send('m0');
        $this->transport->claimAs(QueueConsumerMode::Request, 1);
        [$abandoned] = [...$this->transport->get()];
        QueueTestHolders::hand($this->db, $this->schema, (int)$abandoned->last(TransportMessageIdStamp::class)?->getId(), QueueTestHolders::elsewhere());
        $this->send('m1');
        $this->clock->sleep(62);
        $this->setWikiStatus('archiving');

        $this->trigger('request', 'archiving')->afterResponse();

        $this->assertSame([(int)$abandoned->last(TransportMessageIdStamp::class)?->getId()], array_column($this->statistics->failed(), 'id'));
        $this->assertSame([], $this->handled, 'reaped, nothing consumed');
        $this->assertSame(1, $this->statistics->pendingCount());

        $this->trigger('request-spawn', 'archiving')->afterResponse();
        $this->assertSame([], $this->console->commands, 'nothing left to reap: no process started');
    }

    public function testInRequestSpawnAHibernatedWikiStartsAProcessToReap(): void
    {
        $this->send('m0');
        $this->transport->claimAs(QueueConsumerMode::Request, 1);
        [$abandoned] = [...$this->transport->get()];
        QueueTestHolders::hand($this->db, $this->schema, (int)$abandoned->last(TransportMessageIdStamp::class)?->getId(), QueueTestHolders::elsewhere());
        $this->clock->sleep(62);

        $this->trigger('request-spawn', 'hibernate')->afterResponse();

        $this->assertSame(['messenger:drain'], $this->console->commands);
    }

    private function setWikiStatus(string $status): void
    {
        $configuration = new ConfigurationService();
        $config = $configuration->getConfiguration($this->configFile);
        $config->load();
        $config['wiki_status'] = $status;
        $this->assertTrue($configuration->write($config));
    }

    private function trigger(string $mode, string $wikiStatus = 'running'): QueueRequestTrigger
    {
        putenv(QueueSettings::ENV_MODE . '=' . $mode);
        $settings = new QueueSettings(new ParameterBag([]), static fn (): array => []);

        return new QueueRequestTrigger(
            $settings,
            $this->consumer,
            new QueueBudgetFactory(),
            $this->statistics,
            new QueueFallback($settings, $this->statistics, new HibernationService(new ParameterBag(['wiki_status' => $wikiStatus])), $this->locks, $this->clock),
            $this->console,
            $this->locks,
            new HibernationService(new ParameterBag(['wiki_status' => $wikiStatus])),
            $this->journal,
            $this->clock,
            function (): bool {
                $this->steps[] = 'finish';

                return $this->canFinishEarly;
            },
            self::wikiDir(),
        );
    }

    /** The folder the wiki runs in: the repository, as for the dev wiki. */
    private static function wikiDir(): string
    {
        return (string)realpath(__DIR__ . '/../../..');
    }

    private function build(): void
    {
        $this->transport = new QueueTransport($this->db, $this->schema, clock: $this->clock);
        $this->statistics = new QueueStatistics($this->db, $this->schema, new JournalSchema($this->db), $this->transport, $this->clock);
        $this->log = self::memory();
        $this->journal = new Journal(new ServiceLocator([
            DbService::class => fn (): DbService => $this->db,
            JournalSchema::class => fn (): JournalSchema => new JournalSchema($this->db),
            ThrowableFormatter::class => static fn (): ThrowableFormatter => new ThrowableFormatter(),
        ]));
        $this->journal->writeTo($this->log);

        $handler = function (QueueTestMessage $message): void {
            $this->handled[] = $message->value;
            $this->steps[] = $message->value;
            if ($this->behaviour !== null) {
                ($this->behaviour)($message);
            }
        };
        $locator = new QueueHandlerLocator(
            [QueueTestMessage::class => ['handler']],
            new ServiceLocator(['handler' => static fn (): \Closure => $handler])
        );
        $bus = new QueueBus($this->transport, $locator, $this->journal);
        $recorder = new QueueRecorder($this->db, $this->schema, new JournalSchema($this->db), $this->clock);
        $noSchedule = new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [];
            }
        };

        $this->consumer = new QueueConsumer(
            $this->transport,
            $bus,
            $recorder,
            $this->statistics,
            new QueueBudgetContext(),
            new QueueBudgetFactory(),
            new HibernationService(new ParameterBag(['wiki_status' => 'running'])),
            $this->locks,
            $this->journal,
            new QueueScheduleTicker([$noSchedule], $bus, new QueueCheckpoint($this->db, $this->schema, $this->locks), $recorder, $this->clock),
            $this->clock,
        );

        $this->console = new QueueTestConsole();
    }

    /** Queues a test message and answers its id. */
    private function send(string $value): int
    {
        $stamp = $this->transport->send(new Envelope(new QueueTestMessage($value)))->last(TransportMessageIdStamp::class);
        $this->assertNotNull($stamp);

        return (int)$stamp->getId();
    }

    /** Queues a test message as if it had been sent $seconds ago. */
    private function sendSince(string $value, int $seconds): void
    {
        $this->clock->modify("-{$seconds} seconds");
        $this->send($value);
        $this->clock->modify("+{$seconds} seconds");
    }

    private function at(string $relative): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->modify($relative)->format('Y-m-d H:i:s');
    }

    /** The schedule ticked now, and is next due in $seconds. */
    private function scheduleDueIn(int $seconds): void
    {
        $checkpoint = new QueueCheckpoint($this->db, $this->schema, $this->locks);
        $now = $this->clock->now();
        $this->assertTrue($checkpoint->acquire($now));
        $checkpoint->release($now, $now->modify("+{$seconds} seconds"));
    }

    /** Everything the Journal wrote, as its JSON lines. */
    private function journaled(): string
    {
        rewind($this->log);

        return (string)stream_get_contents($this->log);
    }

    /** @return resource */
    private static function memory()
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);

        return $stream;
    }
}
