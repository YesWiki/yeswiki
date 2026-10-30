<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use YesWiki\Admin\Message\CreateArchive;
use YesWiki\Admin\Message\CreateArchiveHandler;
use YesWiki\Admin\Message\RestoreArchive;
use YesWiki\Admin\Message\RestoreArchiveHandler;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\EventDispatcher;
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
 * Backups and restores an admin starts go through the queue, and run in the admin's own request when the queue would otherwise wait for something to call it (messenger / scheduler, spec 9.5).
 *
 * The queue is a throw-away SQLite file on the test's clock, the progress files live in a
 * throw-away folder, and the wiki's status in a throw-away configuration file. The archive and
 * the restore themselves are stand-ins: what is tested is how they are queued, run and followed.
 */
#[CoversClass(ArchiveService::class)]
#[CoversClass(CreateArchive::class)]
#[CoversClass(RestoreArchive::class)]
#[CoversClass(CreateArchiveHandler::class)]
#[CoversClass(RestoreArchiveHandler::class)]
class ArchiveQueueTest extends YesWikiTestCase
{
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
    private QueueBus $bus;
    private Journal $journal;
    private RuntimeLock $locks;
    private Storage $storage;
    private ?QueueRequestTrigger $trigger = null;
    private ?QueueArchiveService $archives = null;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/yw-archive-queue-' . bin2hex(random_bytes(6)) . '.db';
        $this->lockDir = sys_get_temp_dir() . '/yw-archive-locks-' . bin2hex(random_bytes(4));
        $this->root = sys_get_temp_dir() . '/yw-archive-root-' . bin2hex(random_bytes(4));
        mkdir($this->root);
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

        $this->configFile = sys_get_temp_dir() . '/yw-archive-config-' . bin2hex(random_bytes(4)) . '.php';
        $this->previousConfigFile = getenv('YESWIKI_CONFIG_FILE');
        putenv('YESWIKI_CONFIG_FILE=' . $this->configFile);
        $this->setWikiStatus('running');
        $this->previousMode = getenv(QueueSettings::ENV_MODE);

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

    /** @return array<string, array{string}> */
    public static function nonBlockingModes(): array
    {
        return ['request' => ['request'], 'api' => ['api'], 'command' => ['command'], 'request-spawn' => ['request-spawn']];
    }

    /** @return array<string, array{string}> */
    public static function queueModes(): array
    {
        return ['worker' => ['worker'], 'binary' => ['binary']];
    }

    /** Spec 9.5: where the queue runs only when something calls it, the admin's backup does not wait for that; it runs once the admin has the response. So in `request-spawn`, where nothing says before the launch that its process will run. */
    #[DataProvider('nonBlockingModes')]
    public function testStartingAnArchiveQueuesItAndPinsItInRequestMode(string $mode): void
    {
        $archives = $this->archives($mode);

        $uid = $archives->startArchive(true, false, ['custom'], ['files'], ['custom', 'files']);

        $this->assertNotSame('', $uid);
        $this->assertSame('pending', $this->stateOf($uid), 'queued first, for the dashboard to see it');
        $this->assertSame([], $archives->archived, 'and not run before the response has left');

        $this->endTheRequest();

        $this->assertSame([[
            'savefiles' => true,
            'savedatabase' => false,
            'foldersToInclude' => ['custom'],
            'foldersToExclude' => ['files'],
            'uid' => $uid,
            'onlyFolders' => ['custom', 'files'],
        ]], $archives->archived);
        $this->assertSame('handled', $this->stateOf($uid));
    }

    /** Another visitor's end-of-request pass must not take the backup first: it would run it on a request's budget, with PHP's time limit still on. */
    public function testAPinnedArchiveIsLeftToTheRequestThatPinnedIt(): void
    {
        $archives = $this->archives('request');
        $uid = $archives->startArchive();

        $this->drain();
        $this->assertSame([], $archives->archived);
        $this->assertSame('pending', $this->stateOf($uid));

        $this->endTheRequest();
        $this->assertCount(1, $archives->archived);
    }

