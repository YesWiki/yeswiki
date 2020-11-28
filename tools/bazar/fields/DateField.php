<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

class DateField extends BazarField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
    }
    
    public function formatInput()
    {
        if (!empty($this->value) && isset($this->entry[$this->propertyName . '_allday']) && $this->entry[$this->propertyName . '_allday'] == 0
             && isset($this->entry[$this->propertyName . '_hour']) && isset($this->entry[$this->propertyName . '_minutes'])) {
            $this->value = date("c", strtotime($this->value . ' ' . $this->entry[$this->propertyName . '_hour'] . ':' . $this->entry[$this->propertyName . '_minutes']));
        }
        return [$this->propertyName => $this->value];
    }
    
    public function renderField()
    {
        if( !$this->value ) return null;

        if (strlen($this->value) > 10) {
            $this->value = strftime('%d.%m.%Y - %H:%M', strtotime($this->value));
        } else {
            $this->value =  strftime('%d.%m.%Y', strtotime($this->value));
        }

        return $this->render('@bazar/fields/date.twig');
    }

    public function renderInput()
    {
        $GLOBALS['wiki']->addJavascriptFile('tools/bazar/libs/vendor/bootstrap-datepicker.js');

        $day = "";
        $hour = 0;
        $minute = 0;
        $hasTime = false;

        if (!empty($this->value)) {
            // Default value when entry exist
            $day = date("Y-m-d", strtotime($this->value));
            $hasTime = (strlen($this->value) > 10);
            if ($hasTime) {
                $result = explode('T', $this->value);
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
            'hasTime' => $hasTime
        ]);
    }
}
