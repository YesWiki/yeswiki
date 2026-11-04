<?php

namespace YesWiki\Kernel\Queue;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/** How often the scheduled task sent recurs, for QueueBus to journal with its dispatch; never stored with the message. */
final class TaskPeriodStamp implements NonSendableStampInterface
{
    public function __construct(public readonly int $seconds)
    {
    }
}
