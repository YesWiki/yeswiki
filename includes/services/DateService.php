<?php

namespace YesWiki\Core\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

class DateService
{
    public function __construct(
    ) {
    }

    public function getDateTimeWithRightTimeZone(string $date): DateTimeImmutable
    {
        $dateObj = new DateTimeImmutable($date);
        if (!$dateObj) {
            throw new Exception("date '$date' can not be converted to DateImmutable !");
        }
        // retrieve right TimeZone from parameters
        $defaultTimeZone = new DateTimeZone(date_default_timezone_get());
        if (!$defaultTimeZone) {
            $defaultTimeZone = new DateTimeZone('GMT');
        }
        $newDate = $dateObj->setTimeZone($defaultTimeZone);
        $anchor = '+00:00';
        if (substr($date, -strlen($anchor)) == $anchor) {
            // it could be an error
            $offsetToGmt = $defaultTimeZone->getOffset($newDate);
            // be careful to offset time because time is changed by setTimeZone
            $offSetAbs = abs($offsetToGmt);

            return ($offsetToGmt == 0)
            ? $newDate
            : (
                $offsetToGmt > 0
                ? $newDate->sub(new DateInterval("PT{$offSetAbs}S"))
                : $newDate->add(new DateInterval("PT{$offSetAbs}S"))
            );
        }

        return $newDate;
    }
}
