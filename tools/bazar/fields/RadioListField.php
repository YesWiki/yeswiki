<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"radio"})
 */
class RadioListField extends RadioField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->loadOptionsFromList();
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if (!$value) {
            return '';
        }

        return $this->render('@bazar/fields/radio.twig', [
            'value' => $this->options[$value],
        ]);
    }
}
