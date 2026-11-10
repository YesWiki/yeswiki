<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Admin\Api\RemoteBackupApiController;
use YesWiki\Admin\Message\AdvanceRemoteBackup;
use YesWiki\Admin\Message\AdvanceRemoteBackupHandler;
use YesWiki\Admin\Service\ArchiveRuns;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Admin\Service\RemoteBackupService;
use YesWiki\Admin\Service\RemoteWikiArchive;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassLimits;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueRequestTrigger;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Test\Kernel\QueueTestConsole;
use YesWiki\Test\Kernel\QueueTestHolders;
use YesWiki\Test\Kernel\QueueTestKit;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTestConsole.php';
require_once 'tests/src/Kernel/QueueTestHolders.php';
require_once 'tests/src/Admin/AdminHandlersRefusingBus.php';

/**
 * Fetching a backup from another wiki goes on through the queue, one message per step or download slice, so it finishes with no tab open (ADR-0030).
 *
 * The queue is a throw-away SQLite file on the test's clock, the backups folder a throw-away
 * folder, the wiki's status a throw-away configuration file, and the wiki is in `request` mode,
 * the default: what moves the fetch is the end of page views. The remote wiki is a stand-in that
 * walks through the steps of RemoteWikiArchive and notes each one it is asked to take.
 */
#[CoversClass(RemoteBackupService::class)]
#[CoversClass(AdvanceRemoteBackup::class)]
#[CoversClass(AdvanceRemoteBackupHandler::class)]
#[CoversClass(RemoteBackupApiController::class)]
class RemoteBackupQueueTest extends YesWikiTestCase
{
    /** The `queue_consumer` the test runs under; the settings read it as the parameter the environment resolved. */
    private string $mode = '';

    private const STORED = '2026-08-20T13-29-28_remote-org_archive.zip';

    /** The steps a whole fetch takes, the download in two slices. */
    private const EVERY_STEP = ['checking', 'starting', 'archiving', 'identifying', 'downloading', 'downloading', 'cleaning'];

    private QueueTestKit $kit;
    private string $root;

    private DbService $db;
    private QueueSchema $schema;
    private MockClock $clock;
    private QueueTransport $transport;
    private QueueStatistics $statistics;
    private QueueConsumer $consumer;
    private QueueRequestTrigger $trigger;
    private Storage $storage;
    private ArchiveService $archives;
    private StepByStepRemoteWiki $remote;
    private RemoteBackupService $service;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yw-remote-root-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/' . ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP, 0777, true);
        $this->storage = Storage::rootedAt($this->root);

