<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

class LinkField extends BazarField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'link';
        $this->maxChars = $this->maxChars ?? 255;
        $this->default = $this->default ?? 'https://';
    }

    public function formatInput($entry)
    {
        return array_key_exists($this->recordId, $entry) && $entry[$this->recordId] !== 'https://' ?
            [$this->recordId => $entry[$this->recordId]] : [$this->recordId => null];
    }

    public function renderField($entry)
    {
        return $this->render('@bazar/fields/link.twig', [
            'value' => $entry !== '' ? $entry[$this->recordId] : null
        ]);
    }

    public function renderInput($entry)
    {
        if( $this->isInputHidden($entry) ) return '';

        return $this->render('@bazar/inputs/link.twig', [
            'value' => $entry !== '' ? $entry[$this->recordId] : null
        ]);
    }
}
