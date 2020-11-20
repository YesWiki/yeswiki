<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

class DateField extends BazarField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
    }

    public function formatInput($entry)
    {
        if (!empty($entry[$this->entryId]) && isset($entry[$this->entryId . '_allday']) && $entry[$this->entryId . '_allday'] == 0) {
            if (isset($entry[$this->entryId . '_hour']) && isset($entry[$this->entryId . '_minutes'])) {
                return [
                    $this->entryId => date("c", strtotime($entry[$this->entryId] . ' ' . $entry[$this->entryId . '_hour'] . ':' . $entry[$this->entryId . '_minutes']))
                ];
            } else {
                return [$this->entryId => $entry[$this->entryId]];
            }
        } else {
            return [$this->entryId => isset($entry[$this->entryId]) ? $entry[$this->entryId] : ''];
        }
    }
    
    public function renderField($entry)
    {
        if( !$entry[$this->entryId] ) return null;

        if (strlen($entry[$this->entryId]) > 10) {
            $value = strftime('%d.%m.%Y - %H:%M', strtotime($entry[$this->entryId]));
        } else {
            $value =  strftime('%d.%m.%Y', strtotime($entry[$this->entryId]));
        }

        return $this->render('@bazar/fields/date.twig', [
            'value' => $value
        ]);
    }

    public function renderInput($entry)
    {
        if( $this->isInputHidden($entry) ) return '';

        $GLOBALS['wiki']->addJavascriptFile('tools/bazar/libs/vendor/bootstrap-datepicker.js');

        $day = "";
        $hour = 0;
        $minute = 0;
        $hasTime = false;

        if (isset($entry[$this->entryId]) && !empty($entry[$this->entryId])) {
            // Default value when entry exist
            $day = date("Y-m-d", strtotime($entry[$this->entryId]));
            $hasTime = (strlen($entry[$this->entryId]) > 10);
            if ($hasTime) {
                $result = explode('T', $entry[$this->entryId]);
                list( $hour, $minute ) = array_map('intval', explode(':', $result[1]));
            }
        } elseif (isset($this->default) && $this->default != '') {
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
            'hasTime' => $hasTime
        ]);
    }
}
