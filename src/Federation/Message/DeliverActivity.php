<?php

namespace YesWiki\Federation\Message;

use YesWiki\Kernel\Queue\RetryPolicy;
use YesWiki\Kernel\Queue\ToleratedFailure;

/**
 * "Post this activity to that recipient's inbox": one message per recipient of an activity the form publishes,
 * carrying the activity's prepared JSON and the form id whose key signs it. Five retries, then a tolerated failure.
 */
final class DeliverActivity implements ToleratedFailure, RetryPolicy
{
    public const MAX_RETRIES = 5;

    public function __construct(
        public readonly string $formId,
        public readonly string $recipientUri,
        public readonly string $activityJson,
    ) {
    }

    /** The actor the activity is published and signed as, as ActivityPubService::prepare() wrote it; empty when it has none. */
    public function actorUri(): string
    {
        $activity = json_decode($this->activityJson, true);

        return is_array($activity) && is_string($activity['actor'] ?? null) ? $activity['actor'] : '';
    }

    public function maxRetries(): int
    {
        return self::MAX_RETRIES;
    }
}
