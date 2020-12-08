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
            'options' => $this->options['label']
        ]);
    }

    public function renderStatic($entry)
    {
        $value = $this->options['label'][$this->getValue($entry)];
        return $this->render('@bazar/fields/select_entry.twig', [
            'value' => $value
        ]);
    }
}
