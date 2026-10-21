<?php

namespace YesWiki\Test\Federation;

use Psr\Log\AbstractLogger;

/** A Journal stand-in that keeps what it is told, for tests that check what ActivityPub logged. */
class RecordingJournal extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /** @param array<string, mixed> $context */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => (string)$level, 'message' => (string)$message, 'context' => $context];
    }
}
