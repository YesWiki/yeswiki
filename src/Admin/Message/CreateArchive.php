<?php

namespace YesWiki\Admin\Message;

use YesWiki\Kernel\Queue\NotReplayableByHand;
use YesWiki\Kernel\Queue\RetryPolicy;
use YesWiki\Kernel\Queue\RunsUnbudgeted;

/**
 * "Make this backup": an archive asked for from the backups page, before an update, or by another wiki fetching it (spec 9.5).
 * It reports under the uid ArchiveService::startArchive() handed the browser (its files under cache/archive, STOP included).
 * Never retried: a backup that failed half way left nothing worth finishing.
 */
final class CreateArchive implements RetryPolicy, RunsUnbudgeted, NotReplayableByHand
{
    /**
     * @param list<string>      $foldersToInclude
     * @param list<string>      $foldersToExclude
     * @param list<string>|null $onlyFolders
     */
    public function __construct(
        public readonly string $uid,
        public readonly bool $saveFiles,
        public readonly bool $saveDatabase,
        public readonly array $foldersToInclude,
        public readonly array $foldersToExclude,
        public readonly ?array $onlyFolders,
    ) {
    }

    public function maxRetries(): int
    {
        return 0;
    }
}
