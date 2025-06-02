<?php

namespace YesWiki\Bazar\Field;

use YesWiki\Bazar\Field\BazarField;

/**
 * @Field({"openinghours"})
 */
class OpeningHoursField extends BazarField
{
    protected function renderInput($entry)
    {

        dump("render opening hours input", $entry, $this->render('@bazar/inputs/openingHours.twig', [
            'opening_hours' => $this->getValue($entry),
        ]));
        return $this->render('@bazar/inputs/openingHours.twig', [
            'opening_hours' => $this->getValue($entry) ?? "",
        ]);
    }

    public function formatValuesBeforeSave($entry)
    {
        dump("render opening hours format Value");
        $return = [];
        if ($this->getPropertyname() === 'bf_date_fin_evenement') {
            if (!empty($entry['id_fiche'])
                    && is_string($entry['id_fiche'])) {
                $this->getService(DateService::class)->followId($entry['id_fiche']);
            }
            if (!$this->getService(DateService::class)->canRegisterMultipleEntries($entry)) {
                // clean data from entry because not possible to create repetition
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
        dump("render opening hours static");
        $value = $this->getValue($entry);
        if (!$value) {
            return '';
        }


        return $this->render('@bazar/fields/date.twig', [
            'value' => $value,
            'recurrenceBaseId' => $recurrenceBaseId,
            'data' => $data,
        ]);
    }

    protected function getValue($entry)
    {
        // TODO see if it is necessary to look for $_REQUEST
        // do not take default for this field
        return $entry[$this->propertyName] ?? null;
    }
}