    #[DataProvider('queueModes')]
    public function testInWorkerModeItIsOnlyQueued(string $mode): void
    {
        $archives = $this->archives($mode);
        $uid = $archives->startArchive();

        $this->endTheRequest();
        $this->assertSame([], $archives->archived, 'nothing pinned: a consumer of its own takes it');
        $this->assertSame('pending', $this->stateOf($uid));

        $this->drain();
        $this->assertCount(1, $archives->archived);
        $this->assertSame('handled', $this->stateOf($uid));
    }

    /** A worker that is gone would leave the backup to the end of visitors' requests, on a request's budget, where PHP cuts it: the admin's request runs it instead. */
    #[DataProvider('queueModes')]
    public function testABackupIsPinnedWhenTheWorkerIsGone(string $mode): void
    {
        $archives = $this->archives($mode, consumerSeen: false);
        $uid = $archives->startArchive();

        $this->drain();
        $this->assertSame([], $archives->archived, 'left to the request that pinned it');

        $this->endTheRequest();
        $this->assertCount(1, $archives->archived);
        $this->assertSame('handled', $this->stateOf($uid));
    }

    public function testABackupIsPinnedWhenTheWorkerWasLastSeenElevenMinutesAgo(): void
    {
        $archives = $this->archives('worker', consumerSeen: false);
        QueueTestKit::recordPass($this->db, QueueConsumerMode::Worker, $this->at('-1 hour'), $this->at('-11 minutes'), '');
        $archives->startArchive();

        $this->endTheRequest();
        $this->assertCount(1, $archives->archived);
    }

    public function testARestoreGoesThroughTheQueueToo(): void
    {
        $archives = $this->archives('request');
        $this->storage->write(ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . '/backup.zip', 'zip');

        $uid = $archives->startRestore('backup.zip', true, false, false);
        $this->assertSame('pending', $this->stateOf($uid));
        $this->endTheRequest();

        $this->assertSame([['filename' => 'backup.zip', 'restoreFiles' => true, 'restoreDatabase' => false, 'rewriteUrls' => false, 'uid' => $uid]], $archives->restored);
        $this->assertSame('handled', $this->stateOf($uid));
    }

    public function testRestoringAnArchiveThatIsNotThereQueuesNothing(): void
    {
        $archives = $this->archives('request');

        try {
            $archives->startRestore('missing.zip');
            $this->fail('a restore of nothing was accepted');
        } catch (\Exception $refused) {
            $this->assertStringContainsString('missing.zip', $refused->getMessage());
        }
        $this->assertSame(0, $this->statistics->pendingCount());
    }

    /** While a consumer has not taken it yet, the backup is under way for the browser, not lost. */
    public function testAPendingArchiveIsUnderWay(): void
    {
        $archives = $this->archives('worker');
        $uid = $archives->startArchive();

        $status = $archives->getUIDStatus($uid);

        $this->assertTrue($status['started']);
        $this->assertTrue($status['running']);
        $this->assertFalse($status['finished']);
        $this->assertFalse($status['failed']);
        $this->assertSame('pending', $status['state']);
    }

    public function testAFinishedArchiveReportsItsOutput(): void
    {
        $archives = $this->archives('request');
        $archives->onArchive = function (string $uid): void {
            $this->writeOutputOf($uid, "=== Creating zip archive ===\nArchive \"private/backups/x.zip\" successfully created !\nEND\n");
        };
        $uid = $archives->startArchive();
        $this->endTheRequest();

        $status = $archives->getUIDStatus($uid);

        $this->assertTrue($status['finished']);
        $this->assertFalse($status['running']);
        $this->assertFalse($status['failed']);
        $this->assertSame('handled', $status['state']);
        $this->assertStringContainsString('Creating zip archive', $status['output']);
    }

