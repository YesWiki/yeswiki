<?php

namespace YesWiki\Federation\Exception;

/** A recipient's inbox answered with a status outside 2xx: the status says whether trying again can help. */
final class DeliveryRefused extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }

    /** Whether the recipient is gone for good, and should stop being a follower. */
    public function isGone(): bool
    {
        return $this->status === 410;
    }

    /** Whether the same delivery would be refused again: a client error, except a timeout or a rate limit. */
    public function isFinal(): bool
    {
        return $this->status >= 400 && $this->status < 500 && !\in_array($this->status, [408, 429], true);
    }
}
