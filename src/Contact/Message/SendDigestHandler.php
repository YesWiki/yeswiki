<?php

namespace YesWiki\Contact\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Contact\Service\MailSubscriptions;
use YesWiki\Kernel\Service\HibernationService;

/** Mails one period's digest; a wiki hibernated during the pass mails nothing, and that is not a failure. */
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
        if ($this->hibernation->isWikiHibernatedNow()) {
            return;
        }

        $this->subscriptions->send($task->period);
    }
}
