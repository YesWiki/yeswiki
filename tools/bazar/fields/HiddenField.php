<?php

namespace YesWiki\Bazar\Field;
use Psr\Container\ContainerInterface;

class HiddenField extends BazarField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->type = 'hidden';
    }
    
    public function renderStatic($entry)
    {
        return null;
    }
}
