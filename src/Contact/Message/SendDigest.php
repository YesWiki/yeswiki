<?php

namespace YesWiki\Contact\Message;

use YesWiki\Contact\Service\MailSubscriptions;
use YesWiki\Kernel\Queue\RetryPolicy;
use YesWiki\Kernel\Queue\ScheduledTask;

/**
 * "Mail the pages people subscribed to at this period": the recurring task of one digest (`day`, `week` or `month`).
 *
 * The schedule declares one per period (ContactSchedule), so that a period that fails shows as
 * its own task and does not hide the others. It is never retried: by the time the handler
 * throws, some subscribers have had their mail, and a second run would send it to them again.
 * A failed digest waits in `failed` for an admin and comes back at the next floor.
 *
 * It carries when the period last went out as the schedule saw it. A message can wait a long
 * time in the queue, and the command may send the same period meanwhile: the handler does
 * nothing if the marker has moved since (SendDigestHandler).
 */
final class SendDigest implements ScheduledTask, RetryPolicy
{
    /**
     * @param string   $period     one of MailSubscriptions::PERIODS
     * @param int|null $lastSentAt the period's marker (a Unix time) when this was scheduled; null if it never went out
     */
    public function __construct(
        public readonly string $period,
        public readonly ?int $lastSentAt = null,
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
