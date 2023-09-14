<?php

namespace YesWiki\Bazar\Field;

use YesWiki\Core\Service\DateService;

/**
 * @Field({"jour", "listedatedeb", "listedatefin"})
 */
class DateField extends BazarField
{
    protected function renderInput($entry)
    {
        $day = "";
        $hour = 0;
        $minute = 0;
        $hasTime = false;
        $value = $this->getValue($entry);

        if (!empty($value)) {
            // Default value when entry exist
            $day = $this->getService(DateService::class)->getDateTimeWithRightTimeZone($value)->format('Y-m-d H:i');
            $hasTime = (strlen($value) > 10);
            if ($hasTime) {
                $result = explode(' ', $day);
                list($hour, $minute) = array_map('intval', explode(':', $result[1]));
                $day = $result[0];
            } else {
                $day = substr($day,0,10);
            }
        } elseif (!empty($this->default)) {
            // Default value when new entry
            // 0 and 1 are present to manage olf format of this field
            if (in_array($this->default, ['today','1'])) {
                $day = date("Y-m-d");
            } else {
                $day = date("Y-m-d", strtotime($this->default));
            }
        }

        return $this->render('@bazar/inputs/date.twig', [
            'day' => $day,
            'hour' => $hour,
            'minute' => $minute,
            'hasTime' => $hasTime,
            'value' => $value
        ]);
    }

    public function formatValuesBeforeSave($entry)
    {
        $value = $this->getValue($entry);
        if (!empty($value) && isset($entry[$this->propertyName . '_allday']) && $entry[$this->propertyName . '_allday'] == 0
             && isset($entry[$this->propertyName . '_hour']) && isset($entry[$this->propertyName . '_minutes'])) {
            $value = $this->getService(DateService::class)->getDateTimeWithRightTimeZone("$value {$entry[$this->propertyName . '_hour']}:{$entry[$this->propertyName . '_minutes']}")->format('c');
        }
        return [$this->propertyName => $value,
            'fields-to-remove' =>[$this->propertyName . '_allday',$this->propertyName . '_hour',$this->propertyName . '_minutes']];
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if (!$value) {
            return "";
        }

        if (strlen($value) > 10) {
            $value = $this->getService(DateService::class)->getDateTimeWithRightTimeZone($value)->format('d.m.Y - H:i');
        } else {
            $value =  date('d.m.Y', strtotime($value));
        }

        return $this->render('@bazar/fields/date.twig', [
            'value' => $value
        ]);
    }

    protected function getValue($entry)
    {
        // TODO see if it is necessary to look for $_REQUEST
        // do not take default for this field
        return $entry[$this->propertyName] ?? null;
    }
}
