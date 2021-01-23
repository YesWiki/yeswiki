<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"metadatas"})
 */
class MetadataField extends BazarField
{
    protected $theme;
    protected $template;
    protected $style;
    protected $bgImage;

    protected const FIELD_THEME = 1;
    protected const FIELD_TEMPLATE = 2;
    protected const FIELD_STYLE = 3;
    protected const FIELD_BG_IMAGE = 4;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->theme = empty($values[self::FIELD_THEME]) ? THEME_PAR_DEFAUT : $values[self::FIELD_THEME];
        $this->template = empty($values[self::FIELD_TEMPLATE]) ? SQUELETTE_PAR_DEFAUT : $values[self::FIELD_TEMPLATE];
        $this->style = empty($values[self::FIELD_STYLE]) ? CSS_PAR_DEFAUT : $values[self::FIELD_STYLE];
        $this->bgImage = $values[self::FIELD_BG_IMAGE];
    }

    protected function renderInput($entry)
    {
        return null;
    }

    public function formatValuesBeforeSave($entry)
    {
        $GLOBALS['wiki']->SaveMetaDatas($entry['id_fiche'], [
            'theme' => $this->theme,
            'style' => $this->style,
            'squelette' => $this->template,
            'bgimg' => $this->bgImage
        ]);

        return [];
    }

    public function renderStatic($entry)
    {
        return null;
    }
}