    /** It goes straight to `failed`, never retried, and the browser hears it with its reason. */
    public function testTheUidStatusReportsAFailedMessage(): void
    {
        $archives = $this->archives('request');
        $archives->onArchive = function (string $uid): void {
            $this->writeOutputOf($uid, "=== Checking free space ===\nSTOP\n");
            $this->setWikiStatus('archiving');

            throw new \RuntimeException('disk exploded');
        };
        $uid = $archives->startArchive();

        $this->endTheRequest();
        $status = $archives->getUIDStatus($uid);

        $this->assertSame('failed', $status['state'], 'no retry: a backup that broke is not made again behind the admin\'s back');
        $this->assertTrue($status['failed']);
        $this->assertFalse($status['running']);
        $this->assertStringContainsString('disk exploded', $status['error']);
        $this->assertStringContainsString('Checking free space', $status['output']);
        $this->assertSame('running', $this->wikiStatus(), 'the read-only status the failed run set is lifted');
    }

    public function testAFailedRestoreIsNeverRetried(): void
    {
        $archives = $this->archives('request');
        $this->storage->write(ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . '/backup.zip', 'zip');
        $archives->onRestore = static function (): void {
            throw new \RuntimeException('half restored');
        };
        $uid = $archives->startRestore('backup.zip');

        $this->endTheRequest();
        $status = $archives->getUIDStatus($uid);

        $this->assertSame('failed', $status['state']);
        $this->assertTrue($status['failed']);
        $this->assertStringContainsString('half restored', $status['error']);
        $this->assertCount(1, $archives->restored);
    }

    /** A failed run lifts only the status it set: an admin's hibernation, or another run's `archiving`, stays. */
    public function testAFailedArchiveLeavesAStatusItDidNotSet(): void
    {
        $archives = $this->archives('request');
        $archives->onArchive = static function (): void {
            throw new \RuntimeException('refused');
        };
        $this->setWikiStatus('archiving');
        $archives->startArchive();

        $this->endTheRequest();

        $this->assertSame('archiving', $this->wikiStatus());
    }

    /**
     * A run that failed lifts `archiving` only when no other run this wiki follows is waiting or under way, and the uid of such a run is not forgotten.
     *
     * A remote wiki's `api/archives` arriving during an admin's backup is refused, since the wiki is
     * already `archiving`; its poll must leave the backup's read-only status, and its browser, alone.
     */
    public function testAFailedRunLeavesTheStatusAndTheUidOfARunStillGoing(): void
    {
        $archives = $this->archives('worker');
        $going = $archives->startArchive();
        $this->transport->claimAs(QueueConsumerMode::Worker, 3600);
        $this->assertNotNull($this->transport->getById($this->messageOf($going)), 'a consumer is at work on it');
        $this->setWikiStatus('archiving');
        $refused = $archives->startArchive();
        $this->transport->moveToFailed($this->messageOf($refused), 'RuntimeException: the wiki is already archiving');

        $status = $archives->getUIDStatus($refused);

        $this->assertTrue($status['failed']);
        $this->assertSame('archiving', $this->wikiStatus(), 'the backup under way keeps the wiki read-only');
        $this->assertArrayHasKey($going, $this->info(), 'and its browser still follows it');
        $this->assertArrayNotHasKey($refused, $this->info(), 'the failed run, reported, is forgotten');
        $this->assertTrue($archives->getUIDStatus($going)['running']);
    }

    /**
     * R19: a backup the host killed (FPM's request_terminate_timeout, the OOM killer) leaves no process behind: its read-only status is lifted at once, not when its limit, hours away, has passed.
     */
    public function testAnArchiveTheHostKilledReleasesTheReadOnlyStatusAtOnce(): void
    {
        $archives = $this->archives('request');
        $uid = $archives->startArchive();
        $this->transport->claimAs(QueueConsumerMode::Request, 21600);
        $this->assertNotNull($this->transport->getById($this->messageOf($uid)));
        QueueTestHolders::hand($this->db, $this->schema, $this->messageOf($uid), QueueTestHolders::gone());
        $this->setWikiStatus('archiving');

        $status = $archives->getUIDStatus($uid);

        $this->assertSame('killed', $status['state']);
        $this->assertSame('running', $this->wikiStatus());
    }

