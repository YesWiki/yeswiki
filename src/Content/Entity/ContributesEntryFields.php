<?php

namespace YesWiki\Content\Entity;

/** Supplies extra values about an entry that `Content` does not itself hold. */
interface ContributesEntryFields
{
    /**
     * The names this contributor answers, e.g.
     *
     * @return list<string>
     */
    public function contributedFieldNames(): array;

    /**
     * @param string $name one of `contributedFieldNames()`
     *
     * @return mixed whatever that field is; null if this contributor has nothing for it
     */
    public function contributedField(string $name, string $entryId): mixed;
}
