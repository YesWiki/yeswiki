<?php

namespace YesWiki\Federation\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use YesWiki\Federation\Service\ActivityPubService;

/**
 * Posts a prepared activity to one recipient, signed with the identity the message carries.
 * A failed delivery throws to be retried; one that nothing can sign is unrecoverable.
 */
#[AsMessageHandler]
final class DeliverActivityHandler
{
    public function __construct(private readonly ActivityPubService $activityPub)
    {
    }

    public function __invoke(DeliverActivity $message): void
    {
        $actorUri = $message->actorUri();
        if ($actorUri === '') {
            throw new UnrecoverableMessageHandlingException("The activity queued for {$message->recipientUri} names no actor to sign as");
        }
        $signer = $this->activityPub->signerFor($message->formId, $actorUri);
        if ($signer === null) {
            throw new UnrecoverableMessageHandlingException("The ActivityPub key of the form {$message->formId} is gone");
        }

        $this->activityPub->deliverTo($message->activityJson, $signer, $message->formId, $message->recipientUri);
    }
}
