<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Queue\Deduplicated;

/** A test message that only needs doing once more while one with its key waits. */
final class QueueTestUniqueMessage implements Deduplicated
{
    public function __construct(public string $value, private string $key)
    {
    }

    public function deduplicationKey(): string
    {
        return $this->key;
    }
}
