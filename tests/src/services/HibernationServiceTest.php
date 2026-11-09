<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Render\Service\HibernationNotice;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\ForcedParameterBag;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Test\Kernel\QueueTestHolders;
use YesWiki\Test\Kernel\QueueTestKit;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/ForcedParameterBag.php';
require_once 'tests/src/Kernel/QueueTestHolders.php';
require_once 'tests/src/Kernel/QueueTestKit.php';

/**
 * Regression tests for ticket 15 (security-core-split): HibernationService is the new, standalone home for the hibernation check formerly bundled inside SecurityController (renamed to InputFilter by wave-two ticket 03).
 */
#[CoversMethod(HibernationService::class, 'isWikiHibernated')]
#[CoversMethod(HibernationService::class, 'isWikiHibernatedNow')]
#[CoversMethod(HibernationService::class, 'wikiStatusNow')]
#[CoversMethod(HibernationNotice::class, 'getMessageWhenHibernated')]
class HibernationServiceTest extends YesWikiTestCase
{
    /** The config file of the test's own, which YESWIKI_CONFIG_FILE points at; null until a test asks for one. */
    private ?string $configFile = null;
    private string|false $previousConfigFile = false;
    /** The directory the archive lock of the test's own lives in, so that the dev wiki's cache/ is never touched. */
    private ?string $lockDir = null;

    protected function tearDown(): void
    {
        if ($this->configFile !== null) {
            putenv($this->previousConfigFile === false ? 'YESWIKI_CONFIG_FILE' : 'YESWIKI_CONFIG_FILE=' . $this->previousConfigFile);
            @unlink($this->configFile);
        }
        if ($this->lockDir !== null) {
            @unlink($this->archiveLock());
            @rmdir(\dirname($this->archiveLock()));
            @rmdir($this->lockDir);
        }
        parent::tearDown();
    }

    private function buildService(YesWikiRuntime $wiki, string $wikiStatus): HibernationService
    {
        $realParams = $wiki->services->get(ParameterBagInterface::class);
        $forcedParams = new ForcedParameterBag($realParams, ['wiki_status' => $wikiStatus]);

        return new HibernationService($forcedParams);
    }

    /** `archiving` is not one of them: what it says depends on the archive lock, below. */
    public function testIsWikiHibernatedForEachStatus(): void
    {
        $wiki = $this->getWiki();

        foreach (['hibernate', 'updating'] as $status) {
            $this->assertTrue($this->buildService($wiki, $status)->isWikiHibernated(), "wiki_status '{$status}' must be considered hibernated");
        }
        foreach (['running', '', 'unknown'] as $status) {
            $this->assertFalse($this->buildService($wiki, $status)->isWikiHibernated(), "wiki_status '{$status}' must not be considered hibernated");
        }
    }

    public function testGetMessageWhenHibernatedRendersHibernationNotice(): void
    {
        $wiki = $this->getWiki();

        $templateEngine = $wiki->services->get(TemplateEngine::class);
        $this->assertInstanceOf(TemplateEngine::class, $templateEngine);

        $notice = new HibernationNotice($this->buildService($wiki, 'hibernate'), $templateEngine);

        $this->assertStringContainsString(_t('WIKI_IN_HIBERNATION'), $notice->getMessageWhenHibernated());
    }

    /** The queue consumer runs for minutes or hours: it has to see the status an update or an admin writes to the config file meanwhile, not the one the container was built with. */
    public function testTheLiveStatusIsReadFromTheConfigFile(): void
    {
        $service = $this->serviceOnItsOwnFiles('running');

        $this->writeStatus('updating');
        $this->assertFalse($service->isWikiHibernated(), 'the parameters are what the request started with');
        $this->assertTrue($service->isWikiHibernatedNow());

        $this->writeStatus(null);
        $this->assertFalse($service->isWikiHibernatedNow());
    }

    /** A backup killed midway (by the host, PHP's limit, a `kill`) left `archiving`, and its lock is free: whoever reads the status lifts it. */
    public function testAStaleArchivingStatusIsLiftedWhenNoRunHoldsTheArchiveLock(): void
    {
        $service = $this->serviceOnItsOwnFiles('archiving');
        $this->assertSame(0, QueueTestHolders::lockHeldByAnotherProcess($this->archiveLock())(), 'a run took the lock, then went');
        $this->assertFileExists($this->archiveLock());

        $this->assertSame('running', $service->wikiStatusNow());
        $this->assertNull($this->statusInTheFile(), 'the config file no longer says `archiving`');
        $this->assertFalse($service->isWikiHibernatedNow());
        $this->assertFileExists($this->archiveLock(), 'the lock file stays: it is only ever locked and let go');
    }

