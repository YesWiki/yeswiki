<?php

namespace YesWiki\Contact\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Contact\Service\DigestMarkers;
use YesWiki\Contact\Service\MailSubscriptions;
use YesWiki\Kernel\Service\HibernationService;

/**
 * Mails one period's digest, and notes it went out only if it reached everybody.
 *
 * MailSubscriptions::send() throws after trying every recipient when some could not be reached,
 * so the "sent" marker below is not written for a digest that failed: it is not taken for one
 * that was served, and the failure is the queue's to show. The "tried" marker is written before,
 * so that the schedule waits a floor from a failed attempt rather than trying again at its next
 * tick. A hibernated wiki (an archive, an update) is read-only and mails nothing; that is not a
 * failure and leaves no trace, so the digest comes due again as soon as the wiki wakes.
 */
#[AsMessageHandler]
final class SendDigestHandler
{
    public function __construct(
        private readonly MailSubscriptions $subscriptions,
        private readonly DigestMarkers $markers,
        private readonly HibernationService $hibernation,
    ) {
    }

    public function __invoke(SendDigest $task): void
    {
        // The status as it is now: a consumer that has been up for an hour was built with the one from its start.
        if ($this->hibernation->isWikiHibernatedNow()) {
            return;
        }

        // The period went out since this was scheduled (by the command, say): sending it again would mail everybody twice.
        if ($this->markers->lastSentAt($task->period)?->getTimestamp() !== $task->lastSentAt) {
            return;
        }

        $this->markers->markTried($task->period);
        $this->subscriptions->send($task->period);
        $this->markers->markSent($task->period);
    }
}
