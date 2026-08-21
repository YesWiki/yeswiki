<?php

namespace YesWiki\Content\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\FieldRole;
use YesWiki\Content\Field\DateField;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\DateService;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\MarkdownFormatterService;

class IcalFormatter extends YesWikiController
{
    public const MAX_CHARS_BY_LINE = 74;

    protected DateService $dateService;
    protected EntryController $entryController;
    protected GeoJSONFormatter $geoJSONFormatter;
    protected ParameterBagInterface $params;
    protected MarkdownFormatterService $markdownFormatter;

    protected UrlFormatter $urlFormatter;

    protected FieldRoleResolver $fieldRoleResolver;
    protected FormManager $formManager;

    public function __construct(
        DateService $dateService,
        EntryController $entryController,
        FieldRoleResolver $fieldRoleResolver,
        FormManager $formManager,
        GeoJSONFormatter $geoJSONFormatter,
        ParameterBagInterface $params,
        MarkdownFormatterService $markdownFormatter,
        UrlFormatter $urlFormatter
    ) {
        $this->urlFormatter = $urlFormatter;
        $this->dateService = $dateService;
        $this->entryController = $entryController;
        $this->fieldRoleResolver = $fieldRoleResolver;
        $this->formManager = $formManager;
        $this->geoJSONFormatter = $geoJSONFormatter;
        $this->params = $params;
        $this->markdownFormatter = $markdownFormatter;
    }

    /**
     * format api response.
     *
     * @param array<array-key, array<string, mixed>>  $entries
     * @param int|string|array<array-key, mixed>|null $formId  a form id, or the array a query string can make of one
     * @param array<string, mixed>|null               $get     the query parameters
     */
    public function apiResponse(array $entries, $formId = null, ?array $get = null, string $filename = ''): Response
    {
        ob_start();

        if (!empty($formId) && is_array($formId)) {
            $formId = $formId[array_key_first($formId)];
        }
        if (is_array($formId) || strval(intval($formId)) !== strval($formId)) {
            $formId = null;
        }
        if (empty($filename)) {
            $filename = (empty($formId)) ? 'calendar' : 'calendar-form-' . $formId;
        }
        if (!empty($get['datefilter'])) {
            $entries = $this->entryController->filterEntriesOnDate($entries, $get['datefilter']);
        }
        $fileData = $this->formatToICAL($entries, $formId);

        $obContent = ob_get_contents();
        ob_end_clean();

        if (empty($fileData)) {
            if (!empty($obContent)) {
                $code = Response::HTTP_INTERNAL_SERVER_ERROR;

                return new Response($obContent, $code);
            }
            $code = Response::HTTP_OK;

            return new Response('', $code);
        }
        $code = Response::HTTP_OK;
        if (empty($filename)) {
            $filename = 'calendar';
        }
        if (!empty($obContent)) {
            $comment = $this->splitAtnthChar(self::MAX_CHARS_BY_LINE, 'X-COMMENT:' . str_replace(["\n", "\r"], ['\\n', '\\r'], $obContent) . "\r\n");
            $fileData = str_replace("BEGIN:VCALENDAR\r\n", "BEGIN:VCALENDAR\r\n" . $comment, $fileData);
        }
        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Headers' => 'X-Requested-With, Location, Slug, Accept, Content-Type',
            'Access-Control-Expose-Headers' => 'Location, Slug, Accept, Content-Type',
            'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS, DELETE, PUT, PATCH',
            'Access-Control-Max-Age' => '86400',
            'Content-Type' => 'text/Calendar',
            'Content-Disposition' => 'inline; filename=' . $filename . '.ics',
        ];

