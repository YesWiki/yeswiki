<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

class RadioListField extends EnumField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'radio';

        $this->loadOptionsFromList();
    }

    public function renderInput($entry)
    {
        return $this->render('@bazar/inputs/radio.twig', [
            'options' => $this->options['label']
        ]);
    }

    public function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        $value = $entry !== '' ? $this->options['label'][$value] : '';
        return $this->render('@bazar/fields/radio.twig', [
            'value' => $value
        ]);
    }
}
