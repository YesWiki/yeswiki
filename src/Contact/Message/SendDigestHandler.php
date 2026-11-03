<?php

namespace YesWiki\Contact\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Contact\Service\MailSubscriptions;
use YesWiki\Kernel\Service\HibernationService;

/**
 * Mails one period's digest.
 *
 * MailSubscriptions::send() throws after trying every recipient when some could not be reached,
 * and the failure is the queue's to show. A hibernated wiki (an archive, an update) is read-only
 * and mails nothing; that is not a failure. The consumer takes nothing while the wiki sleeps, so
 * this is a wiki put to sleep during the pass, and the digest goes at the period's next 7 a.m.
 */
#[AsMessageHandler]
final class SendDigestHandler
{
    public function __construct(
        private readonly MailSubscriptions $subscriptions,
        private readonly HibernationService $hibernation,
    ) {
    }

    public function __invoke(SendDigest $task): void
    {
        // The status as it is now: a consumer that has been up for an hour was built with the one from its start.
        if ($this->hibernation->isWikiHibernatedNow()) {
            return;
        }

        $this->subscriptions->send($task->period);
    }
}
