<?php

namespace YesWiki\Admin\Message;

use YesWiki\Kernel\Queue\RetryPolicy;

/**
 * "Take the next step of the fetch from another wiki": one step of RemoteWikiArchive, or one slice of its download (spec 9.6).
 *
 * It carries nothing: there is one fetch at a time, and its job file in the backups folder says
 * where it is. Each message queues the next one, so the fetch goes on without the admin's tab.
 * It is sent with UNIQUE_KEY, so that a fetch never has two chains of steps.
 *
 * It is never retried: a step that fails has already given the fetch up and tidied the remote
 * wiki, and the admin, who sees why, starts it again.
 */
final class AdvanceRemoteBackup implements RetryPolicy
{
    public const UNIQUE_KEY = 'remote-backup';

    public function maxRetries(): int
    {
        return 0;
    }
}
