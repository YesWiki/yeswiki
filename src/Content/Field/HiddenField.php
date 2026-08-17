<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;

#[\Field(['champs_cache'])]
class HiddenField extends BazarField
{
    use ContributesNoSearchableText;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->type = 'hidden';
        $this->label = $this->getPropertyName();
    }

    protected function renderStatic($entry)
    {
        return '';
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'type' => $this->getType(),
            'default' => $this->getDefault(),
        ];
    }
}
