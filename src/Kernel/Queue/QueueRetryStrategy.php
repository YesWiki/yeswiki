<?php

namespace YesWiki\Kernel\Queue;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * When a failed message is tried again: 60 s, 5 min, 25 min (capped at 30 min), three times unless the message says otherwise (RetryPolicy, RetryDelays).
 * A message nobody handles is never retried: it goes straight to `failed`.
 */
final class QueueRetryStrategy implements RetryStrategyInterface
{
    public const DEFAULT_MAX_RETRIES = 3;
    private const DELAY_MILLISECONDS = 60000;
    private const MULTIPLIER = 5;
    private const MAX_DELAY_MILLISECONDS = 1800000;

    public function isRetryable(Envelope $message, ?\Throwable $throwable = null): bool
    {
        if ($throwable instanceof NoHandlerForMessageException) {
            return false;
        }

        return self::strategyFor($message)->isRetryable($message, $throwable);
    }

    public function getWaitingTime(Envelope $message, ?\Throwable $throwable = null): int
    {
        $own = $message->getMessage();
        if ($own instanceof RetryDelays) {
            $delays = $own->retryDelays();

            return $delays[min(RedeliveryStamp::getRetryCountFromEnvelope($message), \count($delays) - 1)] * 1000;
        }

        return self::strategyFor($message)->getWaitingTime($message, $throwable);
    }

    private static function strategyFor(Envelope $envelope): MultiplierRetryStrategy
    {
        $message = $envelope->getMessage();
        $maxRetries = $message instanceof RetryPolicy ? max(0, $message->maxRetries()) : self::DEFAULT_MAX_RETRIES;

        return new MultiplierRetryStrategy($maxRetries, self::DELAY_MILLISECONDS, self::MULTIPLIER, self::MAX_DELAY_MILLISECONDS);
    }
}
