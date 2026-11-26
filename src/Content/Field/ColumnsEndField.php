<?php

namespace YesWiki\Content\Field;

use YesWiki\Content\Attribute\Field;

/** Closes a `columns` block. */
#[Field(['columnsend'])]
class ColumnsEndField extends ColumnsMarkerField
{
    public function markup(): string
    {
        return ColumnsBalancer::CLOSE;
    }
}