    /** A backup started before the archive lock existed, and killed, left `archiving` with no lock file at all: nothing may keep the wiki read-only forever. */
    public function testAnArchivingStatusWithNoLockFileAtAllIsLifted(): void
    {
        $service = $this->serviceOnItsOwnFiles('archiving');
        $this->assertFileDoesNotExist($this->archiveLock());

        $this->assertSame('running', $service->wikiStatusNow());
        $this->assertNull($this->statusInTheFile());
        $this->assertFileDoesNotExist($this->archiveLock(), 'reading the status creates no lock');
    }

    /** A backup under way, in another process, holds the lock: the status is its own, and nobody lifts it. */
    public function testArchivingStaysWhileARunHoldsTheLock(): void
    {
        $service = $this->serviceOnItsOwnFiles('archiving', 'archiving');
        $letGo = QueueTestHolders::lockHeldByAnotherProcess($this->archiveLock());

        try {
            $this->assertSame('archiving', $service->wikiStatusNow());
            $this->assertTrue($service->isWikiHibernatedNow());
            $this->assertTrue($service->isWikiHibernated(), 'a request the backup started under stays read-only');
            $this->assertSame('archiving', $this->statusInTheFile());
        } finally {
            $this->assertSame(0, $letGo());
        }

        $this->assertFalse($service->isWikiHibernated(), 'once the run is gone, the next question lifts it');
        $this->assertNull($this->statusInTheFile());
    }

    /** A backup that starts between the probe that found the lock free and the lift takes the lock first: the lift gives way, and the backup's `archiving` stays. */
    public function testABackupTakingTheLockAtTheMomentOfTheLiftKeepsArchiving(): void
    {
        $backup = null;
        $service = $this->serviceOnItsOwnFiles('archiving', afterProbe: function () use (&$backup): void {
            $backup ??= QueueTestHolders::lockHeldByAnotherProcess($this->archiveLock());
        });
        $this->assertSame(0, QueueTestHolders::lockHeldByAnotherProcess($this->archiveLock())(), 'a run took the lock, then went');

        try {
            $this->assertSame('archiving', $service->wikiStatusNow(), 'the probe found the lock free, the lift could not take it');
            $this->assertSame('archiving', $this->statusInTheFile(), 'the status the backup set is not erased');
        } finally {
            $this->assertNotNull($backup);
            $this->assertSame(0, $backup());
        }
    }

    /** Once it holds the lock, the lift reads the config file again: what was written to it since the first read is not erased by an older copy. */
    public function testTheLiftReadsTheConfigFileAgainUnderTheLock(): void
    {
        $service = $this->serviceOnItsOwnFiles('archiving', afterProbe: function (): void {
            $this->writeStatus('updating');
        });
        $this->assertSame(0, QueueTestHolders::lockHeldByAnotherProcess($this->archiveLock())(), 'a run took the lock, then went');

        $this->assertSame('updating', $service->wikiStatusNow(), 'no longer `archiving`: nothing to lift');
        $this->assertSame('updating', $this->statusInTheFile());
    }

    /** A lock file that cannot be opened at all tells nothing of a run: the status is lifted, since nothing may keep the wiki read-only forever. */
    public function testAnArchivingStatusWhoseLockCannotBeOpenedIsLifted(): void
    {
        $service = $this->serviceOnItsOwnFiles('archiving');
        $this->assertSame(0, QueueTestHolders::lockHeldByAnotherProcess($this->archiveLock())(), 'a run took the lock, then went');
        chmod($this->archiveLock(), 0);

        try {
            if (is_readable($this->archiveLock())) {
                $this->markTestSkipped('this user opens a file whatever its permissions');
            }
            $this->assertSame('running', $service->wikiStatusNow());
            $this->assertNull($this->statusInTheFile());
        } finally {
            chmod($this->archiveLock(), 0o644);
        }
    }