        return new Response($fileData, $code, $headers);
    }

    /**
     * get data grom entries in GeoJSON format.
     *
     * @param array<array-key, array<string, mixed>> $entries
     * @param int|string|null                        $formId
     *
     * @return string $fileData
     */
    public function formatToICAL(array $entries, $formId = null): string
    {
        $entries = array_filter($entries, function ($entry) {
            return !EntryDateService::isLegacyRecurrenceChild($entry['bf_date_fin_evenement_data'] ?? null);
        });

        $form = empty($formId) ? null : $this->formManager->getOne($formId);
        $startField = $this->fieldRoleResolver->propertyName($form, FieldRole::START_DATE);
        $endField = $this->fieldRoleResolver->propertyName($form, FieldRole::END_DATE);

        $entriesWithIcal = array_filter(array_map(function ($entry) use ($startField, $endField) {
            $ical = $this->getICALData($entry, $startField, $endField);
            if (empty($ical)) {
                return [];
            }

            return [
                'entry' => $entry,
                'ical' => $ical,
            ];
        }, $entries), function ($entry) {
            return !empty($entry);
        });

        $fileData = '';
        if (!empty($entriesWithIcal)) {
            $cache = [];
            foreach ($entriesWithIcal as $id => $extendedEntry) {
                $fileData .= $this->formatEvent($extendedEntry['entry'], $extendedEntry['ical'], $cache, $form);
            }
        }

        if (!empty($fileData)) {
            $fileData = $this->addHeaderAndFooter($fileData, $formId);
        }

        return $fileData;
    }

    /**
     * extract getICALData.
     *
     * @param array<string, mixed> $entry
     *
     * @return array<string, string> ['startDate'=>..,'endDate'=>..] or []
     */
    private function getICALData(array $entry, ?string $startField, ?string $endField): array
    {
        if ($startField === null || $endField === null) {
            return [];
        }
        if (!empty($entry[$startField]) && !empty($entry[$endField])) {
            $startDate = $this->dateService->getDateTimeWithRightTimeZone($entry[$startField]);
            $endDate = $this->dateService->getDateTimeWithRightTimeZone($entry[$endField]);

            if ($this->isAllDay(strval($entry[$endField]))) {
                $endDate = $endDate->add(new \DateInterval('P1D'));
            }
            if ($startDate->diff($endDate)->invert > 0) {
                $endDate = $startDate->add(new \DateInterval('PT1H'));
            }

            return [
                'startDate' => $startDate->format('Y-m-d H:i:s'),
                'endDate' => $endDate->format('Y-m-d H:i:s'),
            ];
        }

        return [];
    }

    /** check if is all day date. */
    protected function isAllDay(string $date): bool
    {
        return preg_match('/^[1-2][0-9]{3}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[1-2][0-9]|3[0-1])$/', $date) === 1;
    }

    /**
     * add header and footer.
     *
     * @param int|string|null $formId
     *
     * @return string $fileData
     */
    private function addHeaderAndFooter(string $fileData, $formId = null): string
    {
        $header = "BEGIN:VCALENDAR\r\n";
        $header .= "VERSION:2.0\r\n";
        $header .= $this->splitAtnthChar(self::MAX_CHARS_BY_LINE, 'PRODID:-//' . $this->configString('base_url')
            . '//YesWiki ' . $this->configString('yeswiki_version')
            . ' ' . $this->configString('yeswiki_release') . "//EN\r\n");
        if (!empty($formId) && intval($formId) == $formId) {
            $header .= $this->splitAtnthChar(self::MAX_CHARS_BY_LINE, 'SOURCE:' . $this->urlFormatter->href('forms/' . $formId . '/entries/ical', 'api') . "\r\n");
        } else {
            $header .= $this->splitAtnthChar(self::MAX_CHARS_BY_LINE, 'SOURCE:' . $this->urlFormatter->href('entries/ical', 'api') . "\r\n");
        }

        $footer = "END:VCALENDAR\r\n";

        $fileData = $header . $fileData . $footer;

        return $fileData;
    }

    /**
     * get formatted event.
     *
     * @param array<string, mixed>      $entry
     * @param array<string, mixed>      $icalData
     * @param array<array-key, mixed>   $cache    per-form-id geolocation memo, owned by GeoJSONFormatter
     * @param array<string, mixed>|null $form
     */
    private function formatEvent(array $entry, array $icalData, array &$cache, ?array $form = null): string
    {
        $output = "BEGIN:VEVENT\r\n";

        $output .= $this->chunck_split_except_last('UID:' . $entry['url'], self::MAX_CHARS_BY_LINE, "\r\n", ' ');
        $output .= $this->chunck_split_except_last('URL:' . $entry['url'], self::MAX_CHARS_BY_LINE, "\r\n", ' ');
        $output .= 'DTSTAMP' . $this->formatDate('') . "\r\n";
        $output .= 'DTSTART' . $this->formatDate($icalData['startDate']) . "\r\n";
        $output .= 'DTEND' . $this->formatDate($icalData['endDate']) . "\r\n";
        $output .= $this->formatRecurrenceLines($entry, $icalData);
        $output .= 'CREATED' . $this->formatDate($entry['created_at']) . "\r\n";
        $output .= 'DATE-MOD' . $this->formatDate($entry['updated_at']) . "\r\n";

        $title = (string)($entry['title'] ?? '');
        $output .= $this->splitAtnthChar(self::MAX_CHARS_BY_LINE, 'SUMMARY:' . $title . "\r\n");
        $output .= $this->splitAtnthChar(self::MAX_CHARS_BY_LINE, 'NAME:' . $title . "\r\n");
        $description = $this->fieldRoleResolver->value($form, $entry, FieldRole::DESCRIPTION);
        $decription = empty($description) ? '' : $this->renderAndStripTags((string)$description) . "\r\n";
        $decription .= 'Source: ' . $entry['url'];
        $output .= $this->splitAtnthChar(self::MAX_CHARS_BY_LINE, 'DESCRIPTION:' . str_replace(["\r", "\n"], [' ', '\\n'], $decription) . "\r\n");
        $location = '';
        $location .= (!empty($entry['bf_adresse'])) ? $entry['bf_adresse'] . ' ' : '';
        $location .= (!empty($entry['bf_code_postal'])) ? $entry['bf_code_postal'] . ' ' : '';
        $location .= (!empty($entry['bf_ville'])) ? $entry['bf_ville'] . ' ' : '';
        $location = trim($location);
        if (!empty($location)) {
            $output .= $this->splitAtnthChar(self::MAX_CHARS_BY_LINE, 'LOCATION:' . str_replace(["\r", "\n"], ' ', $location) . "\r\n");
        }
        $geo = $this->geoJSONFormatter->getGeoData($entry, $cache);
        if (!empty($geo)) {
            $output .= $this->splitAtnthChar(self::MAX_CHARS_BY_LINE, 'GEO:' . $geo['latitude'] . ';' . $geo['longitude'] . "\r\n");
        }
        if (!empty($entry['imagebf_image'])) {
            $baseUrl = $this->getBaseURL();
            $url = $baseUrl . 'files/' . $entry['imagebf_image'];
            $output .= $this->chunck_split_except_last('IMAGE;VALUE=URI;DISPLAY=BADGE:' . $url, self::MAX_CHARS_BY_LINE, "\r\n", ' ');
            $output .= $this->chunck_split_except_last('ATTACH:' . $url, self::MAX_CHARS_BY_LINE, "\r\n", ' ');
        }

        $output .= "END:VEVENT\r\n";

        return $output;
    }

    /**
     * format date.
     *
     * @return string $formattedDate
     */
    private function formatDate(string $date): string
    {
        return ':' . $this->formatDateValue($date);
    }

    /** format date value only (no leading ':'), for use inside a property value (e.g. */
    private function formatDateValue(string $date): string
    {
        $dateObject = empty($date) ? new \DateTimeImmutable() : new \DateTimeImmutable($date);
        $dateObject = $dateObject->setTimezone(new \DateTimeZone('UTC'));
        $localFormattedDate = $dateObject->format('Ymd');
        $localFormattedTime = $dateObject->format('His');

        return $localFormattedDate . 'T' . $localFormattedTime . 'Z';
    }

    /**
     * build RRULE (and EXDATE, if any) lines for a recurring entry, or '' if not recurrent.
     *
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $icalData ['startDate'=>'Y-m-d H:i:s', 'endDate'=>'Y-m-d H:i:s']
     */
    private function formatRecurrenceLines(array $entry, array $icalData): string
    {
        $data = $entry['bf_date_fin_evenement_data'] ?? null;
        if (!is_array($data) || ($data['isRecurrent'] ?? null) !== '1' || empty($data['repetition'])) {
            return '';
        }

        $freqs = ['d' => 'DAILY', 'w' => 'WEEKLY', 'm' => 'MONTHLY', 'y' => 'YEARLY'];
        $freq = $freqs[$data['repetition']] ?? null;
        if (empty($freq)) {
            return '';
        }

        $parts = ['FREQ=' . $freq];

        $step = intval($data['step'] ?? 1);
        if ($step > 1) {
            $parts[] = 'INTERVAL=' . $step;
        }

        $dayCodes = ['mon' => 'MO', 'tue' => 'TU', 'wed' => 'WE', 'thu' => 'TH', 'fri' => 'FR', 'sat' => 'SA', 'sun' => 'SU'];
        $days = is_array($data['days'] ?? null) ? array_values(array_filter($data['days'], 'is_string')) : [];
        $positions = [
            'fisrtOfMonth' => 1,
            'secondOfMonth' => 2,
            'thirdOfMonth' => 3,
            'forthOfMonth' => 4,
            'lastOfMonth' => -1,
        ];

        if ($data['repetition'] === 'w') {
            $byday = array_values(array_filter(array_map(function ($day) use ($dayCodes) {
                return $dayCodes[$day] ?? null;
            }, $days)));
            if (!empty($byday)) {
                $parts[] = 'BYDAY=' . implode(',', $byday);
            }
        } elseif (in_array($data['repetition'], ['m', 'y'], true)) {
            $whenInMonth = $data['whenInMonth'] ?? '';
            if ($whenInMonth === 'nthOfMonth' && isset($data['nth']) && is_scalar($data['nth'])) {
                $parts[] = 'BYMONTHDAY=' . intval($data['nth']);
            } elseif (isset($positions[$whenInMonth]) && !empty($days[0]) && isset($dayCodes[$days[0]])) {
                $parts[] = 'BYDAY=' . $positions[$whenInMonth] . $dayCodes[$days[0]];
            }
            if ($data['repetition'] === 'y' && !empty($data['month'])) {
                $months = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];
                if (isset($months[$data['month']])) {
                    $parts[] = 'BYMONTH=' . $months[$data['month']];
                }
            }
        }

        if (!empty($data['limitdate']) && is_string($data['limitdate'])) {
            $parts[] = 'UNTIL=' . $this->formatDateValue($data['limitdate'] . ' 00:00:00');
        } elseif (!empty($data['nbmax']) && is_scalar($data['nbmax'])) {
            $parts[] = 'COUNT=' . (min(intval($data['nbmax']), 600) + 1);
        }

        $output = $this->splitAtnthChar(self::MAX_CHARS_BY_LINE, 'RRULE:' . implode(';', $parts) . "\r\n");

        if (!empty($data['except']) && is_array($data['except'])) {
            $timeOfDay = substr($icalData['startDate'], 10);
            $exdates = array_values(array_filter(array_map(function ($exceptDate) use ($timeOfDay) {
                return is_string($exceptDate) ? $this->formatDateValue($exceptDate . $timeOfDay) : null;
            }, $data['except'])));
            if (!empty($exdates)) {
                $output .= $this->splitAtnthChar(self::MAX_CHARS_BY_LINE, 'EXDATE:' . implode(',', $exdates) . "\r\n");
            }
        }

        return $output;
    }

    /**
     * split at nth char.
     *
     * @return string $output
     */
    private function splitAtnthChar(int $length, string $input): string
    {
        $output = wordwrap($input, $length, " \r\n ", false);

        $output = wordwrap($output, $length, "\r\n ", true);

        $output = (string)preg_replace('/(?:\\r ?\\r\\n \\n|\\r\\n \\r\\n )/', "\r\n ", $output);

        $output = (string)preg_replace('/\\r\\n (?:\\r\\n)?$/', "\r\n", $output);

        return $output;
    }

    private function chunck_split_except_last(string $input, int $length = 76, string $escape = "\r\n", string $additionnalSeparator = ' '): string
    {
        $output = $this->chunk_split_unicode($input, $length, $escape . $additionnalSeparator);

        return substr($output, 0, -strlen($additionnalSeparator));
    }

    private function chunk_split_unicode(string $input, int $length = 76, string $escape = "\r\n"): string
    {
        $tmp = array_chunk(
            preg_split('//u', $input, -1, PREG_SPLIT_NO_EMPTY),
            $length
        );
        $str = '';
        foreach ($tmp as $t) {
            $str .= join('', $t) . $escape;
        }

        return $str;
    }

    /**
     * render and strip tags.
     *
     * @return string $output
     */
    private function renderAndStripTags(string $input): string
    {
        $renderedInput = $this->markdownFormatter->format($input);
        $cleanedRendered = strip_tags($renderedInput, '<a>');

        $output = (string)preg_replace('/<a.*href=(?:"|\')([^"\']*)(?:"|\').*>(.*)<\/a>/m', '$2 ($1)', $cleanedRendered);

        return $output;
    }

    /**
     * test if form is ICAL.
     *
     * @param array<string, mixed>|null $form
     */
    public function isICALForm(?array $form = null): bool
    {
        if (empty($form['prepared'] ?? null)) {
            return false;
        }
        $filteredFields = array_values(array_map(function ($field) {
            return $field->getPropertyName();
        }, array_filter($form['prepared'], function ($field) {
            return $field instanceof DateField;
        })));

        return !empty($filteredFields)
            && $this->fieldRoleResolver->hasRoles($form, FieldRole::START_DATE, FieldRole::END_DATE);
    }

    /** get base Url. */
    private function getBaseURL(): string
    {
        $baseUrl = $this->configString('base_url');
        if (substr($baseUrl, -1) == '?') {
            $baseUrl = substr($baseUrl, 0, -1);
        }

        return $baseUrl;
    }

    /** A configuration value as a string: the parameter bag types every value as a union. */
    private function configString(string $key): string
    {
        $value = $this->params->get($key);

        return is_scalar($value) ? (string)$value : '';
    }
}
