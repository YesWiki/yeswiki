<?php

namespace YesWiki\Admin\Message;

use YesWiki\Kernel\Queue\NotReplayableByHand;
use YesWiki\Kernel\Queue\RetryPolicy;
use YesWiki\Kernel\Queue\RunsUnbudgeted;

/**
 * "Put this backup back": a restore an admin asked for from the backups page (spec 9.5), reporting under the uid the browser follows.
 * Never retried: a restore that stopped half way has already replaced part of the wiki.
 */
final class RestoreArchive implements RetryPolicy, RunsUnbudgeted, NotReplayableByHand
{
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
