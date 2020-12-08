<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"liste"})
 */
class SelectListField extends EnumField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->loadOptionsFromList();
    }

    public function renderInput($entry)
    {
        return $this->render('@bazar/inputs/select.twig', [
            'value' => $this->getValue($entry),
            'options' => $this->options
        ]);
    }

    public function renderStatic($entry)
    {
        return $this->render('@bazar/fields/select.twig', [
            'value' => $this->options[$this->getValue($entry)]
        ]);
    }
}
