<?php

namespace YesWiki\Bazar\Field;

abstract class ListField extends BazarField
{
    public function __construct(array $values)
    {
        parent::__construct($values);

        $this->recordId = $values[self::FIELD_TYPE] . $values[self::FIELD_ID] . $values[6];

        $this->values = baz_valeurs_liste($values[self::FIELD_ID]);
        $this->values['id'] = $values[self::FIELD_ID];
    }
}
