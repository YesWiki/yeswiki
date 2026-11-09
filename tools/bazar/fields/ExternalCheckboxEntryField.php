<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Service\ExternalBazarService;

/**
 * @Field({"externalcheckboxentryfield"})
 */
class ExternalCheckboxEntryField extends CheckboxEntryField
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
        return '';
    }

    protected function renderStatic($entry)
    {
        // copy from parent but with different href
        $keys = $this->getValues($entry);
        $values = [];
        foreach ($keys as $key) {
            if (in_array($key, array_keys($this->getOptions()))) {
                $values[$key]['value'] = $this->options[$key];
                $values[$key]['href'] = $entry['external-data']['baseUrl'] . '?' . $key . '/iframe';
            }
        }

        return (count($values) > 0) ? $this->render('@bazar/fields/externalcheckboxentry.twig', [
            'values' => $values,
        ]) : '';
    }

    protected function getFormName()
    {
        return '';
    }

    public function getOptions()
    {
        // load options only when needed but not at construct to prevent infinite loops
        if (is_null($this->options)) {
            $this->loadOptionsFromJSONForm($this->JSONFormAddress);
        }

        return $this->options;
    }
}
