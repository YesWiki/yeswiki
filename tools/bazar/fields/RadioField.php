<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

class RadioField extends ListField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'radio';
    }

    public function renderField($record)
    {
        return $this->render('@bazar/fields/radio.twig', [
            'value' => $record !== '' ? $record[$this->recordId] : ''
        ]);
    }

    public function renderInput($record)
    {
        return $this->render('@bazar/inputs/radio.twig', [
            'options' => $this->values['label'],
            'value' => $record !== '' ? $record[$this->recordId] : $this->default
        ]);
    }
}