    /**
     * R18: the wiki's own container hands `queue.message_killed` to ArchiveService, which is how a killed backup lifts its read-only status with nobody on the backups page.
     *
     * Every other test here subscribes the service by hand; this one asks the kernel.
     */
    public function testTheWikiTellsTheArchivesOfAKilledMessage(): void
    {
        $listeners = $this->getWiki()->services->get(EventDispatcher::class)->getListeners(QueueConsumer::MESSAGE_KILLED);

        $archives = array_filter($listeners, static fn (mixed $listener): bool => is_array($listener)
            && $listener[0] instanceof ArchiveService
            && $listener[1] === 'releaseWhatAKilledRunLeft');
        $this->assertCount(1, $archives);
    }

    /** Spec 9.5 and 7.2: a run PHP or the server killed leaves the wiki read-only; the browser that follows it lifts that once the queue says killed. */
    public function testAKilledArchiveReleasesTheReadOnlyStatus(): void
    {
        $archives = $this->archives('worker');
        $uid = $archives->startArchive();
        $this->takeAndDie($this->messageOf($uid));
        $this->transport->reapKilled();

        $status = $archives->getUIDStatus($uid);

        $this->assertSame('killed', $status['state']);
        $this->assertTrue($status['failed']);
        $this->assertFalse($status['running']);
        $this->assertSame('running', $this->wikiStatus());
    }

    /** In `request` mode, nothing reaps until a visit runs a pass. A claim past its limit is killed all the same. */
    public function testAnArchivePastItsLimitIsKilledBeforeAnyPassReapsIt(): void
    {
        $archives = $this->archives('request');
        $uid = $archives->startArchive();
        $this->takeAndDie($this->messageOf($uid));

        $status = $archives->getUIDStatus($uid);

        $this->assertSame('killed', $status['state']);
        $this->assertTrue($status['failed']);
        $this->assertSame('running', $this->wikiStatus());
    }

    /**
     * R18: nobody may ever open the backups page (a backup before an update, one another wiki fetched, one whose pin died).
     *
     * The next pass reaps the dead run even though the wiki is paused, the backup hears of it and
     * lifts the status it left, and passes consume as usual again. Whether that same pass goes on
     * once the status is lifted is the queue's to say (QueueConsumerTest).
     */
    #[DataProvider('nonBlockingModes')]
    public function testAKilledArchiveIsReleasedByTheNextPassEvenPaused(string $mode): void
    {
        $archives = $this->archives($mode);
        $uid = $archives->startArchive();
        $killed = $this->messageOf($uid);
        $this->takeAndDie($killed);

        $this->consumer->drain(QueueConsumerMode::from($mode), (new QueueBudgetFactory())->forMode(QueueConsumerMode::from($mode), true));

        $this->assertSame('killed', $this->statistics->messageState($killed));
        $this->assertSame('running', $this->wikiStatus());
        $this->assertArrayNotHasKey($uid, $this->info(), 'the dead run is forgotten: a `core:archive` typed by hand next keeps its status');

        $this->archives('worker')->startArchive();
        $report = $this->consumer->drain(QueueConsumerMode::from($mode), (new QueueBudgetFactory())->forMode(QueueConsumerMode::from($mode), true));

        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
        $this->assertCount(1, $this->currentArchives()->archived, 'the next run went through');
    }

    /** A run nothing here follows (`core:archive` typed in a terminal) did not die because some other message was killed. */
    public function testAKilledMessageThisWikiDoesNotFollowLeavesTheStatus(): void
    {
        $archives = $this->archives('worker');
        $uid = $archives->startArchive();
        $killed = $this->messageOf($uid);
        $this->storage->write(ArchiveService::PROGRESS_FOLDER . '/info.json', '{}');
        $this->takeAndDie($killed);

        $this->drain();

        $this->assertSame('killed', $this->statistics->messageState($killed));
        $this->assertSame('archiving', $this->wikiStatus());
    }

