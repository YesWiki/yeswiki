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

    public function formatInput()
    {
        return [$this->entryId => $this->value !== 'https://' ? $this->value : null ];
    }
}
