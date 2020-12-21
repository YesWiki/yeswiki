<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"texte"})
 */
class TextField extends BazarField
{
    protected $pattern;
    protected $subType;

    protected const FIELD_PATTERN = 6;
    protected const FIELD_SUB_TYPE = 7;
    
    protected const ALLOWED_SUB_TYPES = ['text', 'date', 'email', 'url', 'range', 'password', 'number', 'color'];

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->pattern = $values[self::FIELD_PATTERN];
        $this->subType = $values[self::FIELD_SUB_TYPE];

        if (!empty($this->subType) && in_array($this->subType, self::ALLOWED_SUB_TYPES)) {
            $this->type = $this->subType;
        } else {
            $this->type = 'text';
        }

        $this->maxChars = $this->maxChars ?? 255;
    }

    protected function renderInput($entry)
    {
        // Handling all subtypes (url, number) in the text.twig
        return $this->render("@bazar/inputs/text.twig", [
            'value' => $this->getValue($entry)
        ]);
    }  

    public function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if( !$value ) return null;

        if( $this->name === 'bf_titre') {
            return $this->render("@bazar/fields/title.twig", [
                'value' => $value
            ]);
        } else {
            return $this->render("@bazar/fields/text.twig", [
                'value' => $value
            ]);
        }
    }
}
