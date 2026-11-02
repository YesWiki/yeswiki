<?php

namespace YesWiki\Kernel\Queue;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;

/** The cause of a failed handling as the queue keeps it, in a failed message's `last_error` and in its run's history row alike. */
final class QueueFailureCause
{
    /** Characters kept: enough to recognise it; the whole of it stays in the envelope. */
    public const MAX_LENGTH = 1000;

    /** "Class: message" of what the handler threw, from the stamp Messenger leaves on the envelope (AddErrorDetailsStampListener); null when it left none. */
    public static function of(Envelope $envelope): ?string
    {
        $details = $envelope->last(ErrorDetailsStamp::class);

        return $details === null ? null : self::fitted($details->getExceptionClass() . ': ' . $details->getExceptionMessage());
    }

    /** The same, from what the handler threw. */
    public static function ofThrowable(\Throwable $cause): string
    {
        return self::fitted($cause::class . ': ' . $cause->getMessage());
    }

    public static function fitted(string $cause): string
    {
        return mb_substr($cause, 0, self::MAX_LENGTH);
    }
}
