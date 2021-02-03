<?php

namespace YesWiki\Bazar\Field;

/**
 * @Field({"jour", "listedatedeb", "listedatefin"})
 */
class DateField extends BazarField
{
    protected function renderInput($entry)
    {
        $GLOBALS['wiki']->addJavascriptFile('tools/bazar/libs/vendor/bootstrap-datepicker.js');

        $day = "";
        $hour = 0;
        $minute = 0;
        $hasTime = false;
        $value = $this->getValue($entry);

        if (!empty($value)) {
            // Default value when entry exist
            $day = date("Y-m-d", strtotime($value));
            $hasTime = (strlen($value) > 10);
            if ($hasTime) {
                $result = explode('T', $value);
                list( $hour, $minute ) = array_map('intval', explode(':', $result[1]));
            }
        } elseif ($this->default && $this->default != '') {
            // Default value when new entry
            if ($this->default == 'today') {
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
            $value = date("c", strtotime($value . ' ' . $entry[$this->propertyName . '_hour'] . ':' . $entry[$this->propertyName . '_minutes']));
        }
        return [$this->propertyName => $value];
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if( !$value ) return null;

        if (strlen($value) > 10) {
            $value = strftime('%d.%m.%Y - %H:%M', strtotime($value));
        } else {
            $value =  strftime('%d.%m.%Y', strtotime($value));
        }

        return $this->render('@bazar/fields/date.twig', [
            'value' => $value
        ]);
    }
}
