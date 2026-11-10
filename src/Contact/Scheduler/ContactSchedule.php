<?php

namespace YesWiki\Contact\Scheduler;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Contact\Message\SendDigest;
use YesWiki\Contact\Service\MailSubscriptions;
use YesWiki\Kernel\Queue\ProvidesSchedule;

/**
 * The mailing-list digests: one recurring task per period, as long as somebody subscribed and the wiki did not turn them off.
 * Each goes out at 7 a.m. in the wiki's timezone, the one the Scheduler reads a cron that names none in: every day, on Mondays, on the first of the month.
 */
class ContactSchedule implements ProvidesSchedule
{
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

    /** Subscriptions that cannot be read (no triples table) throw rather than count as nothing to schedule. */
    public function recurringMessages(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $recurring = [];
        foreach (MailSubscriptions::PERIODS as $period) {
            $recurring[] = RecurringMessage::cron(self::CRONS[$period], new SendDigest($period));
        }

        return $recurring;
    }

    /** Whether digests are on and somebody subscribed to one. */
    private function isEnabled(): bool
    {
        if ($this->params->has('contact_disable_periodic_digest')
            && filter_var($this->params->get('contact_disable_periodic_digest'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return $this->subscriptions->hasAny();
    }
}
