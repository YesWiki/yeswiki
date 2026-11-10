<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Process\Process;
use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Command\QueueConsumeCommand;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\Lock\FileLocks;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueueFallback;
use YesWiki\Kernel\Service\Queue\QueuePassLimitsFactory;
use YesWiki\Kernel\Service\Queue\QueueRequestTrigger;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTestMessage.php';
require_once 'tests/src/Kernel/QueueTestTask.php';
require_once 'tests/src/Kernel/QueueTestConsole.php';
require_once 'tests/src/Kernel/QueueTestHolders.php';
require_once 'tests/src/Kernel/QueueTestSenderMessage.php';

/** What the end of a web request does with the queue: consume it (`request`), stand in for a consumer that is gone (the fallback), and run what the request pinned, in any mode. */
#[CoversClass(QueueRequestTrigger::class)]
class QueueRequestTriggerTest extends YesWikiTestCase
{
    /** The `queue_consumer` the test runs under; the settings read it as the parameter the environment resolved. */
    private string $mode = '';

    private const START = '2026-10-23 12:00:00';

    private QueueTestKit $kit;
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

    /** @var array<string, int> task id => interval in seconds: what the kit's schedule declares, read at each call */
    private array $scheduled = [];
    /** @var list<string> */
    private array $handled = [];
    /** @var list<string> each step of the end of the request, in order: `finish` when the response is handed over, then each message handled */
    private array $steps = [];
    /** What the server answers when asked to hand the response over early. */
    private bool $canFinishEarly = true;

    private QueueTestConsole $console;

    /** @var list<int> what the end of the request asked of set_time_limit() */
    private array $timeLimitsAsked = [];

    protected function setUp(): void
    {
        $this->clock = new MockClock(new \DateTimeImmutable(self::START));
        $this->kit = new QueueTestKit([QueueTestMessage::class => function (QueueTestMessage $message): void {
            $this->handled[] = $message->value;
            $this->steps[] = $message->value;
            if ($this->behaviour !== null) {
                ($this->behaviour)($message);
            }
        }, QueueTestSenderMessage::class => function (QueueTestSenderMessage $message): void {
            $this->handled[] = $message->value;
        }], $this->clock, schedule: new class(fn (): array => $this->scheduled) implements ProvidesSchedule {
            /** @param \Closure(): array<string, int> $scheduled */
            public function __construct(private \Closure $scheduled)
            {
            }

            public function recurringMessages(): array
            {
                $recurring = [];
                foreach (($this->scheduled)() as $id => $every) {
                    $recurring[] = RecurringMessage::every($every, new QueueTestTask((string)$id));
                }

                return $recurring;
            }
        }, passLimits: $this->passLimitsThatRecordTheClimb());
        $this->db = $this->kit->db;
        $this->schema = $this->kit->schema;
        $this->transport = $this->kit->transport;
        $this->statistics = $this->kit->statistics;
        $this->consumer = $this->kit->consumer;
        $this->journal = $this->kit->journal;
        $this->locks = $this->kit->locks;
        $this->log = self::memory();
        $this->journal->writeTo($this->log);
        $this->console = new QueueTestConsole();
    }

    protected function tearDown(): void
    {
        unset($this->consumer, $this->statistics, $this->transport, $this->schema, $this->db);
        $this->kit->dispose();
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

    /** The visitor's session is released before the response leaves and before any handler runs: a pass must not lock the next request of the same visitor. */
    public function testTheSessionIsReleasedBeforeTheResponseLeavesAndTheHandlersRun(): void
    {
        $statusBefore = session_status();
        $hadSession = $statusBefore === PHP_SESSION_ACTIVE;
        if ($hadSession) {
            session_write_close();
        }
        @session_start();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->markTestSkipped('a session cannot be opened here');
        }
        $this->send('m0');
        $sessionAt = [];
        $this->behaviour = static function () use (&$sessionAt): void {
            $sessionAt['handler'] = session_status();
        };
        $this->mode = 'request';
        $trigger = $this->kit->requestTrigger(
            QueueTestKit::settings($this->mode),
            $this->console,
            static function () use (&$sessionAt): bool {
                $sessionAt['response'] = session_status();

                return true;
            },
            self::wikiDir(),
            new HibernationService(new ParameterBag(['wiki_status' => 'running'])),
        );

        try {
            $trigger->afterResponse();
        } finally {
            // Unconditional: the trigger may already have closed it, and closing none is a no-op.
            session_write_close();
            if ($hadSession) {
                @session_start();
            }
        }

        $this->assertSame(['m0'], $this->handled);
        $this->assertNotSame(PHP_SESSION_ACTIVE, $sessionAt['response'] ?? PHP_SESSION_ACTIVE, 'released before the response is handed over');
        $this->assertNotSame(PHP_SESSION_ACTIVE, $sessionAt['handler'] ?? PHP_SESSION_ACTIVE, 'and while the handlers run');
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

        $this->assertNull($this->statistics->lastPass(), 'no pass on every request while a retry waits for its time');
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
        $this->cronSeen();
        $this->send('m0');

        $this->trigger('api')->afterResponse();

        $this->assertSame([], $this->handled);
        $this->assertSame(1, $this->statistics->pendingCount());
        $this->assertSame([], $this->console->commands);
    }

