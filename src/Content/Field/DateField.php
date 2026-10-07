<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Attribute\Field;
use YesWiki\Content\Service\EntryDateService;
use YesWiki\Kernel\Service\DateService as CoreDateService;

#[Field(['jour', 'listedatedeb', 'listedatefin'])]
class DateField extends BazarField
{
    use ContributesNoSearchableText;

    protected const FIELD_ENTRY_MODE = 6;
    public const ENTRY_MODE_TIME = 'time';
    public const ENTRY_MODE_ALL_DAY = 'allday';
    protected const EVENT_DATE_NAMES = ['bf_date_debut_evenement', 'bf_date_fin_evenement'];

    protected string $entryMode;

    /**
     * @param array<int|string, mixed> $values
     */
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->entryMode = trim((string)($values[self::FIELD_ENTRY_MODE] ?? ''));
    }

    /** Whether a new entry starts with hours. */
    public function entersTimeByDefault(): bool
    {
        if (in_array($this->entryMode, [self::ENTRY_MODE_TIME, self::ENTRY_MODE_ALL_DAY], true)) {
            return $this->entryMode === self::ENTRY_MODE_TIME;
        }

        return in_array($this->propertyName, self::EVENT_DATE_NAMES, true);
    }

    public function requiresTagBeforeFormatting()
    {
        return true;
    }

    protected function renderInput($entry)
    {
        $day = '';
        $hour = 0;
        $minute = 0;
        $hasTime = false;
        $value = $this->getValue($entry);

        if (!empty($value) && isset($entry[$this->propertyName . '_allday'])) {
            $day = substr($value, 0, 10);
            $hasTime = $entry[$this->propertyName . '_allday'] == 0;
            $hour = (int)($entry[$this->propertyName . '_hour'] ?? 0);
            $minute = (int)($entry[$this->propertyName . '_minutes'] ?? 0);
        } elseif (!empty($value)) {
            $day = $this->getService(CoreDateService::class)->getDateTimeWithRightTimeZone($value)->format('Y-m-d H:i');
            $hasTime = (strlen($value) > 10);
            if ($hasTime) {
                $result = explode(' ', $day);
                list($hour, $minute) = array_map('intval', explode(':', $result[1]));
                $day = $result[0];
            } else {
                $day = substr($day, 0, 10);
            }
        } else {
            if (!empty($this->default)) {
                $day = in_array($this->default, ['today', '1']) ? date('Y-m-d') : date('Y-m-d', strtotime($this->default));
            }
            $hasTime = $this->entersTimeByDefault();
            if ($hasTime) {
                $hour = min(23, (int)date('G') + 1);
                if ($this->propertyName === 'bf_date_fin_evenement') {
                    [$hour, $minute] = $hour < 23 ? [$hour + 1, 0] : [23, 55];
                }
            }
        }

        return $this->render('@core/inputs/date.twig', [
            'day' => $day,
            'hour' => $hour,
            'minute' => $minute,
            'hasTime' => $hasTime,
            'value' => $value,
            'data' => $entry["{$this->getPropertyName()}_data"] ?? [],
            'canRegisterMultipleEntries' => $this->getService(EntryDateService::class)->canRegisterMultipleEntries($entry),
        ]);
    }

    public function formatValuesBeforeSave($entry)
    {
        $return = [];
        if ($this->getPropertyname() === 'bf_date_fin_evenement') {
            if (!empty($entry['tag'])
                    && is_string($entry['tag'])) {
                $this->getService(EntryDateService::class)->followId($entry['tag']);
            }
            if (!$this->getService(EntryDateService::class)->canRegisterMultipleEntries($entry)) {
                if (isset($entry['bf_date_fin_evenement_data'])) {
                    unset($entry['bf_date_fin_evenement_data']);
                }
            } elseif (!empty($entry['bf_date_fin_evenement_data']['other'])) {
                unset($entry['bf_date_fin_evenement_data']['other']);
                if (!empty($entry['bf_date_fin_evenement_data'])) {
                    $return['bf_date_fin_evenement_data'] = $entry['bf_date_fin_evenement_data'];
                }
            }
        }
        $value = $this->getValue($entry);
        if (!empty($value) && isset($entry[$this->propertyName . '_allday']) && $entry[$this->propertyName . '_allday'] == 0
             && isset($entry[$this->propertyName . '_hour']) && isset($entry[$this->propertyName . '_minutes'])) {
            $value = $this->getService(CoreDateService::class)->getDateTimeWithRightTimeZone("$value {$entry[$this->propertyName . '_hour']}:{$entry[$this->propertyName . '_minutes']}")->format('c');
        }
        $return[$this->propertyName] = $value;
        $return['fields-to-remove'] = [
            $this->propertyName . '_allday',
            $this->propertyName . '_hour',
            $this->propertyName . '_minutes',
        ];
        if (empty($entry['bf_date_fin_evenement_data'])) {
            $return['fields-to-remove'][] = 'bf_date_fin_evenement_data';
        }

        return $return;
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if (!$value) {
            return '';
        }

        if (strlen($value) > 10) {
            $value = $this->getService(CoreDateService::class)->getDateTimeWithRightTimeZone($value)->format('d.m.Y - H:i');
        } else {
            $value = date('d.m.Y', strtotime($value));
        }

        $matches = [];
        $recurrenceBaseId = '';
        $data = [];
        if ($this->getPropertyname() === 'bf_date_fin_evenement'
                && !empty($entry['bf_date_fin_evenement_data'])) {
            if (EntryDateService::isLegacyRecurrenceChild($entry['bf_date_fin_evenement_data'])
                && preg_match('/^\{"recurrentParentId":"([^"]+)"}$/', $entry['bf_date_fin_evenement_data'], $matches)) {
                $recurrenceBaseId = $matches[1];
            } elseif (is_array($entry['bf_date_fin_evenement_data'])) {
                $data = $entry['bf_date_fin_evenement_data'];
            }
        }

        return $this->render('@core/fields/date.twig', [
            'value' => $value,
            'recurrenceBaseId' => $recurrenceBaseId,
            'data' => $data,
        ]);
    }

    protected function getValue($entry)
    {
        return $entry[$this->propertyName] ?? null;
    }
}
