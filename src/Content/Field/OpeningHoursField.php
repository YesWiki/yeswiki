<?php

namespace YesWiki\Content\Field;

use Carbon\Carbon;
use YesWiki\Content\Attribute\Field;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\LanguageService;

/** Opening hours in the OpenStreetMap syntax: edited as rows of days and times, shown as today's hours with the week unfolding. */
#[Field(['openinghours'])]
class OpeningHoursField extends BazarField
{
    use ContributesNoSearchableText;

    private const DAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

    protected function renderInput($entry)
    {
        $value = (string)($this->getValue($entry) ?? '');
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/inputs/opening-hours.js', false, true);
        $this->getService(AssetRegistry::class)->addCssFile('styles/yw-entries.css');

        return $this->render('@core/inputs/openingHours.twig', [
            'opening_hours' => $value,
            'intervals' => self::intervals($value),
            'days' => $this->dayNames(),
        ]);
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if (!$value) {
            return '';
        }
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/vendor/opening_hours/opening_hours.js');
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/fields/opening-hours.js', false, true);
        $this->getService(AssetRegistry::class)->addCssFile('styles/yw-entries.css');

        return $this->render('@core/fields/openingHours.twig', [
            'opening_hours' => $value,
        ]);
    }

    protected function getValue($entry)
    {
        return $entry[$this->propertyName] ?? null;
    }

    /**
     * The rows the editor starts from, read from the subset of the syntax it writes: `Mo,Tu 09:00-12:00,14:00-18:00;Sa 10:00-12:00`.
     *
     * @return list<array{days: list<string>, hours: list<array{start: string, end: string}>}>
     */
    public static function intervals(string $value): array
    {
        $intervals = [];
        foreach (array_filter(array_map('trim', explode(';', $value))) as $rule) {
            [$days, $hours] = array_pad(preg_split('/\s+/', $rule, 2) ?: [], 2, '');
            $intervals[] = [
                'days' => array_values(array_filter(array_map('trim', explode(',', $days)))),
                'hours' => array_values(array_map(
                    static function (string $range): array {
                        [$start, $end] = array_pad(explode('-', trim($range), 2), 2, '');

                        return ['start' => $start, 'end' => $end];
                    },
                    array_filter(explode(',', $hours), static fn (string $range): bool => trim($range) !== '')
                )),
            ];
        }
        foreach ($intervals as &$interval) {
            if ($interval['hours'] === []) {
                $interval['hours'][] = ['start' => '', 'end' => ''];
            }
        }

        return $intervals === [] ? [['days' => [], 'hours' => [['start' => '', 'end' => '']]]] : $intervals;
    }

    /** @return array<string, string> the OpenStreetMap day codes, each with its name in the reader's language */
    private function dayNames(): array
    {
        $monday = Carbon::parse('2025-03-10');
        $monday->locale($this->getService(LanguageService::class)->preferredLanguage());
        $names = [];
        foreach (self::DAYS as $offset => $code) {
            $names[$code] = $monday->copy()->addDays($offset)->dayName;
        }

        return $names;
    }
}