        $this->clock = new MockClock(new \DateTimeImmutable('2026-10-23 12:00:00'));
        $this->kit = new QueueTestKit(
            [AdvanceRemoteBackup::class => fn (AdvanceRemoteBackup $step) => (new AdvanceRemoteBackupHandler($this->service))($step)],
            $this->clock,
        );
        $this->mode = 'request';
        $this->build();
    }

    protected function tearDown(): void
    {
        $this->kit->dispose();
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $path => $file) {
            $file->isDir() ? @rmdir($path) : @unlink($path);
        }
        @rmdir($this->root);
    }

    /** Starting only queues: the steps are the queue's to take, not the admin's request's. */
    public function testStartingQueuesTheFirstStep(): void
    {
        $state = $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');

        $this->assertTrue($state['running']);
        $this->assertSame([], $this->remote->steps, 'no step taken in the request that started it');
        $this->assertSame(1, $this->statistics->pendingCount());
        $this->assertSame([AdvanceRemoteBackup::class], $this->waitingTypes());
        $this->assertEquals($this->clock->now(), $this->statistics->oldestPendingAt(), 'to be taken at once');
    }

    /** Spec 9.6: each message takes one step or one slice and queues the next, and page views of anyone carry the fetch to its end. */
    public function testEachStepQueuesTheNext(): void
    {
        $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');

        $this->visitUntilDone();

        $this->assertSame(self::EVERY_STEP, $this->remote->steps);
        $this->assertCount(\count(self::EVERY_STEP), array_filter(
            $this->statistics->history(50, QueueRecorder::HANDLED),
            static fn (array $attempt): bool => $attempt['type'] === AdvanceRemoteBackup::class
        ), 'one message per step');
        $this->assertSame(0, $this->statistics->pendingCount(), 'and none once done');
        $this->assertSame([], $this->statistics->failed());

        $state = $this->service->status();
        $this->assertFalse($state['running']);
        $this->assertSame(self::STORED, $state['filename'], 'the browser that polls learns where the backup went');
        $this->assertSame('PK-half-rest', $this->storage->read(ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . '/' . self::STORED));
        $this->assertContains(self::STORED, array_column($this->archives->getArchives(), 'filename'));
    }

    /** While the remote wiki makes its archive there is nothing to do but look again later: the look is delayed, the download is not. */
    public function testWaitingStepsAreDelayed(): void
    {
        $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');

        $this->visit();
        $this->assertSame(['checking'], $this->remote->steps, 'one step per pass: the next one, queued during the pass, waits for the next');
        $this->assertEquals($this->clock->now(), $this->statistics->oldestPendingAt(), 'with no delay');

        $this->visit();
        $this->assertSame(['checking', 'starting'], $this->remote->steps);
        $this->assertSame(1, $this->statistics->pendingCount());
        $this->assertEquals(
            $this->clock->now()->modify('+' . RemoteWikiArchive::SETTLE_SECONDS . ' seconds'),
            $this->statistics->oldestPendingAt(),
            'polling the remote archive waits ' . RemoteWikiArchive::SETTLE_SECONDS . ' s'
        );

        $this->visit();
        $this->assertSame(['checking', 'starting'], $this->remote->steps, 'a page view before then takes nothing');

        $this->clock->sleep(RemoteWikiArchive::SETTLE_SECONDS);
        $this->visit();
        $this->assertSame(['checking', 'starting', 'archiving'], $this->remote->steps);
        $this->assertGreaterThan($this->clock->now(), $this->statistics->oldestPendingAt(), 'identifying the archive waits too');

        $this->clock->sleep(RemoteWikiArchive::SETTLE_SECONDS);
        for ($left = count(self::EVERY_STEP) - 3; $left > 0; $left--) {
            $this->visit();
        }
        $this->assertSame(self::EVERY_STEP, $this->remote->steps, 'from the download on, each slice follows the last at the next page view, with no wait');
    }

    /**
     * A slice that failed (the remote wiki blinked) is tried again after a pause that grows with each failure, not at once: back to back, the attempts would all fall in the same few seconds.
     */
    public function testADownloadThatFailedIsTriedAgainAfterAPause(): void
    {
        $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');
        $this->remote->toStep = 'downloading';
        $this->remote->failingSlices = 2;

        $this->visit();
        $this->assertSame(['downloading'], $this->remote->steps);
        $this->assertEquals($this->clock->now()->modify('+' . RemoteBackupService::RETRY_PAUSE_SECONDS . ' seconds'), $this->statistics->oldestPendingAt(), 'tried again after a pause');
        $this->visit();
        $this->assertSame(['downloading'], $this->remote->steps, 'not before');

        $this->clock->sleep(RemoteBackupService::RETRY_PAUSE_SECONDS);
        $this->visit();
        $this->assertSame(['downloading', 'downloading'], $this->remote->steps);
        $this->assertEquals($this->clock->now()->modify('+' . 2 * RemoteBackupService::RETRY_PAUSE_SECONDS . ' seconds'), $this->statistics->oldestPendingAt(), 'a longer pause after a second failure');

        $this->clock->sleep(2 * RemoteBackupService::RETRY_PAUSE_SECONDS);
        $this->visitUntilDone();
        $this->assertSame(['downloading', 'downloading', 'downloading', 'downloading', 'cleaning'], $this->remote->steps, 'once a slice went through, the next one follows at once');
        $this->assertSame(self::STORED, $this->service->status()['filename']);
    }

    /** A slice of download is 20 s, run by hand or in a pass: a pass has no time limit to cut it to. */
    public function testADownloadSliceIsTwentySeconds(): void
    {
        $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');
        $this->remote->toStep = 'downloading';

        (new AdvanceRemoteBackupHandler($this->service))(new AdvanceRemoteBackup());
        $this->assertSame(['downloading'], $this->remote->steps);

        $this->consumer->drain(QueueConsumerMode::Request, new QueuePassLimits(PHP_INT_MAX));
        $this->consumer->drain(QueueConsumerMode::Request, new QueuePassLimits(PHP_INT_MAX));

        $this->assertSame(['downloading', 'downloading', 'cleaning'], $this->remote->steps, 'a step per pass');
        $this->assertSame(array_fill(0, 3, RemoteBackupService::SLICE_SECONDS), $this->remote->slices, 'the full slice each time, by hand or in a pass');
        $this->assertSame(20, RemoteBackupService::SLICE_SECONDS);
    }

    /** No retry (the step already gave up on the remote wiki), the queue's failure carries the reason, and the polling browser hears it too. */
    public function testAFailureStopsTheChainAndIsVisible(): void
    {
        $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');
        $this->remote->failAt = 'downloading';

        $this->visitUntilDone();

        $this->assertSame(['checking', 'starting', 'archiving', 'identifying', 'downloading'], $this->remote->steps);
        $this->assertSame(0, $this->statistics->pendingCount(), 'nothing queued after the failure');
        $failed = $this->statistics->failed();
        $this->assertCount(1, $failed);
        $this->assertSame(AdvanceRemoteBackup::class, $failed[0]['type']);
        $this->assertStringContainsString('disk full', (string)$failed[0]['error']);
        $this->assertSame(0, $failed[0]['attempts'], 'never retried');
        $this->assertCount(1, $this->remote->abandoned, 'the remote archive is tidied up');
        $this->assertSame([], glob($this->root . '/' . ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . '/*.part'), 'and the half download removed');

        $state = $this->service->status();
        $this->assertFalse($state['running']);
        $this->assertStringContainsString('disk full', $state['error'] ?? '');

        (new AdvanceRemoteBackupHandler($this->service))(new AdvanceRemoteBackup());
        $this->assertCount(5, $this->remote->steps, 'the failed step retried from the jobs page finds the fetch given up, and does nothing');
        $afterRetry = $this->service->status();
        $this->assertSame($state, $afterRetry);

        $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');
        $this->assertTrue($this->service->status()['running'], 'a failed fetch does not block the next one');
    }

    /** POST `advance` is what the page used to drive the fetch with: it now only says where the fetch is. */
    public function testTheBrowserNoLongerDrivesTheJob(): void
    {
        $started = $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');

        $response = $this->controller(Request::create('/?api/remotebackup', 'POST', ['action' => 'advance']))->remoteBackupAction();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($started, json_decode((string)$response->getContent(), true));
        $this->assertSame([], $this->remote->steps, 'no step taken by the browser');
        $this->assertSame(1, $this->statistics->pendingCount(), 'the queued step is still the only one');

        $response = $this->controller(Request::create('/?api/remotebackup', 'GET'))->getRemoteBackupStatus();
        $this->assertSame($started, json_decode((string)$response->getContent(), true), 'GET says the same');
        $this->assertSame([], $this->remote->steps);
    }

    public function testASecondStartIsRefusedWhileOneRuns(): void
    {
        $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');
        $this->visit();

        try {
            $this->service->start('https://other.org', 'RemoteAdmin', 'secret');
            $this->fail('a second fetch was started over the first');
        } catch (\Exception $refused) {
            $this->assertStringContainsString('already running', $refused->getMessage());
        }
        $this->assertSame(1, $this->statistics->pendingCount());
        $this->assertSame(['https://remote.org'], $this->remote->started);
    }

    /**
     * The step left waiting by a fetch given up is not a second chain: the new fetch's first step joins it, and runs the new fetch.
     *
     * Both carry the key `remote-backup`, and the queue folds a send into the waiting message of
     * the same key: one message, one step at a time on the one job file there is.
     */
    public function testAStartAfterACancelJoinsTheStepStillWaiting(): void
    {
        $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');
        $this->visit();
        $this->service->cancel();

        $this->service->start('https://other.org', 'RemoteAdmin', 'secret');
        $this->assertSame(1, $this->statistics->pendingCount(), 'one message for the one fetch there is');

        $this->visitUntilDone();
        $this->assertSame(array_merge(['checking'], self::EVERY_STEP), $this->remote->steps);
        $this->assertSame('https://other.org', $this->service->status()['source']);
        $this->assertSame(0, $this->statistics->pendingCount());
    }

    /** Given up while a step runs in the queue, the fetch stays given up: the step does not write its job back, nor queue another. */
    public function testACancelDuringAStepIsNotUndone(): void
    {
        $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');
        $this->remote->onStep = function (string $step): void {
            if ($step === 'starting') {
                $this->service->cancel();
            }
        };

        $this->visitUntilDone();

        $this->assertSame(['checking', 'starting'], $this->remote->steps);
        $this->assertSame(['step' => RemoteBackupService::STEP_IDLE, 'running' => false], $this->service->status());
        $this->assertSame(0, $this->statistics->pendingCount());
        $this->assertCount(2, $this->remote->abandoned, 'what the cancel saw, and what the step went on to ask the remote for');
    }

    /** A step whose process died (PHP's limit, a crash) leaves its job at that step: the browser is told, not left watching "in progress" for ever. */
    public function testAKilledStepIsNotShownAsRunning(): void
    {
        $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');
        $this->transport->claimAs(QueueConsumerMode::Worker);
        $this->transport->workFor(QueueTestHolders::passToken());
        $taken = iterator_to_array($this->transport->get());
        $this->assertCount(1, $taken);
        // A consumer on another machine: its limit decides.
        QueueTestHolders::hand($this->db, $this->schema, (int)$taken[0]->last(TransportMessageIdStamp::class)?->getId(), QueueTestHolders::token());
        $underWay = $this->service->status();
        $this->assertTrue($underWay['running'], 'under way while its claim holds');

        $this->clock->sleep(QueueTransport::ABSENT_HOLDER_SECONDS + 1);

        $state = $this->service->status();
        $this->assertFalse($state['running']);
        $this->assertSame(_t('ADMIN_BACKUPS_REMOTE_STOPPED'), $state['error']);

        $this->service->start('https://other.org', 'RemoteAdmin', 'secret');
        $this->assertCount(1, $this->remote->abandoned, 'a new fetch tidies up after the dead one');
        $this->assertTrue($this->service->status()['running']);
    }

    /** The queue did not take the first step: the fetch is given up at once, rather than left "running" with nothing to move it. */
    public function testAFetchTheQueueRefusesIsGivenUp(): void
    {
        $bus = new AdminHandlersRefusingBus();

        try {
            $this->serviceOn($bus)->start('https://remote.org', 'RemoteAdmin', 'secret');
            $this->fail('a fetch nothing will run was accepted');
        } catch (\Exception $refused) {
            $this->assertStringContainsString('could not take the fetch', $refused->getMessage());
        }

        $this->assertCount(1, $bus->asked);
        $this->assertSame(['step' => RemoteBackupService::STEP_IDLE, 'running' => false], $this->service->status(), 'no job file left behind');
        $this->assertCount(1, $this->remote->abandoned, 'the remote archive is tidied up');
        $this->assertTrue($this->service->start('https://remote.org', 'RemoteAdmin', 'secret')['running'], 'and the next fetch is not blocked');
    }

    /** The step ran but the next could not be queued: the chain would stop silently with the job "running" for ever, so the message fails with the reason. */
    public function testAStepWhoseSuccessorTheQueueRefusesFailsTheMessage(): void
    {
        $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');
        $refusing = $this->serviceOn(new AdminHandlersRefusingBus());

        try {
            (new AdvanceRemoteBackupHandler($refusing))(new AdvanceRemoteBackup());
            $this->fail('the chain stopped without a word');
        } catch (UnrecoverableMessageHandlingException $stopped) {
            $this->assertStringContainsString('could not be queued', $stopped->getMessage());
        }

        $this->assertSame(['checking'], $this->remote->steps, 'the step itself was taken');
    }

    public function testThereIsNoNextStepToQueueForAFetchThatIsNotRunning(): void
    {
        $this->assertFalse($this->service->queueNextStep());
        $this->assertSame(0, $this->statistics->pendingCount());
    }

    /** What a finished or failed fetch left is cleared by giving up, so the status goes back to idle. */
    public function testGivingUpAFinishedFetchClearsItsStatus(): void
    {
        $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');
        $this->visitUntilDone();
        $this->assertSame(self::STORED, $this->service->status()['filename']);

        $this->assertSame(['step' => RemoteBackupService::STEP_IDLE, 'running' => false], $this->service->cancel());

        $this->assertSame(['step' => RemoteBackupService::STEP_IDLE, 'running' => false], $this->service->status());
        $this->assertSame([], $this->remote->abandoned, 'a finished fetch has nothing left on the remote wiki to tidy');
    }

    public function testANewFetchStartsOverAFinishedOne(): void
    {
        $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');
        $this->visitUntilDone();

        $this->service->start('https://other.org', 'RemoteAdmin', 'secret');

        $state = $this->service->status();
        $this->assertTrue($state['running']);
        $this->assertSame(['https://remote.org', 'https://other.org'], $this->remote->started);
    }

    /** A step carries nothing: sending it again from the jobs page would start nothing the fetch's job file does not already say. */
    public function testAStepOfARemoteBackupCannotBeReplayedByHand(): void
    {
        $this->assertFalse(QueueTransport::replayableByHand(AdvanceRemoteBackup::class));
    }

    /** A step that failed in the queue (rather than died) without the job hearing of it: no step will follow, and the browser is told. */
    public function testAMessageThatFailedLeavesNoFetchShownAsRunning(): void
    {
        $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');
        $this->transport->claimAs(QueueConsumerMode::Worker);
        $this->transport->workFor(QueueTestHolders::passToken());
        $taken = iterator_to_array($this->transport->get());
        $this->assertCount(1, $taken);
        $this->transport->moveToFailed((int)$taken[0]->last(TransportMessageIdStamp::class)?->getId(), 'RuntimeException: the handler broke');

        $state = $this->service->status();

        $this->assertFalse($state['running']);
        $this->assertSame(_t('ADMIN_BACKUPS_REMOTE_STOPPED'), $state['error']);
        $this->service->start('https://other.org', 'RemoteAdmin', 'secret');
        $this->assertTrue($this->service->status()['running'], 'and the next fetch is not blocked');
    }

    /** One page view: its end-of-request pass, as index.php runs it in `request` mode. */
    private function visit(): void
    {
        $this->service->status();
        $this->trigger->afterResponse();
    }

    /** Page views, time passing between them, until the fetch is no longer running. */
    private function visitUntilDone(): void
    {
        for ($i = 0; $i < 40 && $this->service->status()['running']; $i++) {
            $this->visit();
            $this->clock->sleep(RemoteWikiArchive::SETTLE_SECONDS);
        }
        $this->assertFalse($this->service->status()['running'], 'the fetch never ended');
    }

    /** @return list<string> */
    private function waitingTypes(): array
    {
        return array_map('strval', array_column($this->db->loadAll(
            "SELECT message_type FROM {$this->schema->table()} WHERE queue_name = ?",
            [QueueTransport::DEFAULT_QUEUE]
        ), 'message_type'));
    }

    private function controller(Request $request): RemoteBackupApiController
    {
        $current = new CurrentRequest();
        $current->replace($request);
        $controller = new RemoteBackupApiController();
        $controller->setServices(new ServiceLocator([
            CurrentRequest::class => static fn (): CurrentRequest => $current,
            AclService::class => static fn (): AclService => new class extends AclService {
                public function __construct()
                {
                }

                public function isAdmin(?string $user = null): bool
                {
                    return true;
                }
            },
            CsrfTokenChecker::class => static fn (): CsrfTokenChecker => new class extends CsrfTokenChecker {
                public function __construct()
                {
                }

                public function checkToken(string $name, string $inputType, string $inputKey, bool $remove = true): bool
                {
                    return true;
                }
            },
            RemoteBackupService::class => fn (): RemoteBackupService => $this->service,
        ]));

        return $controller;
    }

    /** The service of the fetch, on that bus instead of the queue's: same folders, same remote wiki, same job file. */
    private function serviceOn(MessageBusInterface $bus): RemoteBackupService
    {
        return new RemoteBackupService($this->archives, $this->storage, new LocalFiles(), $this->remote, $bus, $this->statistics);
    }

    private function build(): void
    {
        $this->db = $this->kit->db;
        $this->schema = $this->kit->schema;
        $this->transport = $this->kit->transport;
        $this->statistics = $this->kit->statistics;
        $this->consumer = $this->kit->consumer;
        $bus = $this->kit->bus;
        $hibernation = new HibernationService(new ParameterBag(['wiki_status' => 'running']));
        $settings = QueueTestKit::settings($this->mode);
        $this->trigger = $this->kit->requestTrigger($settings);

        $services = $this->getWiki()->services;
        $this->archives = new ArchiveService(
            new ConfigurationService(),
            new QueueTestConsole(),
            $services->get(DbService::class),
            $services->get(ParameterBagInterface::class),
            $hibernation,
            $services->get(UrlFormatter::class),
            $this->storage,
            new LocalFiles(),
            $bus,
            $this->statistics,
            new ArchiveRuns($this->kit->db, $this->kit->schema, $this->clock),
        );
        $this->remote = new StepByStepRemoteWiki(self::STORED);
        $this->service = new RemoteBackupService($this->archives, $this->storage, new LocalFiles(), $this->remote, $bus, $this->statistics);
    }
}

