<?php

namespace YesWiki\Contact\Scheduler;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Contact\Message\SendDigest;
use YesWiki\Contact\Service\MailSubscriptions;
use YesWiki\Kernel\Queue\ProvidesSchedule;

/**
 * The mailing-list digests: one recurring task per period, as long as somebody subscribed and the wiki did not turn them off.
 *
 * Each period goes out at 7 a.m. in the wiki's timezone: every day, on Mondays, on the first of
 * the month. A cron, and not a period counted from the last run, which made each digest leave a
 * little earlier than the one before. What has gone out is the Scheduler's checkpoint, and
 * nothing else: a digest sent by hand (`contact:send-digest`) moves nothing, and one that failed
 * is due again at its next 7 a.m.
 *
 * The queue builds the schedule anew at every due tick, from what is declared then, and forgets
 * the tasks that are no longer declared: with the digests disabled or nobody subscribed this
 * declares nothing, and the tasks leave the jobs page.
 */
class ContactSchedule implements ProvidesSchedule
{
    /** @var array<string, string> the cron of each period, in the wiki's timezone */
    private const CRONS = [
        'day' => '0 7 * * *',
        'week' => '0 7 * * 1',
        'month' => '0 7 1 * *',
    ];

    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly MailSubscriptions $subscriptions,
    ) {
    }

    /**
     * Subscriptions that cannot be read (no triples table) throw rather than count as nothing to schedule.
     *
     * The queue journals it and keeps the digest tasks it knew, which then show late on the jobs
     * page and in the health checks; declaring nothing had them forgotten at the next due tick, and
     * the digests stopped with nothing to say so. The other tasks are scheduled all the same.
     */
    public function recurringMessages(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $timezone = $this->timezone();
        $recurring = [];
        foreach (MailSubscriptions::PERIODS as $period) {
            $recurring[] = RecurringMessage::cron(self::CRONS[$period], new SendDigest($period), $timezone);
        }

        return $recurring;
    }

    /** Whether anything wants a digest at all. */
    private function isEnabled(): bool
    {
        if ($this->params->has('contact_disable_periodic_digest')
            && filter_var($this->params->get('contact_disable_periodic_digest'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return $this->subscriptions->hasAny();
    }

    /** The wiki's `timezone`, or PHP's when it names none: the one its pages show times in. */
    private function timezone(): string
    {
        $timezone = $this->params->has('timezone') ? $this->params->get('timezone') : null;

        return is_string($timezone) && $timezone !== '' ? $timezone : date_default_timezone_get();
    }
}
