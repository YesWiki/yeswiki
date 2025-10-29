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

    // use wiki syntax instead of plain html
    protected $useWikiSyntax;

    protected const FIELD_FORM_TEXT = 1;
    protected const FIELD_VIEW_TEXT = 3;
    protected const FIELD_USE_WIKI_SYNTAX = 4;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->name = null;
        $this->label = null;
        $this->propertyName = null;
        $this->formText = $values[self::FIELD_FORM_TEXT];
        $this->viewText = $values[self::FIELD_VIEW_TEXT];
        $this->useWikiSyntax = (
            $values[self::FIELD_USE_WIKI_SYNTAX] === false
            || empty($values[self::FIELD_USE_WIKI_SYNTAX])
            || in_array($values[self::FIELD_USE_WIKI_SYNTAX], [0, '0', 'no', 'non', 'false'])
        ) ? false : true;
    }

    protected function getValue($entry)
    {
        // no value for labelhtml
        return null;
    }

    protected function renderInput($entry)
    {
        if ($this->useWikiSyntax) {
            $content = str_replace('<br/>', "\n", $this->formText);
            return $this->getWiki()->Format($content);
        }
        return $this->formText;
    }

    protected function renderStatic($entry)
    {
        if ($this->useWikiSyntax) {
            $content = str_replace('<br/>', "\n", $this->viewText);
            return $this->getWiki()->Format($content);
        }
        return $this->viewText;
    }

    // Format input values before save
    public function formatValuesBeforeSave($entry)
    {
        return [];
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'type' => $this->getType(),
            'viewtext' => $this->viewText,
            'formtext' => $this->formText,
            'useWikiSyntax' => $this->useWikiSyntax,
        ];
    }
}
