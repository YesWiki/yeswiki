<?php

namespace YesWiki\Kernel\Queue;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/** The token of the claim under which the transport handed this envelope out: ack() and reject() touch the row only while it carries it. */
final class QueueClaimStamp implements NonSendableStampInterface
{
    public function __construct(public readonly string $token)
    {
    }
}
