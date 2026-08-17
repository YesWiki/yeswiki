<?php

namespace YesWiki\Content\Field;

/** The field types that put nothing into the search index (ticket 18 / ADR-0015). */
trait ContributesNoSearchableText
{
    public function searchableText($entry): string
    {
        return '';
    }
}
