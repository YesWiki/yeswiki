<?php

namespace YesWiki\Federation\Message;

use YesWiki\Kernel\Queue\RetryPolicy;
use YesWiki\Kernel\Queue\ToleratedFailure;

/**
 * "Post this activity to that recipient's inbox": one message per recipient of an activity the form publishes.
 *
 * The activity is carried as the JSON that was prepared when the entry was saved (its @context,
 * actor and id), since it depends on the state of the entry at that moment and every recipient must
 * get the same one. So is the signing identity: the form id, which names the key, and the actor it
 * acts as, which is the activity's own `actor`. The form itself is not read again, because deleting
 * a form queues a Delete for each of its entries and then removes the form before any of them is
 * delivered. The recipient is the actor's address, whose inbox is looked up at delivery time, as a
 * synchronous delivery does.
 *
 * Fediverse servers come and go: a delivery gets five retries (60 s, 5 min, 25 min, then 30 min
 * twice) before it is given up, and its giving up is a routine event (ToleratedFailure) that the
 * federation health checks judge, not the generic failed-jobs alert.
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

    /** The actor the activity is published as, and so signed as: what ActivityPubService::prepare() wrote into it; empty when it has none. */
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
