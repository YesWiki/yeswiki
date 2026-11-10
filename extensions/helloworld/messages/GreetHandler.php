<?php

namespace YesWiki\HelloWorld\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Kernel\Service\Journal;

/** What the task does when it runs: a line in the Journal. */
#[AsMessageHandler]
final class GreetHandler
{
    public function __construct(private readonly Journal $journal)
    {
    }

    public function __invoke(Greet $task): void
    {
        $this->journal->info('Hello from the helloworld extension', ['action' => 'helloworld.greet']);
    }
}
