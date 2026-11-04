<?php

namespace YesWiki\Contact\Message;

use YesWiki\Contact\Service\MailSubscriptions;
use YesWiki\Kernel\Queue\RetryPolicy;
use YesWiki\Kernel\Queue\ScheduledTask;

/**
 * "Mail the pages people subscribed to at this period": the recurring task of one digest (`day`, `week` or `month`).
 * Never retried: a second run would mail again those who already had theirs.
 */
final class SendDigest implements ScheduledTask, RetryPolicy
{
    public function __construct(
        public readonly string $period,
    ) {
        if (!in_array($period, MailSubscriptions::PERIODS, true)) {
            throw new \InvalidArgumentException("Unknown period '$period'");
        }
    }

    public function taskId(): string
    {
        return 'digest-' . $this->period;
    }

    public function label(): string
    {
        return _t('CONTACT_TASK_DIGEST_' . strtoupper($this->period));
    }

    public function maxRetries(): int
    {
        return 0;
    }
}
