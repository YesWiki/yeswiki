<?php

namespace YesWiki\Content\Service;

/** Reads a date cell of an imported CSV in one of a few explicit formats, day before month, never `m/d/Y`. */
final class CsvDateReader
{
    public const DAY_FORMATS = ['Y-m-d', 'd/m/Y', 'd.m.Y', 'd-m-Y'];
    public const TIME_FORMATS = ['Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s', \DATE_ATOM, 'd/m/Y H:i', 'd/m/Y H:i:s', 'd.m.Y H:i'];
    public const EXAMPLES = '2026-02-01, 2026-02-01 14:30, 01/02/2026, 01/02/2026 14:30, 01.02.2026';

    /** The date a cell holds, in that timezone unless the cell states its own offset; null when it is none of the formats. */
    public static function read(string $cell, \DateTimeZone $zone): ?\DateTimeImmutable
    {
        return self::parse($cell, $zone)[0] ?? null;
    }

    /** The value a date field stores for that cell: `Y-m-d` for a day, ISO 8601 with its offset for a time, null when it is not a date. */
    public static function storedValue(string $cell, \DateTimeZone $zone): ?string
    {
        $parsed = self::parse($cell, $zone);
        if ($parsed === null) {
            return null;
        }
        [$date, $hasTime] = $parsed;

        return $hasTime ? $date->format('c') : $date->format('Y-m-d');
    }

    /** @return array{0: \DateTimeImmutable, 1: bool}|null the date and whether the cell gave a time */
    private static function parse(string $cell, \DateTimeZone $zone): ?array
    {
        $cell = trim($cell);
        if ($cell === '') {
            return null;
        }
        foreach ([[self::DAY_FORMATS, false], [self::TIME_FORMATS, true]] as [$formats, $hasTime]) {
            foreach ($formats as $format) {
                $date = \DateTimeImmutable::createFromFormat('!' . $format, $cell, $zone);
                $errors = \DateTimeImmutable::getLastErrors();
                if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                    return [$date, $hasTime];
                }
            }
        }

        return null;
    }
}
