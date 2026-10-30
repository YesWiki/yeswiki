<?php

namespace YesWiki\Test\Kernel;

/** The smallest message the queue can carry: a value to find again on the other side. */
final class QueueTestMessage
{
    public function __construct(public string $value)
    {
    }
}
