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

    public function renderField()
    {
        $this->value = $this->entry !== '' ? $this->options['label'][$this->value] : '';
        return $this->render('@bazar/fields/radio.twig');
    }

    public function renderInput()
    {
        return $this->render('@bazar/inputs/radio.twig', [
            'options' => $this->options['label']
        ]);
    }
}
