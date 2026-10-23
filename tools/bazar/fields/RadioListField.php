<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"radio"})
 */
class RadioListField extends RadioField
{

    private const FIELD_CLASS_TYPE = 'RadioListField';

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->loadOptionsFromList();
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
            return array_merge(
                parent::jsonSerialize(),
                [
                    'field_type' => self::FIELD_CLASS_TYPE,
                ]
            );
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
