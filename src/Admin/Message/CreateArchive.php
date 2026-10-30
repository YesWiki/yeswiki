<?php

namespace YesWiki\Admin\Message;

use YesWiki\Kernel\Queue\RetryPolicy;

/**
 * "Make this backup": an archive an admin asked for, from the backups page, before an update or from another wiki fetching it (spec 9.5).
 *
 * The uid is the one ArchiveService::startArchive() handed the browser: the run reports its
 * progress in that uid's files under cache/archive and reads its STOP there, as the detached
 * `core:archive` used to. It is never retried: a backup that failed half way left nothing
 * worth finishing, and the admin who asked for it sees the failure and decides.
 */
final class CreateArchive implements RetryPolicy
{
    /**
     * @param list<string>      $foldersToInclude folders added to the usual ones
     * @param list<string>      $foldersToExclude folders left out besides the usual ones
     * @param list<string>|null $onlyFolders      the only folders the zip holds, null for the usual ones
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
