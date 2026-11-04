<?php

namespace YesWiki\Admin\Message;

use YesWiki\Kernel\Queue\Deduplicated;
use YesWiki\Kernel\Queue\RetryPolicy;

/**
 * "Take the next step of the fetch from another wiki": one step of RemoteWikiArchive, or one slice of its download (spec 9.6).
 * It carries nothing: one fetch at a time, its job file says where it is. Each message queues the next, under UNIQUE_KEY.
 * Never retried: a step that fails has already given the fetch up.
 */
final class AdvanceRemoteBackup implements RetryPolicy, Deduplicated
{
    public const UNIQUE_KEY = 'remote-backup';

    public function deduplicationKey(): string
    {
        return self::UNIQUE_KEY;
    }

    public function maxRetries(): int
    {
        return 0;
    }
}
