<?php

namespace YesWiki\Content\Field;

/** The field types whose stored value is prose a translator retypes, rather than a key, a date or a file name. */
trait TranslatesItsValue
{
    public function translatesValue(): bool
    {
        return true;
    }
}
