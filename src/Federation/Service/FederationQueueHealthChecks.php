<?php

namespace YesWiki\Federation\Service;

use YesWiki\Federation\Message\DeliverActivity;
use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;
use YesWiki\Kernel\Service\Queue\QueueHealthChecks;
use YesWiki\Kernel\Service\Queue\QueueStatistics;

/**
 * What the admin is told about the deliveries to other servers (spec 11.2).
 *
 * Instances of the fediverse disappear all the time, so a failed delivery is routine and the
 * generic failed-jobs alert leaves it out (ToleratedFailure). Two checks judge it instead, because a
 * check has one severity: that the deliveries do not work at all is Broken, that some gave up is
 * only Degraded. Both stay silent when the queue cannot be read; `queue-tables` is the one to say so.
 */
class FederationQueueHealthChecks implements ProvidesHealthChecks
{
    /** Deliveries that must have finished, over the window, before "none succeeded" means something. */
    private const MINIMUM_FINISHED = 5;

    private const WINDOW_SECONDS = 86400;

    public function __construct(private readonly QueueStatistics $statistics)
    {
    }

    public function healthChecks(): array
    {
        return [
            HealthCheck::named('federation-delivery')
                ->label(_t('HEALTH_FEDERATION_DELIVERY'))
                ->says(_t('HEALTH_FEDERATION_DELIVERY_SAYS'))
                ->linkedTo(QueueHealthChecks::ROUTE)
                ->runs($this->allFailing(...)),

            HealthCheck::named('federation-delivery-failed')
                ->label(_t('HEALTH_FEDERATION_DELIVERY_FAILED'))
                ->degraded()
                ->says(_t('HEALTH_FEDERATION_DELIVERY_FAILED_SAYS'))
                ->linkedTo(QueueHealthChecks::ROUTE)
                ->offers(QueueHealthChecks::offeringFailedOf(DeliverActivity::class))
                ->runs($this->someGaveUp(...)),
        ];
    }

    /** Enough deliveries finished in the last day, and not one went through: this wiki cannot reach the fediverse. */
    private function allFailing(): ?string
    {
        try {
            $outcomes = $this->statistics->outcomesOfType(DeliverActivity::class, self::WINDOW_SECONDS);
        } catch (\Throwable) {
            return null;
        }
        $finished = $outcomes['handled'] + $outcomes['failed'] + $outcomes['killed'];

        return $finished >= self::MINIMUM_FINISHED && $outcomes['handled'] === 0
            ? _t('HEALTH_FEDERATION_DELIVERY_NONE', ['count' => $finished])
            : null;
    }

    /** Deliveries that used up their retries and wait in `failed`; those cut off by a time limit are `queue-killed`'s. */
    private function someGaveUp(): ?string
    {
        try {
            $groups = $this->statistics->failedGroups();
        } catch (\Throwable) {
            return null;
        }
        $count = 0;
        foreach ($groups as $group) {
            if ($group['type'] === DeliverActivity::class && $group['killedIn'] === null) {
                $count += $group['count'];
            }
        }

        return $count > 0 ? _t('HEALTH_FEDERATION_DELIVERY_FAILED_SOME', ['count' => $count]) : null;
    }
}
