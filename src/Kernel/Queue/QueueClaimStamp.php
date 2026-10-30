<?php

namespace YesWiki\Kernel\Queue;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * The token of the claim under which the transport handed this envelope out: ack() and reject() touch the row only while it still carries it.
 *
 * A message whose holder was declared dead has been moved to `failed` meanwhile; the late ack of
 * the holder, if it was alive after all, must not erase that failure. Never sent: a retry is a new
 * message, claimed afresh.
 */
final class QueueClaimStamp implements NonSendableStampInterface
{
    public function __construct(public readonly string $token)
    {
    }
}
