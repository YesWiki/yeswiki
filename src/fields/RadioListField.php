<?php

namespace YesWiki\Core\Field;

use Psr\Container\ContainerInterface;

#[\Field(['radio'])]
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

        return $this->render('@core/fields/radio.twig', [
            'value' => $this->options[$value],
        ]);
    }
}