    public function testPinnedMessagesRunInEveryNonBlockingMode(): void
    {
        $this->cronSeen();
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
        $this->cronSeen();
        $this->canFinishEarly = false;
        $pinned = $this->send('pinned');
        $trigger = $this->trigger('api');

        $trigger->pin($pinned);
        $trigger->afterResponse();

        $this->assertSame([], $this->handled, 'not in this request');
        $this->assertSame(['messenger:consume'], $this->console->commands);
        $this->assertSame([['--message', (string)$pinned]], $this->console->arguments);
        $this->assertSame([true], $this->console->detached, 'detached: the request ends long before the backup');
        $this->assertSame(1, $this->statistics->pendingCount(), 'still waiting, for that process');
    }

    /**
     * Why a process cannot be started: the server has no PHP binary to give it, or forbids proc_open().
     *
     * @return iterable<string, array{\Closure(): ?Process}>
     */
    public static function processFailures(): iterable
    {
        yield 'no PHP binary found' => [static fn (): ?Process => null];
        yield 'proc_open disabled' => [static fn (): ?Process => throw new \RuntimeException('The Process class relies on proc_open, which is not available on your PHP installation.')];
    }

    /**
     * @param \Closure(): ?Process $answer
     */
    #[DataProvider('processFailures')]
    public function testAPinnedMessageNoProcessCanTakeRunsInTheRequest(\Closure $answer): void
    {
        $this->canFinishEarly = false;
        $this->console->answer = $answer;
        $trigger = $this->trigger('api');

        $trigger->pin($this->send('pinned'));
        $trigger->afterResponse();

        $this->assertSame(['pinned'], $this->handled, 'the admin waits for it, as before there was a queue when no process could be started');
    }

