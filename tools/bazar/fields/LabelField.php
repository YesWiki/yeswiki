<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"labelhtml"})
 */
class LabelField extends BazarField
{
    // Text to display on the edit/create pages
    protected $formText;

    // Text to display on the view page
    protected $viewText;

    protected const FIELD_FORM_TEXT = 1;
    protected const FIELD_VIEW_TEXT = 3;
    private const FIELD_CLASS_TYPE = 'LabelField';

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->name = null;
        $this->label = null;
        $this->propertyName = null;
        $this->formText = $values[self::FIELD_FORM_TEXT];
        $this->viewText = $values[self::FIELD_VIEW_TEXT];
    }

    protected function getValue($entry)
    {
        // no value for labelhtml
        return null;
    }

    protected function renderInput($entry)
    {
        return $this->formText;
    }

    protected function renderStatic($entry)
    {
        return $this->viewText;
    }

    // Format input values before save
    public function formatValuesBeforeSave($entry)
    {
        return [];
    }

    public static function mapToFieldArray($fieldProps): array
    {
        $new = parent::mapToFieldArray($fieldProps);
        $new[self::FIELD_VIEW_TEXT] = $fieldProps['viewText'] ?? '';
        $new[self::FIELD_FORM_TEXT] = $fieldProps['formText'] ?? '';
        ksort($new);
        return $new;
    }


    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'field_type' => self::FIELD_CLASS_TYPE,
            'type' => $this->getType(),
            'viewText' => $this->viewText,
            'formText' => $this->formText,
        ];
    }
}
