<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Queue\RunsForItsSender;

/** A message that runs for its sender rather than on a visitor's request, as a backup an admin starts does. */
final class QueueTestSenderMessage implements RunsForItsSender
{
    public function __construct(public string $value)
    {
    }
}