    /**
     * Nobody may ever open the backups page of a wiki whose backup died: the container it boots with says `archiving`, and that is not taken on trust.
     *
     * isWikiHibernated() is what every write of a request asks, so the first request after the death lifts the status.
     */
    public function testAnUnattendedWikiLeavesArchivingAtItsNextRequest(): void
    {
        $service = $this->serviceOnItsOwnFiles('archiving', 'archiving');

        $this->assertFalse($service->isWikiHibernated());
        $this->assertNull($this->statusInTheFile(), 'lifted for the requests after this one too');
    }

    /** Only `archiving` has a lock to ask: a hibernation or an update stays until whoever set it lifts it. */
    #[DataProvider('statusesWithoutALock')]
    public function testOtherStatusesAreNeverLifted(string $status): void
    {
        $service = $this->serviceOnItsOwnFiles($status, $status);

        $this->assertSame($status, $service->wikiStatusNow());
        $this->assertTrue($service->isWikiHibernated());
        $this->assertSame($status, $this->statusInTheFile());
    }

    /** @return array<string, array{string}> */
    public static function statusesWithoutALock(): array
    {
        return ['hibernate' => ['hibernate'], 'updating' => ['updating']];
    }

    public function testHibernationServiceDependsOnNothingButParametersAndTheArchiveLock(): void
    {
        $ctor = (new \ReflectionClass(HibernationService::class))->getConstructor();
        $this->assertNotNull($ctor);
        $types = array_map(
            static fn (\ReflectionParameter $p): string => (string)$p->getType(),
            $ctor->getParameters()
        );

        $this->assertSame([ParameterBagInterface::class, '?' . RuntimeLock::class], $types);
    }

    /**
     * A service over a config file of the test's own that says $status, its archive lock in a directory of the test's own.
     *
     * @param string                  $booted     the status the container's parameters froze when the request began
     * @param (\Closure(): void)|null $afterProbe what happens elsewhere right after the service probed the archive lock, before it acts on the answer
     */
    private function serviceOnItsOwnFiles(string $status, string $booted = 'running', ?\Closure $afterProbe = null): HibernationService
    {
        $this->configFile = sys_get_temp_dir() . '/yw-config-' . bin2hex(random_bytes(4)) . '.php';
        $this->previousConfigFile = getenv('YESWIKI_CONFIG_FILE');
        putenv('YESWIKI_CONFIG_FILE=' . $this->configFile);
        $this->writeStatus($status);
        $this->lockDir = sys_get_temp_dir() . '/yw-archive-lock-' . bin2hex(random_bytes(4));
        mkdir($this->lockDir);
        $locks = QueueTestKit::locksIn($this->lockDir);
        if ($afterProbe !== null) {
            $locks = new class($locks, $afterProbe) extends RuntimeLock {
                public function __construct(private readonly RuntimeLock $locks, private readonly \Closure $afterProbe)
                {
                    parent::__construct();
                }

                public function lastTaken(string $path): int
                {
                    return $this->locks->lastTaken($path);
                }

                public function acquire(string $path)
                {
                    return $this->locks->acquire($path);
                }

                public function probe(string $path): ?bool
                {
                    $answer = $this->locks->probe($path);
                    ($this->afterProbe)();

                    return $answer;
                }
            };
        }

        return new HibernationService(new ParameterBag(['wiki_status' => $booted]), $locks);
    }

    /** Writes the status the way ArchiveService does, null for none. */
    private function writeStatus(?string $status): void
    {
        $this->assertNotNull($this->configFile);
        $configuration = new ConfigurationService();
        $config = $configuration->getConfiguration($this->configFile);
        $config->load();
        if ($status === null) {
            unset($config['wiki_status']);
        } else {
            $config['wiki_status'] = $status;
        }
        $this->assertTrue($configuration->write($config));
    }

    /** What the config file says now, null when it says nothing. */
    private function statusInTheFile(): ?string
    {
        $this->assertNotNull($this->configFile);
        $config = (new ConfigurationService())->getConfiguration($this->configFile);
        $config->load();
        $status = $config['wiki_status'] ?? null;

        return $status === null ? null : (string)$status;
    }

    private function archiveLock(): string
    {
        $this->assertNotNull($this->lockDir);

        return $this->lockDir . '/' . HibernationService::ARCHIVING_LOCK;
    }
}
