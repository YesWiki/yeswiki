<?php

namespace YesWiki\Federation\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use YesWiki\Federation\Exception\DeliveryRefused;
use YesWiki\Federation\Service\ActivityPubService;

/**
 * Posts a prepared activity to one recipient, signed with the identity the message carries. A recipient that is gone stops
 * being a follower, a refusal is not tried again, any other failure throws to be retried.
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

        try {
            $this->activityPub->deliverTo($message->activityJson, $signer, $message->formId, $message->recipientUri);
        } catch (DeliveryRefused $refused) {
            if ($refused->isGone()) {
                $this->activityPub->removeFollower(['id' => $message->formId], $message->recipientUri);

                return;
            }
            if ($refused->isFinal()) {
                throw new UnrecoverableMessageHandlingException("{$message->recipientUri} refused the activity: {$refused->getMessage()}", 0, $refused);
            }

            throw $refused;
        }
    }
}
