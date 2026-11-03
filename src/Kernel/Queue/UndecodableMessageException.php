<?php

namespace YesWiki\Kernel\Queue;

use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;

/** A message the transport took but could not decode, already moved to `failed`, with the row it came from. */
final class UndecodableMessageException extends MessageDecodingFailedException
{
    public function __construct(
        public readonly int $messageId,
        public readonly string $messageType,
        MessageDecodingFailedException $cause,
    ) {
        parent::__construct($cause->getMessage(), 0, $cause);
    }
}
