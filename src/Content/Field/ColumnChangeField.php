<?php

namespace YesWiki\Content\Field;

use YesWiki\Content\Attribute\Field;

/** Moves to the next column of a `columns` block. */
#[Field(['columnchange'])]
class ColumnChangeField extends ColumnsMarkerField
{
    public function markup(): string
    {
        return ColumnsBalancer::BREAK;
    }
}
