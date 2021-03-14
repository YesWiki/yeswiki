<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"champs_cache"})
 */
class HiddenField extends BazarField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->type = 'hidden';
    }

    protected function renderStatic($entry)
    {
        return null;
    }

    // Format input values before save
    public function formatValuesBeforeSave($entry)
    {
        return [];
    }
    
    public function jsonSerialize()
    {
        return [
            'type' => $this->getType(),
            'value' => $this->getLabel(),
            ];
    }
}
