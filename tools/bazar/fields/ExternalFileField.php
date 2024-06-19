<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Service\ExternalBazarService;

/**
 * @Field({"externalfilefield"})
 */
class ExternalFileField extends FileField
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

    protected function renderStatic($entry)
    {
        // copy from parent but with different href
        $value = $this->getValue($entry);

        if (isset($value) && $value != '') {
            return $this->render('@bazar/fields/file.twig', [
                'value' => $value,
                'fileUrl' => $entry['external-data']['baseUrl'] . BAZ_CHEMIN_UPLOAD . $value,
            ]);
        }

        return '';
    }
}