    /** Another run this wiki follows is still waiting: the status is its own. The dead one is forgotten all the same. */
    public function testAKilledArchiveLeavesTheStatusToARunStillWaiting(): void
    {
        $archives = $this->archives('worker');
        $dead = $archives->startArchive();
        $killed = $this->messageOf($dead);
        $waiting = $archives->startArchive();
        $this->takeAndDie($killed);

        $this->drain();

        $this->assertSame('archiving', $this->wikiStatus());
        $this->assertArrayNotHasKey($dead, $this->info());
        $this->assertArrayHasKey($waiting, $this->info());
    }

    /** The browser that followed the backup may be long gone: the backups page lifts the status too. */
    public function testTheArchivingStatusLiftsWhatAKilledRunLeft(): void
    {
        $archives = $this->archives('worker');
        $uid = $archives->startArchive();
        $this->takeAndDie($this->messageOf($uid));

        $status = $archives->getArchivingStatus();

        $this->assertFalse($status['archiving']);
        $this->assertSame('running', $this->wikiStatus());

        $this->setWikiStatus('archiving');
        $this->assertTrue($archives->getArchivingStatus()['archiving'], 'the dead run is forgotten: a `core:archive` typed by hand next keeps its status');
        $this->assertSame('archiving', $this->wikiStatus());
    }

    /**
     * Only a killed backup can have left `archiving`: one that failed lifted it as it failed (archive()), and a restore never sets it.
     *
     * A dead run nobody polled stays in info.json, and must not lift the status of a run nothing
     * here follows -- a `core:archive` typed by hand -- the wiki would turn writable in the middle of it.
     */
    public function testAFailedRestoreNobodyPolledLeavesTheStatusOfABackupTypedByHand(): void
    {
        $archives = $this->archives('request');
        $this->storage->write(ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . '/backup.zip', 'zip');
        $archives->onRestore = static function (): void {
            throw new \RuntimeException('half restored');
        };
        $uid = $archives->startRestore('backup.zip');
        $this->endTheRequest();
        $this->assertSame('failed', $this->stateOf($uid));

        $this->setWikiStatus('archiving');

        $this->assertTrue($archives->getArchivingStatus()['archiving']);
        $this->assertSame('archiving', $this->wikiStatus());
    }

    public function testAFailedBackupNobodyPolledLeavesTheStatusOfABackupTypedByHand(): void
    {
        $archives = $this->archives('request');
        $archives->onArchive = static function (): void {
            throw new \RuntimeException('disk exploded');
        };
        $uid = $archives->startArchive();
        $this->endTheRequest();
        $this->assertSame('failed', $this->stateOf($uid));

        $this->setWikiStatus('archiving');

        $this->assertTrue($archives->getArchivingStatus()['archiving']);
        $this->assertSame('archiving', $this->wikiStatus());
    }

    public function testAKilledRestoreLeavesTheStatusOfABackupTypedByHand(): void
    {
        $archives = $this->archives('worker');
        $this->storage->write(ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . '/backup.zip', 'zip');
        $uid = $archives->startRestore('backup.zip');
        $this->takeAndDie($this->messageOf($uid));

        $this->assertTrue($archives->getArchivingStatus()['archiving'], 'takeAndDie() stands for the `core:archive` that set it');
        $this->assertSame('archiving', $this->wikiStatus());
    }

    public function testTheArchivingStatusLeavesARunThatIsStillGoing(): void
    {
        $archives = $this->archives('worker');
        $uid = $archives->startArchive();
        $this->transport->claimAs(QueueConsumerMode::Worker, 3600);
        $this->transport->getById($this->messageOf($uid));
        $this->setWikiStatus('archiving');

        $this->assertTrue($archives->getArchivingStatus()['archiving']);
        $this->assertSame('archiving', $this->wikiStatus());
    }

