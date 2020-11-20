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
        if (!empty($entry[$this->recordId]) && isset($entry[$this->recordId . '_allday']) && $entry[$this->recordId . '_allday'] == 0) {
            if (isset($entry[$this->recordId . '_hour']) && isset($entry[$this->recordId . '_minutes'])) {
                return [
                    $this->recordId => date("c", strtotime($entry[$this->recordId] . ' ' . $entry[$this->recordId . '_hour'] . ':' . $entry[$this->recordId . '_minutes']))
                ];
            } else {
                return [$this->recordId => $entry[$this->recordId]];
            }
        } else {
            return [$this->recordId => isset($entry[$this->recordId]) ? $entry[$this->recordId] : ''];
        }
    }
    
    public function renderField($entry)
    {
        if( !$entry[$this->recordId] ) return null;

        if (strlen($entry[$this->recordId]) > 10) {
            $value = strftime('%d.%m.%Y - %H:%M', strtotime($entry[$this->recordId]));
        } else {
            $value =  strftime('%d.%m.%Y', strtotime($entry[$this->recordId]));
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

        if (isset($entry[$this->recordId]) && !empty($entry[$this->recordId])) {
            // Default value when entry exist
            $day = date("Y-m-d", strtotime($entry[$this->recordId]));
            $hasTime = (strlen($entry[$this->recordId]) > 10);
            if ($hasTime) {
                $result = explode('T', $entry[$this->recordId]);
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