/** A remote wiki that walks through the steps of a fetch without a network, noting each step it is asked to take and the slice it was given. */
class StepByStepRemoteWiki extends RemoteWikiArchive
{
    /** @var list<string> the steps taken, in order */
    public array $steps = [];
    /** @var list<int> the slice each step was given */
    public array $slices = [];
    /** @var list<string> the addresses a fetch was started from */
    public array $started = [];
    /** @var list<array<string, mixed>> the jobs given up */
    public array $abandoned = [];
    /** The step that throws, as a remote wiki that refuses would. */
    public ?string $failAt = null;
    /** A step to jump to on the next one taken, to start a test where it matters. */
    public ?string $toStep = null;
    /** How many download slices fail before one goes through, as a remote wiki that blinks would have them. */
    public int $failingSlices = 0;
    /** @var (\Closure(string): void)|null what happens while a step runs, given the step */
    public ?\Closure $onStep = null;

    public function __construct(private readonly string $storedAs)
    {
        parent::__construct();
    }

    public function start(string $url, string $username, string $password, array $params = []): array
    {
        $this->started[] = $url;

        return [
            'baseUrl' => $url,
            'step' => self::STEP_CHECKING,
            'remoteFilename' => '',
            'filename' => '',
            'total' => 0,
            'bytes' => 0,
            'warning' => '',
            'output' => '',
        ];
    }

