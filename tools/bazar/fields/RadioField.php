<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

class RadioField extends ListListField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'radio';
    }

    public function renderField($entry)
    {
        return $this->render('@bazar/fields/radio.twig', [
            'value' => $entry !== '' ? $this->options['label'][$entry[$this->entryId]] : ''
        ]);
    }

    public function renderInput($entry)
    {
        if( $this->isInputHidden($entry) ) return '';

        return $this->render('@bazar/inputs/radio.twig', [
            'options' => $this->options['label'],
            'value' => $entry !== '' ? $entry[$this->entryId] : $this->default
        ]);
    }
}