    /** What a detached `core:archive` left in info.json before the update has no message to follow: it is forgotten. */
    public function testAnUidWithoutAMessageIsForgotten(): void
    {
        $archives = $this->archives('request');
        $this->storage->write(ArchiveService::PROGRESS_FOLDER . '/info.json', (string)json_encode([
            'old' => ['input' => ArchiveService::PROGRESS_FOLDER . '/input-old.log', 'output' => ArchiveService::PROGRESS_FOLDER . '/output-old.log', 'pid' => '1234'],
        ]));

        $status = $archives->getUIDStatus('old');

        $this->assertFalse($status['started']);
        $this->assertSame('[]', $this->storage->read(ArchiveService::PROGRESS_FOLDER . '/info.json'));
    }

    public function testTheArchivingStatusNoLongerAsksWhetherAProcessCanBeStarted(): void
    {
        $status = $this->archives('request')->getArchivingStatus();

        $this->assertArrayNotHasKey('canExec', $status);
        $this->assertArrayNotHasKey('callAsync', $status);
        $this->assertArrayHasKey('canArchive', $status);
    }

    /**
     * A backup retried from the jobs page is a copy nothing follows: it would run on a visitor's budget, and the backups page would lift its `archiving` mid-way. It is refused, and touches nothing.
     */
    #[DataProvider('statuses')]
    public function testAnArchiveRetriedFromTheJobsPageIsRefused(string $status): void
    {
        $archives = $this->archives('request');
        $archives->onArchive = static function (): void {
            throw new \RuntimeException('disk exploded');
        };
        $archives->startArchive();
        $this->endTheRequest();
        $this->setWikiStatus($status);

        $copy = $this->failedCopyOf(CreateArchive::class);
        $this->transport->requeue($copy);
        // Taken by id, as no pass takes anything while the wiki is `archiving`: what is tested is that the handler refuses it.
        $this->consumer->handleById($copy, QueueConsumerMode::Worker);

        $this->assertCount(1, $archives->archived, 'the copy did not run');
        $this->assertSame($status, $this->wikiStatus());
        $refused = $this->statistics->failedMessage($this->failedCopyOf(CreateArchive::class));
        $this->assertNotNull($refused);
        $this->assertStringContainsString(_t('AU_RUN_NOT_REPLAYED'), (string)$refused['error']);
    }

    /** A blocking consumer may take the message before queue() has written its id under the uid: that first run is the run, not a copy. */
    public function testARunTakenBeforeItsMessageIsRecordedGoesAhead(): void
    {
        $archives = $this->archives('worker');
        $uid = $archives->startArchive();
        $messageId = $this->messageOf($uid);
        $info = json_decode($this->storage->read(ArchiveService::PROGRESS_FOLDER . '/info.json'), true);
        $this->assertIsArray($info);
        unset($info[$uid]['message']);
        $this->storage->write(ArchiveService::PROGRESS_FOLDER . '/info.json', (string)json_encode($info));

        $this->consumer->handleById($messageId, QueueConsumerMode::Worker);

        $this->assertCount(1, $archives->archived);
        $this->assertSame('handled', $this->statistics->messageState($messageId));
    }

    /** @return array<string, array{string}> */
    public static function statuses(): array
    {
        return ['running' => ['running'], 'another run archiving' => ['archiving']];
    }

    /** "Retry all" on the jobs page would replay a restore half done: refused, whether or not the browser already saw the failure. */
    public function testARestoreRetriedFromTheJobsPageIsRefused(): void
    {
        $archives = $this->archives('worker');
        $this->storage->write(ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . '/backup.zip', 'zip');
        $archives->onRestore = static function (): void {
            throw new \RuntimeException('half restored');
        };
        $uid = $archives->startRestore('backup.zip');
        $this->drain();
        $this->assertTrue($archives->getUIDStatus($uid)['failed']);

        $this->transport->requeue($this->failedCopyOf(RestoreArchive::class));
        $this->drain();

        $this->assertCount(1, $archives->restored, 'the restore was not replayed');
        $this->assertSame(1, $this->statistics->failedCount());
    }

