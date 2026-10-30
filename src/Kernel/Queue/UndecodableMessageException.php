<?php

namespace YesWiki\Kernel\Queue;

use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;

/**
 * A message the transport took but could not decode, and has already moved to `failed`.
 *
 * Messenger's own exception says only why decoding failed; this one also says which row it was,
 * so that the consumer can record the failure against the message like any other.
 */
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
