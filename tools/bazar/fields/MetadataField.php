<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Core\Service\PageManager;

/**
 * @Field({"metadatas"})
 */
class MetadataField extends BazarField
{
    protected $theme;
    protected $template;
    protected $style;
    protected $bgImage;
    protected $favorite_preset;

    protected const FIELD_THEME = 1;
    protected const FIELD_TEMPLATE = 2;
    protected const FIELD_STYLE = 3;
    protected const FIELD_BG_IMAGE = 4;
    protected const FIELD_CSS_PRESET = 5;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->theme = empty($values[self::FIELD_THEME]) ? THEME_PAR_DEFAUT : $values[self::FIELD_THEME];
        $this->template = empty($values[self::FIELD_TEMPLATE]) ? SQUELETTE_PAR_DEFAUT : $values[self::FIELD_TEMPLATE];
        $this->style = empty($values[self::FIELD_STYLE]) ? CSS_PAR_DEFAUT : $values[self::FIELD_STYLE];
        $this->bgImage = $values[self::FIELD_BG_IMAGE];
        $this->favorite_preset = empty($values[self::FIELD_CSS_PRESET]) ? null : $values[self::FIELD_CSS_PRESET];
        $this->name = null;
        $this->label = null;
        $this->propertyName = null;
        $this->size = null;
        $this->maxChars = null;
        $this->default = null;
    }

    protected function renderInput($entry)
    {
        return '';
    }

    public function formatValuesBeforeSave($entry)
    {
        $this->getService(PageManager::class)->setMetadata($entry['id_fiche'], [
            'theme' => $this->theme,
            'style' => $this->style,
            'squelette' => $this->template,
            'bgimg' => $this->bgImage,
        ] + (
            !empty($this->favorite_preset)
            ? ['favorite_preset' => $this->favorite_preset]
            : []
        ));

        return [];
    }

    protected function renderStatic($entry)
    {
        return '';
    }

    public function getTheme()
    {
        return $this->theme;
    }

    public function getStyle()
    {
        return $this->style;
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function getBgImage()
    {
        return $this->bgImage;
    }

    public function getFavoritePreset()
    {
        return $this->favorite_preset;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'type' => $this->getType(),
            'theme' => $this->getTheme(),
            'style' => $this->getStyle(),
            'template' => $this->getTemplate(),
            'bgImage' => $this->getBgImage(),
            'favorite_preset' => $this->getFavoritePreset(),
        ];
    }
}