    public function testCallArchiveAsyncIsGone(): void
    {
        require_once 'src/migrations/20261023130000_ArchivesNoLongerChooseHowTheyRun.php';
        $configuration = new ConfigurationService();
        $config = $configuration->getConfiguration($this->configFile);
        $config->load();
        $config['archive'] = ['call_archive_async' => false, 'max_nb_files' => 4, 'preupdate_backup_activated' => true];
        $this->assertTrue($configuration->write($config));

        $this->runMigration();
        $this->runMigration();

        $config = $configuration->getConfiguration($this->configFile);
        $config->load();
        $this->assertSame(['max_nb_files' => 4, 'preupdate_backup_activated' => true], $config['archive']);

        $mentions = [];
        foreach (['src', 'templates', 'javascripts'] as $folder) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $path => $file) {
                if ($file->isFile() && !str_contains($path, 'ArchivesNoLongerChooseHowTheyRun') && str_contains((string)file_get_contents($path), 'call_archive_async')) {
                    $mentions[] = $path;
                }
            }
        }
        $this->assertSame([], $mentions, 'nothing reads, sets or offers the setting any more');
    }

    private function runMigration(): void
    {
        $services = $this->getWiki()->services;
        $migration = new \ArchivesNoLongerChooseHowTheyRun();
        $migration->setServices($services);
        $migration->setDbService($services->get(DbService::class));
        $migration->setParams($services->get(ParameterBagInterface::class));
        $migration->run();
    }

    /**
     * The service under test in that mode, on this test's queue, progress folder and configuration.
     *
     * In `worker` and `binary`, the worker is seen at work a minute ago unless told otherwise:
     * a backup is left to a worker only while there is one.
     */
    private function archives(string $mode, bool $consumerSeen = true): QueueArchiveService
    {
        putenv(QueueSettings::ENV_MODE . '=' . $mode);
        if ($consumerSeen && !QueueConsumerMode::from($mode)->isBudgeted()) {
            QueueTestKit::recordPass($this->db, QueueConsumerMode::from($mode), $this->at('-1 hour'), $this->at('-1 minute'), '');
        }
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
            new HibernationService(new ParameterBag(['wiki_status' => 'running'])),
            $this->journal,
            $this->clock,
            static fn (): bool => true,
        );
        $services = $this->getWiki()->services;
        $this->archives = new QueueArchiveService(
            new ConfigurationService(),
            $services->get(ConsoleService::class),
            $services->get(DbService::class),
            $services->get(ParameterBagInterface::class),
            new HibernationService(new ParameterBag(['wiki_status' => 'running'])),
            $services->get(UrlFormatter::class),
            $this->storage,
            new LocalFiles(),
            $this->bus,
            $settings,
            $this->trigger,
            $this->statistics,
            $fallback,
        );

        return $this->archives;
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

        $locator = new QueueHandlerLocator(
            [CreateArchive::class => ['create'], RestoreArchive::class => ['restore']],
            new ServiceLocator([
                'create' => fn (): CreateArchiveHandler => new CreateArchiveHandler($this->currentArchives()),
                'restore' => fn (): RestoreArchiveHandler => new RestoreArchiveHandler($this->currentArchives()),
            ])
        );
        $this->bus = $bus = new QueueBus($this->transport, $locator, $this->journal);
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
            services: new ServiceLocator([EventDispatcher::class => fn (): EventDispatcher => $this->events()]),
        );
    }

    /** The wiki's dispatcher, with the service under test subscribed to it as the kernel subscribes it. */
    private function events(): EventDispatcher
    {
        $events = new EventDispatcher(new ServiceLocator([]), new ThrowableFormatter());
        $events->addSubscriber($this->currentArchives());

        return $events;
    }

    private function currentArchives(): QueueArchiveService
    {
        if ($this->archives === null) {
            throw new \LogicException('archives() first');
        }

        return $this->archives;
    }

    /** The end of the admin's request, once the response has left: what index.php's shutdown function does. */
    private function endTheRequest(): void
    {
        $this->assertNotNull($this->trigger, 'archives() first');
        $this->trigger->afterResponse();
    }

    /** A pass of a consumer of its own, as a `messenger:drain` would run. */
    private function drain(): void
    {
        $this->consumer->drain(QueueConsumerMode::Command, (new QueueBudgetFactory())->forMode(QueueConsumerMode::Command, true), false);
    }

    /** A consumer on another machine takes the message and dies with it: no ack, no failure, and its limit runs out. */
    private function takeAndDie(int $messageId): void
    {
        $this->transport->claimAs(QueueConsumerMode::Worker, 60);
        $this->assertNotNull($this->transport->getById($messageId));
        QueueTestHolders::hand($this->db, $this->schema, $messageId, QueueTestHolders::elsewhere());
        $this->setWikiStatus('archiving');
        $this->clock->sleep(3600);
    }

    /** The id of the row Messenger sent to `failed` for a message of that type: a copy, with an id of its own. */
    private function failedCopyOf(string $type): int
    {
        foreach ($this->statistics->failed() as $failed) {
            if ($failed['type'] === $type) {
                return $failed['id'];
            }
        }
        $this->fail("no $type in failed");
    }

    private function messageOf(string $uid): int
    {
        $info = json_decode($this->storage->read(ArchiveService::PROGRESS_FOLDER . '/info.json'), true);
        $this->assertIsArray($info);
        $this->assertArrayHasKey($uid, $info);
        $this->assertIsInt($info[$uid]['message'] ?? null, 'the uid records the message that runs it');

        return $info[$uid]['message'];
    }

    /** @return array<string, mixed> the runs this wiki follows, by uid */
    private function info(): array
    {
        $info = json_decode($this->storage->read(ArchiveService::PROGRESS_FOLDER . '/info.json'), true);
        $this->assertIsArray($info);

        return $info;
    }

    private function stateOf(string $uid): string
    {
        return $this->statistics->messageState($this->messageOf($uid));
    }

    private function writeOutputOf(string $uid, string $text): void
    {
        $this->storage->write(ArchiveService::PROGRESS_FOLDER . "/output-$uid.log", $text);
    }

    private function setWikiStatus(string $status): void
    {
        $configuration = new ConfigurationService();
        $config = $configuration->getConfiguration($this->configFile);
        $config->load();
        $config['wiki_status'] = $status;
        $this->assertTrue($configuration->write($config));
    }

    private function at(string $relative): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->modify($relative)->format('Y-m-d H:i:s');
    }

    private function wikiStatus(): string
    {
        $config = (new ConfigurationService())->getConfiguration($this->configFile);
        $config->load();

        return trim((string)($config['wiki_status'] ?? '')) ?: 'running';
    }
}