    /** A pinned backup has PHP's limit raised step by step to the ceiling, never removed. */
    public function testAPinnedMessageRunsUnderARaisedLimit(): void
    {
        $trigger = $this->trigger('api');

        $trigger->pin($this->send('pinned'));
        $trigger->afterResponse();

        $this->assertSame(['pinned'], $this->handled);
        $this->assertSame([60, 120, 240, 480, 960, 1200], $this->timeLimitsAsked);
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

    /**
     * In `request`, the end of the admin's request takes the backup with the rest of the queue, wherever the response leaves first (FPM, FrankenPHP, LiteSpeed).
     * On mod_php or CGI it would keep the admin waiting for the whole backup, and a wiki fetching one by `api/archives` would give up: it is pinned, and a process of its own runs it, or the request itself when none can start.
     */
    public function testInRequestModeAMessageForItsSenderIsPinnedOnlyWhereTheResponseCannotLeaveFirst(): void
    {
        $early = $this->trigger('request');
        $this->assertFalse($early->runsItself(), 'the response leaves first: the end of the request takes it as any other message');

        $this->sendForItsSender('backup on FPM');
        $early->afterResponse();

        $this->assertSame(['backup on FPM'], $this->handled, 'taken by the pass of the admin\'s request');
        $this->assertSame([], $this->console->commands);

        $this->handled = [];
        $this->canFinishEarly = false;
        $late = $this->trigger('request');
        $this->assertTrue($late->runsItself(), 'the response cannot leave before the end of the script');

        $detached = $this->sendForItsSender('backup on mod_php');
        $late->afterResponse();

        $this->assertSame([], $this->handled, 'not in the admin\'s request');
        $this->assertSame(['messenger:consume'], $this->console->commands);
        $this->assertSame([['--message', (string)$detached]], $this->console->arguments);
        $this->assertSame([true], $this->console->detached, 'detached: the request ends long before the backup');
        $this->assertSame('pending', $this->statistics->messageState($detached), 'held for that process, which no other pass takes');

        $this->console = new QueueTestConsole();
        $this->console->answer = static fn (): ?Process => null;
        $nowhere = $this->trigger('request');
        $here = $this->sendForItsSender('backup with no process');
        $nowhere->afterResponse();

        $this->assertSame(['backup with no process'], $this->handled, 'no process could start: the admin\'s request runs it');
        $this->assertSame(['messenger:consume'], $this->console->commands, 'after asking for one');
        $this->assertSame('handled', $this->statistics->messageState($here));
        $this->assertSame('pending', $this->statistics->messageState($detached), 'the one handed to a process is still that process\'s');

        $this->handled = [];
        $process = new CommandTester(new QueueConsumeCommand($this->kit->services(QueueTestKit::settings($this->mode))));
        $process->execute(['--message' => (string)$detached]);

        $this->assertSame(0, $process->getStatusCode(), $process->getDisplay());
        $this->assertSame(['backup on mod_php'], $this->handled, 'the process it was handed to takes it, held as it is');
        $this->assertSame(['request'], array_values(array_unique(array_column($this->statistics->recentPasses(10), 'mode'))), 'every pass that ran is `request`');
    }

    /** The cron or the API call only comes when called: the admin's request runs the backup, after its response or in a process of its own. */
    public function testInApiModeAMessageForItsSenderIsAlwaysPinned(): void
    {
        $early = $this->trigger('api');
        $this->assertTrue($early->runsItself());

        $this->sendForItsSender('backup on FPM');
        $early->afterResponse();

        $this->assertSame(['backup on FPM'], $this->handled, 'run once the response has left');
        $this->assertSame([], $this->console->commands);
        $this->assertSame('request', $this->statistics->lastPass()['mode'] ?? null, 'run for its sender, not by the cron');
        $this->assertNull($this->statistics->lastSeenAt(QueueConsumerMode::Api), 'the cron is not seen for it');

        $this->canFinishEarly = false;
        $late = $this->trigger('api');
        $this->assertTrue($late->runsItself());

        $detached = $this->sendForItsSender('backup on mod_php');
        $late->afterResponse();

        $this->assertSame([['--message', (string)$detached]], $this->console->arguments);
    }

    /** A worker takes it as any other message; if it is gone, the fallback does. */
    public function testInWorkerModeAMessageForItsSenderIsNeverPinned(): void
    {
        QueueTestKit::recordPass($this->db, QueueConsumerMode::Worker, $this->at('-1 hour'), $this->at('-1 minute'), '');
        foreach ([true, false] as $canFinishEarly) {
            $this->canFinishEarly = $canFinishEarly;
            $trigger = $this->trigger('worker');
            $this->assertFalse($trigger->runsItself());

            $id = $this->sendForItsSender('backup');
            $trigger->afterResponse();

            $this->assertSame([], $this->handled);
            $this->assertSame([], $this->console->commands);
            $this->assertLessThanOrEqual($this->clock->now()->getTimestamp(), $this->statistics->oldestPendingAt()?->getTimestamp() ?? PHP_INT_MAX, 'not held for its sender: the worker may take it at once');
            $this->assertSame('pending', $this->statistics->messageState($id));
        }

        $this->clock->sleep(600);
        $this->canFinishEarly = true;
        $this->trigger('worker')->afterResponse();

        $this->assertSame(['backup', 'backup'], $this->handled, 'the worker gone, the end of the request stands in for it');
    }

    public function testPinnedMessagesDoNotSurviveTheRequest(): void
    {
        $this->cronSeen();
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

    public function testInWorkerModeTheRequestConsumesWhenTheWorkerIsGone(): void
    {
        $this->sendSince('m0', 660);
        QueueTestKit::recordPass($this->db, QueueConsumerMode::Worker, $this->at('-2 hours'), $this->at('-11 minutes'), '');

        $this->trigger('worker')->afterResponse();

        $this->assertSame(['m0'], $this->handled);
        $this->assertSame('request', $this->statistics->lastPass()['mode'] ?? null, 'as `request` does: in the visitor\'s process');
        $this->assertSame($this->at('-11 minutes'), $this->statistics->lastSeenAt(QueueConsumerMode::Worker)?->format('Y-m-d H:i:s'), 'standing in for the worker does not make it seen');
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

    /** `archiving` is a backup under way, in another process that holds the archive lock. */
    public function testAHibernatedWikiConsumesNothing(): void
    {
        $this->send('m0');

        $this->setWikiStatus('hibernate');
        $this->trigger('request', 'hibernate')->afterResponse();
        $letGo = QueueTestHolders::lockHeldByAnotherProcess($this->kit->lockDir . '/' . FileLocks::pathOf(HibernationService::ARCHIVING_LOCK));
        $this->setWikiStatus('archiving');
        try {
            $this->trigger('request', 'archiving')->afterResponse();
        } finally {
            $this->assertSame(0, $letGo());
        }

        $this->assertSame([], $this->handled);
        $this->assertSame([], $this->statistics->openPasses(), 'and starts no pass for nothing');
    }

    /**
     * A backup that died left `archiving`, and every request since boots with it: nobody opens the backups page, yet the end of the next request finds
     * the archive lock free, lifts the status and consumes.
     */
    public function testAWikiADeadBackupLeftArchivingConsumesAtItsNextRequest(): void
    {
        $this->send('m0');
        $this->setWikiStatus('archiving');

        $this->trigger('request', 'archiving')->afterResponse();

        $this->assertSame(['m0'], $this->handled);
        $this->assertSame('running', $this->wikiStatus());
    }

    private function setWikiStatus(string $status): void
    {
        $configuration = new ConfigurationService();
        $config = $configuration->getConfiguration($this->kit->configFile());
        $config->load();
        $config['wiki_status'] = $status;
        $this->assertTrue($configuration->write($config));
    }

    /** What the config file says of the status now, `running` when it says nothing. */
    private function wikiStatus(): string
    {
        $config = (new ConfigurationService())->getConfiguration($this->kit->configFile());
        $config->load();

        return trim((string)($config['wiki_status'] ?? '')) ?: 'running';
    }

    /** The end of a request whose container booted with $wikiStatus, reading the kit's archive lock. */
    private function trigger(string $mode, string $wikiStatus = 'running'): QueueRequestTrigger
    {
        $this->mode = $mode;

        return $this->kit->requestTrigger(
            QueueTestKit::settings($this->mode),
            $this->console,
            function (): bool {
                $this->steps[] = 'finish';

                return $this->canFinishEarly;
            },
            self::wikiDir(),
            new HibernationService(new ParameterBag(['wiki_status' => $wikiStatus]), new FileLocks($this->locks)),
            fn (): bool => $this->canFinishEarly,
        );
    }

    /** PHP's limits as a server gives them, 30 s for a request that just began; what set_time_limit() is asked is noted. */
    private function passLimitsThatRecordTheClimb(): QueuePassLimitsFactory
    {
        $ini = ['max_execution_time' => '30', 'memory_limit' => '-1'];

        return new QueuePassLimitsFactory(
            new NativeClock(),
            static function (string $name) use (&$ini): string {
                return $ini[$name];
            },
            microtime(true),
            function (int $seconds) use (&$ini): bool {
                $this->timeLimitsAsked[] = $seconds;
                $ini['max_execution_time'] = (string)$seconds;

                return true;
            }
        );
    }

    /** The cron of `api` passed a minute ago: the end of requests has nothing to stand in for. */
    private function cronSeen(): void
    {
        QueueTestKit::recordPass($this->db, QueueConsumerMode::Api, $this->at('-1 minute'), $this->at('-1 minute'), 'empty');
    }

    /** Sends a message that runs for its sender as an admin's backup is sent, through the bus, and answers its id. */
    private function sendForItsSender(string $value): int
    {
        $stamp = $this->kit->bus->dispatch(new QueueTestSenderMessage($value))->last(TransportMessageIdStamp::class);
        $this->assertNotNull($stamp);

        return (int)$stamp->getId();
    }

    /** The folder the wiki runs in: the repository, as for the dev wiki. */
    private static function wikiDir(): string
    {
        return (string)realpath(__DIR__ . '/../../..');
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
        return $this->clock->now()->modify($relative)->format('Y-m-d H:i:s');
    }

    /** The schedule's one task was dispatched now, and is next due in $seconds. */
    private function scheduleDueIn(int $seconds): void
    {
        $this->scheduled = ['test-task' => $seconds];
        $this->kit->recorder->taskDispatched(new QueueTestTask());
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
