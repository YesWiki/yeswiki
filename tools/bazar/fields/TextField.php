<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

class TextField extends BazarField
{
    public const ALLOWED_SUB_TYPES = ['text', 'date', 'email', 'url', 'range', 'password', 'number'];

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        if (!empty($values[self::FIELD_SUB_TYPE]) && in_array($values[self::FIELD_SUB_TYPE], self::ALLOWED_SUB_TYPES)) {
            $this->type = $values[self::FIELD_SUB_TYPE];
        } else {
            $this->type = 'text';
        }

        $this->maxChars = $this->maxChars || 255;

        // TODO put this directly in the template
//        $this->attributes = ' maxlength="'.$values[self::FIELD_MAX_CHARS].'" size="'.$values[self::FIELD_MAX_LENGTH].'"';
//        $this->attributes .= ($values[self::FIELD_PATTERN] != '') ? ' pattern="' . $values[self::FIELD_PATTERN] . '"' : '';
    }

    public function renderField($entry)
    {
        return $this->render('@bazar/fields/text.twig', [
            'value' => $entry !== '' ? $entry[$this->recordId] : ''
        ]);
    }

    public function renderInput($entry)
    {
        if( $this->isInputHidden($entry) ) return '';

        return $this->render('@bazar/inputs/text.twig', [
            'value' => $entry !== '' ? $entry[$this->recordId] : $this->default
        ]);
    }
}
