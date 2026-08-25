<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Attribute\Field;

#[Field(['radio'])]
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
            'value' => $this->getOptions()[$value] ?? '',
        ]);
    }
}
