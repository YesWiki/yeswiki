<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Service\ExternalBazarService;

/**
 * @Field({"externalselectentryfield"})
 */
class ExternalSelectEntryField extends SelectEntryField
{
    protected $JSONFormAddress;

    public function __construct(array $values, ContainerInterface $services)
    {
        $values[self::FIELD_TYPE] = $values[ExternalBazarService::FIELD_ORIGINAL_TYPE];
        $values[ExternalBazarService::FIELD_ORIGINAL_TYPE] = '';
        $this->JSONFormAddress = $values[ExternalBazarService::FIELD_JSON_FORM_ADDR];
        $values[ExternalBazarService::FIELD_JSON_FORM_ADDR] = '';

        parent::__construct($values, $services);
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
            if ($this->isDistantJson) {
                $this->loadOptionsFromJson();
            } else {
                $this->loadOptionsFromJSONForm($this->JSONFormAddress);
            }
        }

        return $this->options;
    }
}
