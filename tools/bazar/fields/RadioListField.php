<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"radio"})
 */
class RadioListField extends EnumField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'radio';

        $this->loadOptionsFromList();
    }

    protected function renderInput($entry)
    {
        return $this->render('@bazar/inputs/radio.twig', [
            'options' => $this->options,
            'value' => $this->getValue($entry)
        ]);
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry) ;
        if( !$value ) return null;
        return $this->render('@bazar/fields/radio.twig', [
            'value' => $this->options[$value]
        ]);
    }
}
