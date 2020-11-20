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
        return array_key_exists($this->entryId, $entry) && $entry[$this->entryId] !== 'https://' ?
            [$this->entryId => $entry[$this->entryId]] : [$this->entryId => null];
    }

    public function renderField($entry)
    {
        return $this->render('@bazar/fields/link.twig', [
            'value' => $entry !== '' ? $entry[$this->entryId] : null
        ]);
    }

    public function renderInput($entry)
    {
        if( $this->isInputHidden($entry) ) return '';

        return $this->render('@bazar/inputs/link.twig', [
            'value' => $entry !== '' ? $entry[$this->entryId] : null
        ]);
    }
}
