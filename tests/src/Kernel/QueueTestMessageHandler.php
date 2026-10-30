<?php

namespace YesWiki\Test\Kernel;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

require_once 'tests/src/Kernel/QueueTestMessage.php';

/** The smallest handler the queue can find: it keeps what it was given, so a test can tell whether it ran. */
#[AsMessageHandler]
final class QueueTestMessageHandler
{
    /** @var list<string> */
    public array $handled = [];

    public function __invoke(QueueTestMessage $message): void
    {
        $this->handled[] = $message->value;
    }
}
