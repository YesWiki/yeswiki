<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;

#[\Field(['liste'])]
class SelectListField extends EnumField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->loadOptionsFromList();
    }

    protected function renderInput($entry)
    {
        return $this->render('@core/inputs/select.twig', [
            'value' => $this->getValue($entry),
            'options' => $this->options,
        ]);
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if (!$value) {
            return '';
        }

        return $this->render('@core/fields/select.twig', [
            'value' => $this->options[$value] ?? '',
        ]);
    }
}
