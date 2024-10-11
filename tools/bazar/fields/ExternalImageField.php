<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"externalimagefield"})
 */
class ExternalImageField extends ImageField
{
    protected $JSONFormAddress;

    public const FIELD_JSON_FORM_ADDR = 13; // replace nothing

    public function __construct(array $values, ContainerInterface $services)
    {
        $values[self::FIELD_TYPE] = 'image';
        $this->JSONFormAddress = $values[self::FIELD_JSON_FORM_ADDR];
        $values[self::FIELD_JSON_FORM_ADDR] = '';

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
        // inspired from parent but with different href
        $value = $this->getValue($entry);

        if (isset($value) && $value != '') {
            return $this->render('@bazar/fields/external-image.twig', [
                'attachClass' => $this->getAttach(),
                'baseUrl' => $entry['external-data']['baseUrl'],
                'imageFullPath' => $this->getBasePath() . $value,
                'fieldName' => $this->name,
                'thumbnailHeight' => $this->thumbnailHeight,
                'thumbnailWidth' => $this->thumbnailHeight,
                'imageHeight' => $this->imageHeight,
                'imageWidth' => $this->imageWidth,
                'class' => $this->imageClass,
            ]);
        }

        return '';
    }
}
