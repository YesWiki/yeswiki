<?php

namespace YesWiki\Admin\Service;

use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Identity\Service\AccountActivationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Entity\MaintenanceReport;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Search\Service\SearchIndexer;

/**
 * The wiki's housekeeping: the sweep itself, and who is allowed to start one (ticket 54).
 *
 * A request triggering it is the default and stays the default -- a wiki on shared hosting with no
 * crontab still gets its housekeeping. `maintenance_trigger` is how an operator who does have cron
 * stops a visitor paying for it, and `core:maintenance` is the sweep with an exit code.
 */
class MaintenanceService
{
    /** Which mechanism starts a sweep. */
    public const TRIGGER_SETTING = 'maintenance_trigger';

    /** The poor man's cron: whoever loads a page once the interval has elapsed sweeps. */
    public const TRIGGER_REQUEST = 'request';

    /** Only `./yeswicli core:maintenance` sweeps. */
    public const TRIGGER_CRON = 'cron';

    /** Run at most once every 30 minutes. */
    public const INTERVAL = 1800;

    public const LOCK_FILE = 'cache/maintenance.lock';

    /** When maintenance last ran, read before this run claimed the lock. */
    private ?int $previousRun = null;

    private RuntimeConfig $config;
    private DbService $dbService;
    private HibernationService $hibernation;
    private EventDispatcher $dispatcher;
    private Journal $journal;
    private UserManager $userManager;
    private AccountActivationService $activation;
    private SearchIndexer $indexer;
    private RuntimeLock $locks;

    public function __construct(
        RuntimeConfig $config,
        DbService $dbService,
        HibernationService $hibernation,
        EventDispatcher $dispatcher,
        Journal $journal,
        UserManager $userManager,
        AccountActivationService $activation,
        SearchIndexer $indexer,
        RuntimeLock $locks,
    ) {
        $this->config = $config;
        $this->dbService = $dbService;
        $this->hibernation = $hibernation;
        $this->dispatcher = $dispatcher;
        $this->journal = $journal;
        $this->userManager = $userManager;
        $this->activation = $activation;
        $this->indexer = $indexer;
        $this->locks = $locks;
    }

    /** Which mechanism this wiki has chosen, defaulting to the poor man's cron. */
    public function trigger(): string
    {
        $configured = strtolower(trim((string)$this->config->getValue(self::TRIGGER_SETTING, self::TRIGGER_REQUEST)));

        return $configured === self::TRIGGER_CRON ? self::TRIGGER_CRON : self::TRIGGER_REQUEST;
    }

    /** When the last sweep stamped the lock, or 0 if none ever has. */
    public function lastRun(): int
    {
        return $this->locks->lastTaken(self::LOCK_FILE);
    }

    /**
     * Whether this page view is the one that sweeps, stamping the lock when it is.
     *
     * With `maintenance_trigger: cron` the answer is always no, which is the whole of what that
     * setting does to a request.
     */
    public function dueOnRequest(): bool
    {
        if ($this->trigger() === self::TRIGGER_CRON) {
            return false;
        }

        return $this->claim();
    }

    /**
     * Take the interval lock, answering whether this process got it.
     *
     * @param bool $force stamp and sweep whatever the interval says -- `core:maintenance --force`
     */
    public function claim(bool $force = false): bool
    {
        $lastRun = $this->lastRun();
        if (!$force && time() - $lastRun < self::INTERVAL) {
            return false;
        }

        $this->previousRun = $lastRun ?: null;
        $this->locks->stamp(self::LOCK_FILE);

        return true;
    }

    /**
     * The housekeeping, and the one place an extension can hang its own on.
     *
     * Every step is bracketed on its own, so an extension having a bad afternoon does not stop the
     * password recovery keys expiring -- and the operator, or the Journal, is told which one threw.
     */
    public function sweep(): MaintenanceReport
    {
        $report = new MaintenanceReport();
        $startedAt = time();
        $began = microtime(true);
        $context = [
            'startedAt' => $startedAt,
            'interval' => self::INTERVAL,
            'previousRun' => $this->previousRun,
        ];

        $this->dispatcher->yesWikiDispatch('maintenance.before', $context);

        $this->step($report, 'revisions', function (): string {
            $purged = $this->purgePageRevisions();

            return $purged === null
                ? 'not purged (no pages_purge_time, or the wiki is hibernated)'
                : "{$purged} old revision(s) deleted";
        });

        $this->step($report, 'journal', function (): string {
            return $this->journal->prune() . ' Journal entrie(s) past their retention deleted';
        });

        $this->step($report, 'recovery-keys', function (): string {
            $this->userManager->purgeExpiredPasswordRecoveryKeys();

            return 'expired password recovery keys purged';
        });

        $this->step($report, 'activation-keys', function (): string {
            $this->activation->purgeExpiredActivationKeys();

            return 'expired account activation keys purged';
        });

        $this->step($report, 'search-queue', function (): string {
            $drained = $this->indexer->drain(200, 5);

            return "{$drained} Content(s) reindexed, {$this->indexer->pending()} still queued";
        });

        $report->took(microtime(true) - $began);

        $this->dispatcher->yesWikiDispatch(
            'maintenance.after',
            $context + ['duration' => $report->duration()]
        );

        return $report;
    }

    /**
     * @param callable(): string $work
     */
    private function step(MaintenanceReport $report, string $name, callable $work): void
    {
        try {
            $report->did($name, $work());
        } catch (\Throwable $failed) {
            $report->threw($name, $failed);
            $this->journal->error('maintenance step ' . $name . ' failed: ' . $failed->getMessage());
        }
    }

    /**
     * Delete page versions older than the newest one that is itself older than `pages_purge_time`, so a revision from before the window always survives.
     *
     * @return int|null how many rows went, or null when this wiki does not purge at all
     */
    private function purgePageRevisions(): ?int
    {
        $days = (int)$this->config->getValue('pages_purge_time', 0);
        if ($days <= 0 || $this->hibernation->isWikiHibernated()) {
            return null;
        }

        $pages = trim($this->dbService->prefixTable('pages'));
        $dateExpr = $this->dbService->dateSubDays($days);
        $ids = $this->dbService->loadAll(
            <<<SQL
            SELECT DISTINCT a.id FROM {$pages} a,{$pages} b
                WHERE a.latest = 'N'
                    AND b.latest = 'N'
                    AND a.time < {$dateExpr}
                    AND a.tag = b.tag
                    AND a.time < b.time
                    AND b.time < {$dateExpr}
            SQL
        );

        if ($ids === []) {
            return 0;
        }

        $this->dbService->query(
            "DELETE FROM {$pages} WHERE id IN (" . implode(', ', array_map(static fn (array $row): int => (int)$row['id'], $ids)) . ')'
        );

        return count($ids);
    }
}
