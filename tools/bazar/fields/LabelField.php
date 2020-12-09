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

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->formText = $values[self::FIELD_FORM_TEXT];
        $this->viewText = $values[self::FIELD_VIEW_TEXT];
    }

    public function renderInput($entry)
    {
        return $this->formText . "\n";
    }

    public function renderStatic($entry)
    {
        return empty($this->viewText) ? $this->viewText . "\n" : null ;
    }
}