/** ArchiveService with stand-ins for the archive and the restore themselves, which note what they were asked. */
class QueueArchiveService extends ArchiveService
{
    /** @var list<array<string, mixed>> */
    public array $archived = [];
    /** @var list<array<string, mixed>> */
    public array $restored = [];
    /** @var (\Closure(string): void)|null what the archive does besides noting it was asked, given its uid */
    public ?\Closure $onArchive = null;
    /** @var (\Closure(string): void)|null */
    public ?\Closure $onRestore = null;

    public function archive(&$output, bool $savefiles = true, bool $savedatabase = true, array $foldersToInclude = [], array $foldersToExclude = [], ?array $hideConfigValuesParams = null, string $uid = '', ?array $onlyFolders = null): string
    {
        $this->archived[] = compact('savefiles', 'savedatabase', 'foldersToInclude', 'foldersToExclude', 'uid', 'onlyFolders');
        if ($this->onArchive !== null) {
            ($this->onArchive)($uid);
        }

        return 'private/backups/x.zip';
    }

    public function restoreArchive(string $filename, bool $restoreFiles = true, bool $restoreDatabase = true, bool $rewriteUrls = true, string $uid = ''): void
    {
        $this->restored[] = compact('filename', 'restoreFiles', 'restoreDatabase', 'rewriteUrls', 'uid');
        if ($this->onRestore !== null) {
            ($this->onRestore)($uid);
        }
    }
}
