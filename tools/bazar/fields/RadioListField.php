<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

class RadioListField extends EnumListField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'radio';
    }

    public function renderInput($entry)
    {
        $value = $this->getValue($entry);
        return $this->render('@bazar/inputs/radio.twig', [
            'options' => $this->options['label'],
            'value' => ($value !== '') ? $value : ''
        ]);
    }

    public function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        return ($value !== '') ? $this->render('@bazar/fields/radio.twig', [
            'value' => $this->options['label'][$value]
        ]) : '' ;
    }
}