    public function advance(array $job, string $localFolder, string $part, int $slice = 0): array
    {
        if ($this->toStep !== null) {
            $job['step'] = $this->toStep;
            $job['filename'] = $this->storedAs;
            $this->toStep = null;
        }
        $this->steps[] = $job['step'];
        $this->slices[] = $slice;
        if ($this->onStep !== null) {
            ($this->onStep)($job['step']);
        }
        if ($job['step'] === $this->failAt) {
            throw new \Exception('The remote wiki answered HTTP 507 on api/archives: disk full');
        }

        switch ($job['step']) {
            case self::STEP_CHECKING:
                $job['step'] = self::STEP_STARTING;
                break;
            case self::STEP_STARTING:
                $job['step'] = self::STEP_ARCHIVING;
                break;
            case self::STEP_ARCHIVING:
                $job['step'] = self::STEP_IDENTIFYING;
                break;
            case self::STEP_IDENTIFYING:
                $job['remoteFilename'] = '2026-08-20T13-29-28_archive.zip';
                $job['filename'] = $this->storedAs;
                $job['total'] = 12;
                $job['step'] = self::STEP_DOWNLOADING;
                break;
            case self::STEP_DOWNLOADING:
                if ($this->failingSlices > 0) {
                    $this->failingSlices--;
                    $job['failures'] = (int)($job['failures'] ?? 0) + 1;
                    $job['warning'] = 'Connection reset by peer';
                    break;
                }
                $job['failures'] = 0;
                if (!is_file($part)) {
                    file_put_contents($part, 'PK-half');
                } else {
                    file_put_contents($part, '-rest', FILE_APPEND);
                    $job['step'] = self::STEP_CLEANING;
                }
                break;
            case self::STEP_CLEANING:
                $job['step'] = self::STEP_DONE;
                break;
        }

        return $job;
    }

    public function abandon(array $job): void
    {
        $this->abandoned[] = $job;
    }
}
