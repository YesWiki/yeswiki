<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

class CheckboxListField extends EnumListField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'checkbox';
    }

    public function renderInput($entry)
    {
        return $this->render('@bazar/inputs/checkbox.twig', [
            'options' => $this->options['label'],
            'values' => $this->getValues($entry)
        ]);
    }

    public function renderStatic($entry)
    {
        $keys = $this->getValues($entry);
        $values = array() ;
        foreach ($this->options['label'] as $key_option => $option ) {
            if (in_array($key_option,$keys)) {
                $values[] = $option ;
            }
        }
        return (count($values) > 0) ? $this->render('@bazar/fields/checkbox.twig', [
            'values' => $values
        ]) : '' ;
    }
    
    public function getValues($entry)
    {
        $value = $this->getValue($entry);
        return explode(',', $value);
    }
    
    public function formatValuesBeforeSave($entry)
    {            
        foreach ($entry as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $key_val => $value) {
                    if ($value == 0 && $key_val == "END_INDEX NO_CHANGE_IT"){
                        unset($val[$key_val]) ;
                    }
                }
                
                if (empty(array_keys($val))) {
                    unset($entry[$key]) ;
                } else {
                    $entry[$key] = implode(',', array_keys($val)) ;
                }
            }
        }
        return [$this->propertyName => $this->getValue($entry)];
    }
    
}
