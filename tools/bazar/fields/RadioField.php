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

    public function renderField($entry)
    {
        return $this->render('@bazar/fields/radio.twig', [
            'value' => $entry !== '' ? $this->values['label'][$entry[$this->recordId]] : ''
        ]);
    }

    public function renderInput($entry)
    {
        if( $this->isInputHidden($entry) ) return '';

        return $this->render('@bazar/inputs/radio.twig', [
            'options' => $this->values['label'],
            'value' => $entry !== '' ? $entry[$this->recordId] : $this->default
        ]);
    }
}
