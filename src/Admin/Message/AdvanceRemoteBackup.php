<?php

namespace YesWiki\Admin\Message;

use YesWiki\Kernel\Queue\Deduplicated;
use YesWiki\Kernel\Queue\NotReplayableByHand;
use YesWiki\Kernel\Queue\RetryPolicy;

/**
 * "Take the next step of the fetch from another wiki": one step of RemoteWikiArchive, or one slice of its download (ADR-0030, "Fetching a backup from another wiki").
 * It carries nothing: one fetch at a time, its job file says where it is. Each message queues the next, under UNIQUE_KEY.
 * Never retried: a step that fails has already given the fetch up.
 * Not replayed by hand either: its job file is what says where the fetch is, and a failed step has ended it.
 */
final class AdvanceRemoteBackup implements RetryPolicy, Deduplicated, NotReplayableByHand
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
