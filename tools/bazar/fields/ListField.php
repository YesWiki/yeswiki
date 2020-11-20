<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

abstract class ListField extends BazarField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->recordId = $values[self::FIELD_TYPE] . $values[self::FIELD_ID] . $values[self::FIELD_LIST_LABEL];

        // TODO call this options, and remove strange fields
        $this->values = baz_valeurs_liste($values[self::FIELD_ID]);
        $this->values['id'] = $values[self::FIELD_ID];
    }
}
