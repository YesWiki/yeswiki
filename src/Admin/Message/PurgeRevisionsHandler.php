<?php

namespace YesWiki\Admin\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\RuntimeConfig;

/**
 * Deletes the page versions older than the newest one that is itself older than `pages_purge_time`, so a revision from before the window always survives.
 *
 * It does nothing when the wiki does not purge (no `pages_purge_time`) and while it is
 * hibernated, an archive or an update being under way: the status as it is now, since a consumer
 * that has been up for an hour was built with the one from its start.
 */
#[AsMessageHandler]
final class PurgeRevisionsHandler
{
    public function __construct(
        private readonly RuntimeConfig $config,
        private readonly DbService $dbService,
        private readonly HibernationService $hibernation,
    ) {
    }

    public function __invoke(PurgeRevisions $task): void
    {
        $days = (int)$this->config->getValue('pages_purge_time', 0);
        if ($days <= 0 || $this->hibernation->isWikiHibernatedNow()) {
            return;
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
            return;
        }

        $this->dbService->query(
            "DELETE FROM {$pages} WHERE id IN (" . implode(', ', array_map(static fn (array $row): int => (int)$row['id'], $ids)) . ')'
        );
    }
}
