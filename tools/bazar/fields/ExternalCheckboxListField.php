<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Service\ExternalBazarService;

/**
 * @Field({"externalcheckboxlistfield"})
 */
class ExternalCheckboxListField extends CheckboxListField
{
    protected $JSONFormAddress;

    private const FIELD_CLASS_TYPE = 'ExternalCheckboxListField';

    public function __construct(array $values, ContainerInterface $services)
    {
        $values[self::FIELD_TYPE] = $values[ExternalBazarService::FIELD_ORIGINAL_TYPE];
        $values[ExternalBazarService::FIELD_ORIGINAL_TYPE] = '';
        $this->JSONFormAddress = $values[ExternalBazarService::FIELD_JSON_FORM_ADDR];
        $values[ExternalBazarService::FIELD_JSON_FORM_ADDR] = '';

        parent::__construct($values, $services);
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

    protected function renderInput($entry)
    {
        return '';
    }

    public function formatValuesBeforeSave($entry)
    {
        return null;
    }

    public function getOptions()
    {
        // load options only when needed but not at construct to prevent infinite loops
        if (is_null($this->options)) {
            $this->loadOptionsFromJSONForm($this->JSONFormAddress);
        }

        return $this->options;
    }

    public function loadOptionsFromList()
    {
        $this->options = null;
        $this->getOptions();
    }
}
