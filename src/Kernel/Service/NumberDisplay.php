<?php

namespace YesWiki\Kernel\Service;

/** Writes a stored number for a reader in the page's language, the same text with or without ext-intl for the languages the wiki ships (end-users 24). */
class NumberDisplay
{
    public const MAX_DECIMALS = 6;

    private const SEPARATORS = [
        'fr' => [',', "\u{202F}"],
        'en' => ['.', ','],
        'es' => [',', '.'],
        'ca' => [',', '.'],
        'eo' => [',', "\u{00A0}"],
        'eu' => [',', '.'],
        'it' => [',', '.'],
        'nl' => [',', '.'],
        'pt' => [',', '.'],
        'ro' => [',', '.'],
        'ru' => [',', "\u{00A0}"],
        'ta' => ['.', ','],
    ];

    public function __construct(
        private readonly ?LanguageService $languages = null,
    ) {
    }

    /** The number as this page's readers write it, with that many decimals, or up to six with no trailing zero when none is asked; the raw text when it is no number. */
    public function format(string $raw, ?int $decimals = null, ?string $language = null): string
    {
        $text = trim($raw);
        $normalised = str_replace(',', '.', $text);
        if ($text === '' || !is_numeric($normalised)) {
            return $raw;
        }
        $number = (float)$normalised;
        $language = strtolower(substr($language ?? ($this->languages?->preferredLanguage() ?: 'fr'), 0, 2));
        $fixed = $decimals !== null && $decimals >= 0;
        $places = $fixed ? min($decimals, self::MAX_DECIMALS) : self::MAX_DECIMALS;

        if (!isset(self::SEPARATORS[$language]) && class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($language, \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $fixed ? $places : 0);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $places);
            $written = $formatter->format($number);
            if (is_string($written)) {
                return $written;
            }
        }

        [$decimalPoint, $grouping] = self::SEPARATORS[$language] ?? self::SEPARATORS['en'];
        $written = number_format($number, $places, $decimalPoint, $grouping);
        if (!$fixed && str_contains($written, $decimalPoint)) {
            $written = rtrim(rtrim($written, '0'), $decimalPoint);
        }

        return $written === '-0' ? '0' : $written;
    }

    /** What a number input's `step` is for that many decimals: `any` when none is set. */
    public static function step(?int $decimals): string
    {
        if ($decimals === null || $decimals < 0) {
            return 'any';
        }

        return $decimals === 0 ? '1' : '0.' . str_repeat('0', min($decimals, self::MAX_DECIMALS) - 1) . '1';
    }

    /** A setting read from a field definition: an integer from 0 to 6, null when blank or unreadable. */
    public static function decimalsFrom(mixed $setting): ?int
    {
        if (is_int($setting)) {
            return max(0, min($setting, self::MAX_DECIMALS));
        }
        if (!is_string($setting) || !ctype_digit(trim($setting))) {
            return null;
        }

        return min((int)trim($setting), self::MAX_DECIMALS);
    }
}
