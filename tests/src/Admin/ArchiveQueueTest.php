<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use YesWiki\Admin\Message\CreateArchive;
use YesWiki\Admin\Message\CreateArchiveHandler;
use YesWiki\Admin\Message\RestoreArchive;
use YesWiki\Admin\Message\RestoreArchiveHandler;
use YesWiki\Admin\Service\ArchiveRuns;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Lock\FileLocks;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
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
 * Backups and restores an admin starts go through the queue, and run in the admin's own request when the queue would otherwise wait for something to call it (ADR-0030).
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
    /** The `queue_consumer` the test runs under; the settings read it as the parameter the environment resolved. */
    private string $mode = '';

    private QueueTestKit $kit;
    private string $root;

    private DbService $db;
    private QueueSchema $schema;
    private MockClock $clock;
    private QueueTransport $transport;
    private QueueStatistics $statistics;
    private QueueConsumer $consumer;
    private QueueBus $bus;
    private Storage $storage;
    private ArchiveRuns $runs;
    private ?QueueRequestTrigger $trigger = null;
    private ?QueueArchiveService $archives = null;

    /** The wiki boots on its own configuration, before the kit points YESWIKI_CONFIG_FILE at one of its own. */
    public static function setUpBeforeClass(): void
    {
        self::getWiki();
    }

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yw-archive-root-' . bin2hex(random_bytes(4));
        mkdir($this->root);
        $this->storage = Storage::rootedAt($this->root);

        $this->clock = new MockClock(new \DateTimeImmutable('2026-10-23 12:00:00'));
        $this->kit = new QueueTestKit(
            [
                CreateArchive::class => fn (CreateArchive $run) => (new CreateArchiveHandler($this->currentArchives()))($run),
                RestoreArchive::class => fn (RestoreArchive $run) => (new RestoreArchiveHandler($this->currentArchives()))($run),
            ],
            $this->clock,
        );
        $this->db = $this->kit->db;
        $this->schema = $this->kit->schema;
        $this->transport = $this->kit->transport;
        $this->statistics = $this->kit->statistics;
        $this->consumer = $this->kit->consumer;
        $this->bus = $this->kit->bus;
        $this->runs = new ArchiveRuns($this->db, $this->schema, $this->clock);
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

    /** @return array<string, array{string}> */
    public static function nonBlockingModes(): array
    {
        return ['request' => ['request'], 'api' => ['api']];
    }

    /** Spec 9.5: where the queue runs only when something calls it, the admin's backup does not wait for that; it runs once the admin has the response. */
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

    public function testInWorkerModeItIsOnlyQueued(): void
    {
        $archives = $this->archives('worker');
        $uid = $archives->startArchive();

        $this->endTheRequest();
        $this->assertSame([], $archives->archived, 'nothing pinned: a consumer of its own takes it');
        $this->assertSame('pending', $this->stateOf($uid));

        $this->drain();
        $this->assertCount(1, $archives->archived);
        $this->assertSame('handled', $this->stateOf($uid));
    }

    /** In `worker`, a backup is never pinned: when the worker is gone, the end of requests stands in for it, the admin's own first. */
    public function testABackupIsLeftToTheFallbackWhenTheWorkerIsGone(): void
    {
        $archives = $this->archives('worker', consumerSeen: false);
        $uid = $archives->startArchive();

        $this->assertSame('pending', $this->stateOf($uid), 'queued as any other message');

        $this->endTheRequest();
        $this->assertCount(1, $archives->archived);
        $this->assertSame('handled', $this->stateOf($uid));
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

    /**
     * @param list<mixed>       $include
     * @param array<mixed>|null $only
     * @param list<string>      $expectedInclude
     * @param list<string>|null $expectedOnly
     */
    #[DataProvider('foldersAsTheApiMaySendThem')]
    public function testOnlyTheNamesOfFoldersReachTheBackup(array $include, ?array $only, array $expectedInclude, ?array $expectedOnly): void
    {
        $archives = $this->archives('request');

        $archives->startArchive(true, true, $include, [], $only);
        $this->endTheRequest();

        $this->assertSame($expectedInclude, $archives->archived[0]['foldersToInclude']);
        $this->assertSame($expectedOnly, $archives->archived[0]['onlyFolders'], 'no list at all is not an empty one');
    }

    /** @return array<string, array{list<mixed>, array<mixed>|null, list<string>, list<string>|null}> */
    public static function foldersAsTheApiMaySendThem(): array
    {
        return [
            'what is not a name is dropped, a number is one' => [['custom', ['nested'], null, 5, 'files'], null, ['custom', '5', 'files'], null],
            'an empty list of only folders is kept' => [[], [], [], []],
            'only folders are cleaned the same way' => [[], ['custom', new \stdClass(), 'files'], [], ['custom', 'files']],
        ];
    }

    public function testARestoreIsQueuedUnderTheNameOfTheFileNotItsPath(): void
    {
        $archives = $this->archives('request');
        $this->storage->write(ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . '/backup.zip', 'zip');

        $archives->startRestore('../../elsewhere/backup.zip');
        $this->endTheRequest();

        $this->assertSame('backup.zip', $archives->restored[0]['filename'], 'a path in the request does not travel in the message');
    }

    /** The queue did not take the message: nothing was started, and nothing is left to follow. */
    public function testABackupTheQueueRefusesIsNotStarted(): void
    {
        $bus = new AdminHandlersRefusingBus();
        $archives = $this->archives('request', bus: $bus);

        $uid = $archives->startArchive();

        $this->assertSame('', $uid, 'no uid for the browser to follow');
        $this->assertCount(1, $bus->asked);
        $this->assertSame([], $this->info(), 'and no run left to follow');
        $this->assertSame(0, $this->statistics->pendingCount());
    }

    public function testARestoreTheQueueRefusesIsNotStarted(): void
    {
        $archives = $this->archives('request', bus: new AdminHandlersRefusingBus());
        $this->storage->write(ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . '/backup.zip', 'zip');

        $this->assertSame('', $archives->startRestore('backup.zip'));
        $this->assertSame([], $this->info());
    }

    /**
     * A refused call (a remote wiki's `api/archives` arriving during the admin's backup) is not the run that set `archiving`: it must not lift it.
     *
     * Through the real archive(), not a failed message: whether the refusal lifts the status is archive()'s own. The run under way is
     * another process, which holds the archive lock.
     */
    public function testARefusedArchiveLeavesTheReadOnlyStatusOfTheRunThatIsUnderWay(): void
    {
        $archives = $this->archives('request');
        $archives->real = true;
        $letGo = QueueTestHolders::lockHeldByAnotherProcess($this->archiveLock());
        $this->setWikiStatus('archiving');
        $output = '';
        $refused = null;

        try {
            $archives->archive($output);
        } catch (\Exception $exception) {
            $refused = $exception;
        }

        try {
            $this->assertNotNull($refused, 'a second backup was accepted while one is under way');
            $this->assertStringContainsString(_t('AU_CANNOT_ARCHIVE'), $refused->getMessage());
            $this->assertSame('archiving', $archives->getWikiStatus());
        } finally {
            $this->assertSame(0, $letGo());
        }
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
        $this->assertSame('running', $this->wikiStatus(), 'no run holds the archive lock: the read-only status the failed run left is lifted');
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

    /** A failed run lifts only the status it set: an admin's hibernation, or the `archiving` of another run, which holds the archive lock, stays. */
    public function testAFailedArchiveLeavesAStatusItDidNotSet(): void
    {
        $archives = $this->archives('request');
        $archives->onArchive = static function (): void {
            throw new \RuntimeException('refused');
        };
        $letGo = QueueTestHolders::lockHeldByAnotherProcess($this->archiveLock());
        $this->setWikiStatus('archiving');
        $archives->startArchive();

        try {
            $this->endTheRequest();
            $this->assertSame('archiving', $archives->getWikiStatus());
        } finally {
            $this->assertSame(0, $letGo());
        }
    }

    /**
     * A run that failed leaves alone the status and the uid of a run still going.
     *
     * A remote wiki's `api/archives` arriving during an admin's backup is refused, since the wiki is
     * already `archiving`; its poll must leave the backup's read-only status, and its browser, alone.
     */
    public function testAFailedRunLeavesTheStatusAndTheUidOfARunStillGoing(): void
    {
        $archives = $this->archives('worker');
        $going = $archives->startArchive();
        $this->transport->claimAs(QueueConsumerMode::Worker);
        $this->transport->workFor(QueueTestHolders::passToken());
        $this->assertNotNull($this->transport->getById($this->messageOf($going)), 'a consumer is at work on it');
        $letGo = QueueTestHolders::lockHeldByAnotherProcess($this->archiveLock());
        $this->setWikiStatus('archiving');
        $refused = $archives->startArchive();
        $this->transport->moveToFailed($this->messageOf($refused), 'RuntimeException: the wiki is already archiving');

        try {
            $status = $archives->getUIDStatus($refused);

            $this->assertTrue($status['failed']);
            $this->assertSame('archiving', $archives->getWikiStatus(), 'the backup under way keeps the wiki read-only');
        } finally {
            $this->assertSame(0, $letGo());
        }
        $this->assertArrayHasKey($going, $this->info(), 'and its browser still follows it');
        $this->assertArrayNotHasKey($refused, $this->info(), 'the failed run, reported, is forgotten');
        $this->assertTrue($archives->getUIDStatus($going)['running']);
    }

    /**
     * A backup that was killed (by the host, PHP, or a limit that ran out) leaves the wiki read-only; the browser that follows it hears killed, and whoever reads the status next lifts it, the archive lock having gone with the run.
     *
     * How the queue comes to say killed is its own (QueueStatisticsSeenTest, QueueConsumerTest): the backup only hears the state.
     *
     * @param 'host'|'reaped'|'limit' $death
     */
    #[DataProvider('waysToBeKilled')]
    public function testAKilledArchiveReleasesTheReadOnlyStatus(string $mode, string $death): void
    {
        $archives = $this->archives($mode);
        $uid = $archives->startArchive();
        $this->dieAs($death, $this->messageOf($uid));

        $status = $archives->getUIDStatus($uid);

        $this->assertSame('killed', $status['state']);
        $this->assertTrue($status['failed']);
        $this->assertFalse($status['running']);
        $this->assertSame('running', $archives->getWikiStatus());
        $this->assertSame('running', $this->wikiStatus());
    }

    /** @return array<string, array{string, 'host'|'reaped'|'limit'}> */
    public static function waysToBeKilled(): array
    {
        return [
            'its host is gone' => ['request', 'host'],
            'reaped by a pass' => ['worker', 'reaped'],
            'past its limit before any pass' => ['request', 'limit'],
        ];
    }

    /** The browser that followed the backup may be long gone: the backups page reads the status too, and lifts what a dead run left. */
    public function testTheArchivingStatusLiftsWhatAKilledRunLeft(): void
    {
        $archives = $this->archives('worker');
        $uid = $archives->startArchive();
        $this->takeAndDie($this->messageOf($uid));

        $status = $archives->getArchivingStatus();

        $this->assertFalse($status['archiving']);
        $this->assertSame('running', $this->wikiStatus());
    }

    /** A run that never got a message to follow is forgotten. */
    public function testAnUidWithoutAMessageIsForgotten(): void
    {
        $archives = $this->archives('request');
        $old = $this->runs->open();

        $status = $archives->getUIDStatus($old);

        $this->assertFalse($status['started']);
        $this->assertSame([], $this->runs->all());
    }

    /** What a run prints and a STOP asked of it are read from the database, so another replica answers the page that follows it. */
    public function testARunIsFollowedFromAnotherConnection(): void
    {
        $archives = $this->archives('request');
        $uid = $archives->startArchive();
        $this->writeOutputOf($uid, "=== Checking free space ===\n");

        $elsewhere = new ArchiveRuns($this->db, $this->schema, $this->clock);
        $this->assertSame("=== Checking free space ===\n", $elsewhere->output($uid));
        $this->assertTrue($archives->stopArchive($uid));
        $this->assertTrue($elsewhere->stopRequested($uid));
        $this->assertSame([], glob($this->root . '/cache/*') ?: [], 'and nothing of it is a file');
    }

    /** Backups run in the queue's consumer, so whether this host can start a process of its own no longer decides whether it can back up. */
    public function testTheArchivingStatusDoesNotAskWhetherAProcessCanBeStarted(): void
    {
        $console = new QueueTestConsole();
        $console->answer = static fn (): null => null;
        $archives = $this->archives('request', console: $console);

        $status = $archives->getArchivingStatus();

        $this->assertTrue($status['canArchive'], 'a host that starts no process can still back up: nothing else than room and a writable folder is asked');
        $this->assertSame([], $console->commands, 'and the status started none to find out');
        $this->assertArrayNotHasKey('canExec', $status);
        $this->assertArrayNotHasKey('callAsync', $status);
    }

    /** A backup in `failed` is not sent again from the jobs page: it would run with nothing following it, on a visitor's request. */
    public function testAFailedArchiveIsNotReplayedByHand(): void
    {
        $archives = $this->archives('request');
        $archives->onArchive = static function (): void {
            throw new \RuntimeException('disk exploded');
        };
        $archives->startArchive();
        $this->endTheRequest();
        $copy = $this->failedCopyOf(CreateArchive::class);

        $this->assertFalse($this->transport->requeue($copy));
        $this->drain();

        $this->assertCount(1, $archives->archived, 'the copy did not run');
        $this->assertSame($copy, $this->failedCopyOf(CreateArchive::class), 'it stays where it failed');
    }

    /** "Retry all" on the jobs page would replay a restore half done: refused, whether or not the browser already saw the failure. */
    public function testAFailedRestoreIsNotReplayedByHand(): void
    {
        $archives = $this->archives('worker');
        $this->storage->write(ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . '/backup.zip', 'zip');
        $archives->onRestore = static function (): void {
            throw new \RuntimeException('half restored');
        };
        $uid = $archives->startRestore('backup.zip');
        $this->drain();
        $this->assertTrue($archives->getUIDStatus($uid)['failed']);

        $this->assertFalse($this->transport->requeue($this->failedCopyOf(RestoreArchive::class)));
        $this->drain();

        $this->assertCount(1, $archives->restored, 'the restore was not replayed');
        $this->assertSame(1, $this->statistics->failedCount());
    }

    /**
     * A backup killed midway (SIGKILL, the OOM killer, a host that ends the process: none of its code runs after) leaves `archiving` in the config file, and its lock goes with its process.
     *
     * While it runs, its status is its own. Once it is gone nobody needs to open the backups page:
     * the next pass reads the status, finds the lock free, lifts it, and consumes.
     */
    public function testAnArchiveKilledMidwayLeavesAStatusTheNextReaderLifts(): void
    {
        $archives = $this->archives('api');
        $kill = QueueTestHolders::lockHeldByAProcessToKill($this->archiveLock());
        $this->setWikiStatus('archiving');
        $this->kit->send('after');

        try {
            $this->assertTrue($archives->getArchivingStatus()['archiving'], 'the run under way holds the status');
            $this->assertSame(QueuePassReport::PAUSED, $this->consumer->drain(QueueConsumerMode::Api, $this->kit->passLimits->forPass())->stopReason);
            $this->assertSame('archiving', $this->wikiStatus());
        } finally {
            $kill();
        }

        $report = $this->consumer->drain(QueueConsumerMode::Api, $this->kit->passLimits->forPass());

        $this->assertSame('running', $this->wikiStatus(), 'the next pass lifted what the dead run left');
        $this->assertSame(QueuePassReport::EMPTY, $report->stopReason);
        $this->assertSame(['after'], $this->kit->handled, 'and took what waited');
        $this->assertFalse($archives->getArchivingStatus()['archiving']);
    }

    /**
     * A lock that cannot be taken at all (cache/ not writable, a lock file another user owns, a filesystem without locks) is not another backup:
     * the backup refuses to start, sets no status, and says what is wrong rather than to wait.
     */
    public function testAnArchiveWhoseLockCannotBeTakenSaysSoAndSetsNoStatus(): void
    {
        $notADirectory = (string)tempnam(sys_get_temp_dir(), 'yw-not-a-dir-');
        $archives = $this->archives('request', locks: QueueTestKit::locksIn($notADirectory));
        $archives->real = true;
        $before = (string)file_get_contents($this->kit->configFile());
        $output = '';
        $refused = null;

        try {
            $archives->archive($output);
        } catch (\Exception $exception) {
            $refused = $exception;
        } finally {
            @unlink($notADirectory);
        }

        $this->assertNotNull($refused, 'a backup ran without its lock');
        $this->assertSame(_t('ARCHIVE_LOCK_UNAVAILABLE', ['path' => FileLocks::pathOf(HibernationService::ARCHIVING_LOCK)]), $refused->getMessage());
        $this->assertStringContainsString('cache/archiving.lock', $refused->getMessage());
        $this->assertNotSame(_t('ARCHIVE_ALREADY_RUNNING'), $refused->getMessage());
        $this->assertSame($before, (string)file_get_contents($this->kit->configFile()), 'no status was set, and none lifted');
    }

    /**
     * The service under test in that mode, on this test's queue, progress folder, configuration and archive lock.
     *
     * In `worker`, the worker is seen at work a minute ago unless told otherwise:
     * a backup is left to a worker only while there is one.
     *
     * @param RuntimeLock|null $locks the locks the backup takes (default: the kit's, which the wiki's status is read on too)
     */
    private function archives(string $mode, bool $consumerSeen = true, ?MessageBusInterface $bus = null, ?ConsoleService $console = null, ?RuntimeLock $locks = null): QueueArchiveService
    {
        $this->mode = $mode;
        if ($consumerSeen && !QueueConsumerMode::from($mode)->endsByItself()) {
            QueueTestKit::recordPass($this->db, QueueConsumerMode::from($mode), $this->at('-1 hour'), $this->at('-1 minute'), '');
        }
        $settings = QueueTestKit::settings($this->mode);
        $this->trigger = $this->kit->requestTrigger($settings);
        $services = $this->getWiki()->services;
        $this->archives = new QueueArchiveService(
            new ConfigurationService(),
            $console ?? $services->get(ConsoleService::class),
            $services->get(DbService::class),
            $services->get(ParameterBagInterface::class),
            new HibernationService(new ParameterBag(['wiki_status' => 'running']), new FileLocks($this->kit->locks)),
            $services->get(UrlFormatter::class),
            $this->storage,
            new LocalFiles(),
            $bus ?? $this->bus,
            $this->statistics,
            $this->runs,
            new FileLocks($locks ?? $this->kit->locks),
        );

        return $this->archives;
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

    /** A pass of a consumer of its own, as the API route would run. */
    private function drain(): void
    {
        $this->consumer->drain(QueueConsumerMode::Api, $this->kit->passLimits->forPass());
    }

    /** @param 'host'|'reaped'|'limit' $death */
    private function dieAs(string $death, int $messageId): void
    {
        if ($death === 'host') {
            $this->transport->claimAs(QueueConsumerMode::Request);
            $this->transport->workFor(QueueTestHolders::passToken());
            $this->assertNotNull($this->transport->getById($messageId));
            QueueTestHolders::hand($this->db, $this->schema, $messageId, QueueTestHolders::gone($this->kit->lockDir));
            $this->setWikiStatus('archiving');

            return;
        }
        $this->takeAndDie($messageId);
        if ($death === 'reaped') {
            $this->transport->reapKilled();
        }
    }

    /** A consumer on another machine takes the message and dies with it: no ack, no failure, and the hour and the minute run out. */
    private function takeAndDie(int $messageId): void
    {
        $this->transport->claimAs(QueueConsumerMode::Worker);
        $this->transport->workFor(QueueTestHolders::passToken());
        $this->assertNotNull($this->transport->getById($messageId));
        QueueTestHolders::hand($this->db, $this->schema, $messageId, QueueTestHolders::token());
        $this->setWikiStatus('archiving');
        $this->clock->sleep(QueueTransport::ABSENT_HOLDER_SECONDS + 1);
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
        $run = $this->runs->get($uid);
        $this->assertIsArray($run);
        $this->assertIsInt($run['message'] ?? null, 'the uid records the message that runs it');

        return $run['message'];
    }

    /** @return array<string, mixed> the runs this wiki follows, by uid */
    private function info(): array
    {
        return $this->runs->all();
    }

    private function stateOf(string $uid): string
    {
        return $this->statistics->messageState($this->messageOf($uid));
    }

    private function writeOutputOf(string $uid, string $text): void
    {
        $this->runs->note($uid, ['output' => $text]);
    }

    private function setWikiStatus(string $status): void
    {
        $configuration = new ConfigurationService();
        $config = $configuration->getConfiguration($this->kit->configFile());
        $config->load();
        $config['wiki_status'] = $status;
        $this->assertTrue($configuration->write($config));
    }

    private function at(string $relative): string
    {
        return $this->clock->now()->modify($relative)->format('Y-m-d H:i:s');
    }

    /** The archive lock this test's services take and read, as another process addresses it. */
    private function archiveLock(): string
    {
        return $this->kit->lockDir . '/' . FileLocks::pathOf(HibernationService::ARCHIVING_LOCK);
    }

    private function wikiStatus(): string
    {
        $config = (new ConfigurationService())->getConfiguration($this->kit->configFile());
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
    /** Whether archive() is the real one, for a test of what it refuses. */
    public bool $real = false;

    public function archive(&$output, bool $savefiles = true, bool $savedatabase = true, array $foldersToInclude = [], array $foldersToExclude = [], ?array $hideConfigValuesParams = null, string $uid = '', ?array $onlyFolders = null): string
    {
        if ($this->real) {
            return parent::archive($output, $savefiles, $savedatabase, $foldersToInclude, $foldersToExclude, $hideConfigValuesParams, $uid, $onlyFolders);
        }
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
