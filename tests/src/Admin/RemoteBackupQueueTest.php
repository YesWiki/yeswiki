<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Admin\Api\RemoteBackupApiController;
use YesWiki\Admin\Message\AdvanceRemoteBackup;
use YesWiki\Admin\Message\AdvanceRemoteBackupHandler;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Admin\Service\RemoteBackupService;
use YesWiki\Admin\Service\RemoteWikiArchive;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueBudget;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueBudgetContext;
use YesWiki\Kernel\Service\Queue\QueueBudgetFactory;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueCheckpoint;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueFallback;
use YesWiki\Kernel\Service\Queue\QueueRequestTrigger;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\ThrowableFormatter;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Test\Kernel\QueueTestConsole;
use YesWiki\Test\Kernel\QueueTestHolders;
use YesWiki\Test\Kernel\QueueTestKit;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestConsole.php';
require_once 'tests/src/Kernel/QueueTestHolders.php';

/**
 * Fetching a backup from another wiki goes on through the queue, one message per step or download slice, so it finishes with no tab open (messenger / scheduler, spec 9.6).
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
    private const STORED = '2026-08-20T13-29-28_remote-org_archive.zip';

    /** The steps a whole fetch takes, the download in two slices. */
    private const EVERY_STEP = ['checking', 'starting', 'archiving', 'identifying', 'downloading', 'downloading', 'cleaning'];

    private string $file;
    private string $lockDir;
    private string $root;
    private string $configFile;
    private string|false $previousConfigFile;
    private string|false $previousMode;

    private DbService $db;
    private QueueSchema $schema;
    private MockClock $clock;
    private QueueTransport $transport;
    private QueueStatistics $statistics;
    private QueueConsumer $consumer;
    private QueueBudgetContext $budgetContext;
    private QueueRequestTrigger $trigger;
    private Journal $journal;
    private RuntimeLock $locks;
    private Storage $storage;
    private ArchiveService $archives;
    private StepByStepRemoteWiki $remote;
    private RemoteBackupService $service;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/yw-remote-queue-' . bin2hex(random_bytes(6)) . '.db';
        $this->lockDir = sys_get_temp_dir() . '/yw-remote-locks-' . bin2hex(random_bytes(4));
        $this->root = sys_get_temp_dir() . '/yw-remote-root-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/' . ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP, 0777, true);
        $this->storage = Storage::rootedAt($this->root);

        $this->db = new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywq_',
            'debug' => false,
        ]));
        $this->schema = new QueueSchema($this->db);
        $this->schema->create();
        (new JournalSchema($this->db))->create();

        $this->configFile = sys_get_temp_dir() . '/yw-remote-config-' . bin2hex(random_bytes(4)) . '.php';
        $this->previousConfigFile = getenv('YESWIKI_CONFIG_FILE');
        putenv('YESWIKI_CONFIG_FILE=' . $this->configFile);
        $configuration = new ConfigurationService();
        $config = $configuration->getConfiguration($this->configFile);
        $config->load();
        $config['wiki_status'] = 'running';
        $this->assertTrue($configuration->write($config));
        $this->previousMode = getenv(QueueSettings::ENV_MODE);
        putenv(QueueSettings::ENV_MODE . '=request');

        $this->clock = new MockClock('2026-10-23 12:00:00', 'UTC');
        $this->locks = QueueTestKit::locksIn($this->lockDir);
        $this->build();
    }

    protected function tearDown(): void
    {
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
        $this->assertSame(['checking', 'starting'], $this->remote->steps, 'the steps that need no waiting follow each other in one pass');
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
        $this->visit();
        $this->assertSame(self::EVERY_STEP, $this->remote->steps, 'from the download on, each slice follows the last at once');
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

    /** A slice of download fits what is left of the pass, and takes the full slice where no pass budget applies. */
    public function testADownloadSliceFitsThePass(): void
    {
        $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');
        $this->remote->toStep = 'downloading';

        (new AdvanceRemoteBackupHandler($this->service))(new AdvanceRemoteBackup());
        $this->assertSame(['downloading'], $this->remote->steps);
        $this->assertSame(RemoteBackupService::SLICE_SECONDS, $this->remote->slices[0], 'a blocking consumer has no pass budget');

        $this->consumer->drain(QueueConsumerMode::Request, new QueueBudget(microtime(true) + 7.9, PHP_INT_MAX, 60));

        $this->assertSame(['downloading', 'downloading', 'cleaning'], $this->remote->steps);
        $this->assertSame(7, $this->remote->slices[1]);
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
        $this->assertSame(array_merge(['checking', 'starting'], self::EVERY_STEP), $this->remote->steps);
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

    /**
     * Two steps of one fetch can be queued (a step retried from the jobs page, two senders racing past the dedup key)
     * and taken by two consumers at once: the second must not take a step of its own on the job file the first is
     * moving, nor keep a second chain going.
     */
    public function testAStepTakenWhileAnotherRunsDoesNothing(): void
    {
        $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');
        $concurrent = null;
        $this->remote->onStep = function (string $step) use (&$concurrent): void {
            if ($step === 'checking' && $concurrent === null) {
                $concurrent = [];
                $concurrent = $this->service->advance();
            }
        };

        $this->visitUntilDone();

        $this->assertSame(['step' => RemoteBackupService::STEP_IDLE, 'running' => false], $concurrent, 'nothing done, and no next step to queue');
        $this->assertSame(self::EVERY_STEP, $this->remote->steps, 'each step taken once');
        $this->assertSame(self::STORED, $this->service->status()['filename']);
    }

    /** A step whose process died (PHP's limit, a crash) leaves its job at that step: the browser is told, not left watching "in progress" for ever. */
    public function testAKilledStepIsNotShownAsRunning(): void
    {
        $this->service->start('https://remote.org', 'RemoteAdmin', 'secret');
        $this->transport->claimAs(QueueConsumerMode::Worker, 60);
        $taken = iterator_to_array($this->transport->get());
        $this->assertCount(1, $taken);
        // A consumer on another machine: its limit decides.
        QueueTestHolders::hand($this->db, $this->schema, (int)$taken[0]->last(TransportMessageIdStamp::class)?->getId(), QueueTestHolders::elsewhere());
        $underWay = $this->service->status();
        $this->assertTrue($underWay['running'], 'under way while its claim holds');

        $this->clock->sleep(3600);

        $state = $this->service->status();
        $this->assertFalse($state['running']);
        $this->assertSame(_t('ADMIN_BACKUPS_REMOTE_STOPPED'), $state['error']);

        $this->service->start('https://other.org', 'RemoteAdmin', 'secret');
        $this->assertCount(1, $this->remote->abandoned, 'a new fetch tidies up after the dead one');
        $this->assertTrue($this->service->status()['running']);
    }

    /** A fetch the browser was driving when the wiki was upgraded has no message behind it: the migration queues its next step. */
    public function testTheMigrationResumesAFetchLeftInProgress(): void
    {
        require_once 'src/migrations/20261023131000_RemoteBackupInProgressJoinsTheQueue.php';
        $migration = new \RemoteBackupInProgressJoinsTheQueue();
        $migration->setServices(new ServiceLocator([RemoteBackupService::class => fn (): RemoteBackupService => $this->service]));

        $migration->run();
        $this->assertSame(0, $this->statistics->pendingCount(), 'no fetch, nothing to queue');

        $this->storage->write(ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . '/' . RemoteBackupService::JOB_FILENAME, (string)json_encode([
            'baseUrl' => 'https://remote.org',
            'step' => 'archiving',
            'part' => $this->root . '/' . ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . '/remote-0123456789ab.part',
            'busySince' => 0,
        ]));
        $migration->run();
        $migration->run();

        $this->assertSame(1, $this->statistics->pendingCount());
        $this->assertNotEmpty($migration->notes());
        $this->clock->sleep(RemoteWikiArchive::SETTLE_SECONDS);
        $this->visitUntilDone();
        $this->assertSame(['archiving', 'identifying', 'downloading', 'downloading', 'cleaning'], $this->remote->steps);
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

    private function build(): void
    {
        $this->transport = new QueueTransport($this->db, $this->schema, clock: $this->clock);
        $this->statistics = new QueueStatistics($this->db, $this->schema, new JournalSchema($this->db), $this->transport, $this->clock);
        $this->journal = new Journal(new ServiceLocator([
            DbService::class => fn (): DbService => $this->db,
            JournalSchema::class => fn (): JournalSchema => new JournalSchema($this->db),
            ThrowableFormatter::class => static fn (): ThrowableFormatter => new ThrowableFormatter(),
        ]));
        $log = fopen('php://memory', 'w+b');
        if ($log !== false) {
            $this->journal->writeTo($log);
        }

        $bus = new QueueBus($this->transport, new QueueHandlerLocator(
            [AdvanceRemoteBackup::class => ['advance']],
            new ServiceLocator(['advance' => fn (): AdvanceRemoteBackupHandler => new AdvanceRemoteBackupHandler($this->service)])
        ), $this->journal);
        $recorder = new QueueRecorder($this->db, $this->schema, new JournalSchema($this->db), $this->clock);
        $noSchedule = new class implements ProvidesSchedule {
            public function recurringMessages(): array
            {
                return [];
            }
        };
        $this->budgetContext = new QueueBudgetContext();
        $hibernation = new HibernationService(new ParameterBag(['wiki_status' => 'running']));
        $this->consumer = new QueueConsumer(
            $this->transport,
            $bus,
            $recorder,
            $this->statistics,
            $this->budgetContext,
            new QueueBudgetFactory(),
            $hibernation,
            $this->locks,
            $this->journal,
            new QueueScheduleTicker([$noSchedule], $bus, new QueueCheckpoint($this->db, $this->schema, $this->locks), $recorder, $this->clock),
            $this->clock,
        );
        $settings = new QueueSettings(new ParameterBag([]), static fn (): array => []);
        $fallback = new QueueFallback($settings, $this->statistics, new HibernationService(new ParameterBag(['wiki_status' => 'running'])), $this->locks, $this->clock);
        $this->trigger = new QueueRequestTrigger(
            $settings,
            $this->consumer,
            new QueueBudgetFactory(),
            $this->statistics,
            $fallback,
            new QueueTestConsole(),
            $this->locks,
            $hibernation,
            $this->journal,
            $this->clock,
            static fn (): bool => true,
        );

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
            $settings,
            $this->trigger,
            $this->statistics,
            $fallback,
        );
        $this->remote = new StepByStepRemoteWiki(self::STORED);
        $this->service = new RemoteBackupService($this->archives, $this->storage, new LocalFiles(), $this->remote, $bus, $this->budgetContext, $this->statistics);
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
