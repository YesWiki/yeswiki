<?php

namespace YesWiki\Admin\Message;

use YesWiki\Kernel\Queue\RetryPolicy;

/**
 * "Put this backup back": a restore an admin asked for from the backups page (spec 9.5).
 *
 * Like CreateArchive, it reports under the uid the browser follows. It is never retried: a
 * restore that stopped half way has already replaced part of the wiki, and running it again
 * on its own, later, is the last thing an admin would want.
 */
final class RestoreArchive implements RetryPolicy
{
    /** @param string $filename the archive's name in the backups folder */
    public function __construct(
        public readonly string $uid,
        public readonly string $filename,
        public readonly bool $restoreFiles,
        public readonly bool $restoreDatabase,
        public readonly bool $rewriteUrls,
    ) {
    }

    public function maxRetries(): int
    {
        return 0;
    }
}
