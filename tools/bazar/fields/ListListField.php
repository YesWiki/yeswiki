<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * List with a list as a source
 */
abstract class ListListField extends ListField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        // TODO remove strange fields
        $this->options = baz_valeurs_liste($this->name);
        $this->options['id'] = $this->name;
    }
}
