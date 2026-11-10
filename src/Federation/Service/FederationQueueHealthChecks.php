<?php

namespace YesWiki\Federation\Service;

use YesWiki\Federation\Message\DeliverActivity;
use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;
use YesWiki\Kernel\Service\Queue\QueueHealthChecks;
use YesWiki\Kernel\Service\Queue\QueueStatistics;

/**
 * What the admin is told about the deliveries to other servers (ADR-0030, "The per-module tasks"): Broken when none goes through;
 * silent when the queue cannot be read. A delivery that used up its retries is left in the history, not in `failed`.
 */
class FederationQueueHealthChecks implements ProvidesHealthChecks
{
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
        ];
    }

    /** Enough deliveries finished in the last day, and not one went through: this wiki cannot reach the fediverse. */
    private function allFailing(): ?string
    {
        try {
            $outcomes = $this->statistics->outcomes(self::WINDOW_SECONDS, DeliverActivity::class);
        } catch (\Throwable) {
            return null;
        }
        $finished = $outcomes['handled'] + $outcomes['failed'] + $outcomes['killed'];

        return $finished >= self::MINIMUM_FINISHED && $outcomes['handled'] === 0
            ? _t('HEALTH_FEDERATION_DELIVERY_NONE', ['count' => $finished])
            : null;
    }
}
