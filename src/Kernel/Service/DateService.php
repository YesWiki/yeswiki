<?php

namespace YesWiki\Kernel\Service;

class DateService
{
    public function __construct(
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
}
