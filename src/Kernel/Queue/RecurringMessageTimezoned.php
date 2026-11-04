<?php

namespace YesWiki\Kernel\Queue;

use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Trigger\MessageProviderInterface;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

/**
 * A recurring task as a schedule provider declares it (ProvidesSchedule): Symfony's RecurringMessage, and the timezone it runs in.
 *
 * It checks that timezone itself. No timezone, or an empty one, is the wiki's (wikiTimezone()). A name PHP does not know (a typo
 * in the wiki's `timezone`, a trailing space) falls back to PHP's timezone, which is what the wiki's pages show times in, and is
 * kept (rejectedTimezone()) for the Scheduler to say at each tick. Symfony's cron() takes such a name and throws only when the
 * first run is asked for, which stopped every scheduled task of the wiki, not the digests alone.
 *
 * The timezone is the cron's (cron()), and the one whose wall clock the slots of every() fall on (ScheduleGrid): midnight, :00
 * and :30. RecurringMessage being final, this wraps it and reads as it does: getId(), getTrigger(), getMessages().
 */
final class RecurringMessageTimezoned implements MessageProviderInterface
{
    private function __construct(
        private readonly RecurringMessage $recurring,
        private readonly \DateTimeZone $timezone,
        private readonly ?string $rejectedTimezone,
    ) {
    }

    /** A task every $frequency (seconds, '24 hours', an ISO 8601 duration), on fixed slots of the wall clock of that timezone. */
    public static function every(string|int|\DateInterval $frequency, object $message, \DateTimeZone|string|null $timezone = null): self
    {
        [$zone, $rejected] = self::resolve($timezone);

        return new self(RecurringMessage::every($frequency, $message), $zone, $rejected);
    }

    /** A task at the times of a cron expression, in that timezone. */
    public static function cron(string $expression, object $message, \DateTimeZone|string|null $timezone = null): self
    {
        [$zone, $rejected] = self::resolve($timezone);

        return new self(RecurringMessage::cron($expression, $message, $zone), $zone, $rejected);
    }

    /** A task on a trigger of its own (one with a start or an end), whose slots fall on the wall clock of that timezone. */
    public static function trigger(TriggerInterface $trigger, object $message, \DateTimeZone|string|null $timezone = null): self
    {
        [$zone, $rejected] = self::resolve($timezone);

        return new self(RecurringMessage::trigger($trigger, $message), $zone, $rejected);
    }

    /**
     * The wiki's timezone: its `timezone`, which YesWikiInit makes PHP's when it exists, and PHP's otherwise. It is the one the
     * wiki's pages show times in.
     */
    public static function wikiTimezone(): \DateTimeZone
    {
        return new \DateTimeZone(date_default_timezone_get());
    }

    public function recurringMessage(): RecurringMessage
    {
        return $this->recurring;
    }

    /** The timezone the task runs in, always one that exists. */
    public function timezone(): \DateTimeZone
    {
        return $this->timezone;
    }

    /** The name it was declared in when PHP knows no such timezone, and it runs in the wiki's instead; null otherwise. */
    public function rejectedTimezone(): ?string
    {
        return $this->rejectedTimezone;
    }

    public function getId(): string
    {
        return $this->recurring->getId();
    }

    public function getTrigger(): TriggerInterface
    {
        return $this->recurring->getTrigger();
    }

    public function getMessages(MessageContext $context): iterable
    {
        return $this->recurring->getMessages($context);
    }

    /**
     * That timezone, the wiki's when none is named, and the name that was refused when PHP knows none such.
     *
     * @return array{\DateTimeZone, ?string}
     */
    private static function resolve(\DateTimeZone|string|null $timezone): array
    {
        if ($timezone instanceof \DateTimeZone) {
            return [$timezone, null];
        }
        if ($timezone === null || $timezone === '') {
            return [self::wikiTimezone(), null];
        }

        try {
            return [new \DateTimeZone($timezone), null];
        } catch (\Exception) {
            return [self::wikiTimezone(), $timezone];
        }
    }
}
