<?php

namespace YesWiki\Kernel\Service;

use Carbon\Carbon;

/** Reads a stored date in the wiki's timezone, and writes one for a reader in the page's language (end-users 23). */
class DateService
{
    public const DATE = 'date';
    public const DATETIME = 'datetime';
    public const SHORT = 'short';
    public const DAY_MONTH = 'day_month';
    public const DAY_MONTH_TIME = 'day_month_time';

    private const FORMATS = [
        self::DATE => 'LL',
        self::DATETIME => 'LLL',
        self::SHORT => 'L LT',
        self::DAY_MONTH => 'D MMMM',
        self::DAY_MONTH_TIME => 'D MMMM LT',
    ];

    public function __construct(
        private readonly ?LanguageService $languages = null,
    ) {
    }

    public function getDateTimeWithRightTimeZone(string $date): \DateTimeImmutable
    {
        try {
            $dateObj = new \DateTimeImmutable($date);
        } catch (\Exception $e) {
            throw new \Exception("date '$date' can not be converted to DateImmutable !", 0, $e);
        }

        $defaultTimeZone = new \DateTimeZone(date_default_timezone_get());
        $newDate = $dateObj->setTimeZone($defaultTimeZone);
        $anchor = '+00:00';
        if (substr($date, -strlen($anchor)) == $anchor) {
            $offsetToGmt = $defaultTimeZone->getOffset($newDate);

            $offSetAbs = abs($offsetToGmt);

            return ($offsetToGmt == 0)
            ? $newDate
            : (
                $offsetToGmt > 0
                ? $newDate->sub(new \DateInterval("PT{$offSetAbs}S"))
                : $newDate->add(new \DateInterval("PT{$offSetAbs}S"))
            );
        }

        return $newDate;
    }

    /** A date as a reader of this page reads it, in one of the styles above; '' for a value that is no date, rather than 1970. */
    public function display(string|int|\DateTimeInterface|null $date, string $style = self::DATE): string
    {
        $moment = $this->read($date);
        if ($moment === null) {
            return '';
        }

        $carbon = Carbon::instance($moment);
        $carbon->locale($this->languages?->preferredLanguage() ?: 'fr');

        return $carbon->isoFormat(self::FORMATS[$style] ?? self::FORMATS[self::DATE]);
    }

    private function read(string|int|\DateTimeInterface|null $date): ?\DateTimeImmutable
    {
        if ($date instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }
        if (is_int($date) || (is_string($date) && ctype_digit($date) && strlen($date) >= 9)) {
            return (new \DateTimeImmutable('@' . $date))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }
        if (!is_string($date) || trim($date) === '' || str_starts_with(trim($date), '0000-00-00')) {
            return null;
        }
        try {
            return $this->getDateTimeWithRightTimeZone(trim($date));
        } catch (\Throwable) {
            return null;
        }
    }
}
