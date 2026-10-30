<?php

namespace YesWiki\Federation\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use YesWiki\Federation\Service\ActivityPubService;

/**
 * Posts a prepared activity to one recipient, signed with the identity the message carries.
 *
 * The form is not looked up: it may be gone by now (deleting a form sends a Delete for each of its
 * entries, and removes the form before they leave), and what a delivery needs from it is its key.
 * A failed delivery throws, which is what makes the queue retry it. What no retry can mend -- the
 * key is gone, or the activity names no actor, so nothing can sign -- is unrecoverable, and goes
 * straight to `failed` instead of knocking five times at a door that is not there.
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
