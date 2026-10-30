<?php

namespace YesWiki\Contact\Scheduler;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Contact\Message\SendDigest;
use YesWiki\Contact\Service\DigestMarkers;
use YesWiki\Contact\Service\MailSubscriptions;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\RecurringAfterLastRun;

/**
 * The mailing-list digests: one recurring task per period, as long as somebody subscribed and the wiki did not turn them off.
 *
 * The floors are how long a period must go unsent before it is due again, those of the scheduler
 * of the housekeeping pass that this replaces: 23 hours, 7 days, 30 days.
 *
 * The queue builds the schedule anew at every due tick, from what is declared then, and forgets
 * the tasks that are no longer declared: with the digests disabled or nobody subscribed this
 * declares nothing, and the tasks leave the jobs page. A period starts a floor after the later of
 * its two markers (see DigestMarkers), as RecurringAfterLastRun says: an upgrade does not mail
 * again a digest that has just gone out, and one sent by hand or by cron postpones the scheduled one.
 *
 * An overdue period, built again while its run still waits, starts a bit later and is due again,
 * which the queue absorbs, one waiting run per task. The tick that first starts the Scheduler
 * cannot run anything, as for every task: the schedule starts then, and the digest is due at the
 * next tick.
 */
class ContactSchedule implements ProvidesSchedule
{
    /** @var array<string, int> seconds */
    private const FLOORS = [
        'day' => 82800,
        'week' => 604800,
        'month' => 2592000,
    ];

    private ClockInterface $clock;

    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly MailSubscriptions $subscriptions,
        private readonly DigestMarkers $markers,
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
    }

    /**
     * Subscriptions or a marker that cannot be read (no triples table, `private/digests` unreadable, a broken private bucket) throw rather than count as nothing to schedule.
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

        $now = $this->clock->now();
        $recurring = [];
        foreach (MailSubscriptions::PERIODS as $period) {
            $floor = self::FLOORS[$period];
            $sentAt = $this->markers->lastSentAt($period);
            $triedAt = $this->markers->lastTriedAt($period);
            $recurring[] = RecurringAfterLastRun::every(
                $floor,
                new SendDigest($period, $sentAt?->getTimestamp()),
                max($sentAt?->getTimestamp(), $triedAt?->getTimestamp()),
                $now
            );
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
}
