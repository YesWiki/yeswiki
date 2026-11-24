<?php

namespace YesWiki\Content\Field;

/** A field that can keep, for each entry, a group of the people behind the entries it holds. */
interface KeepsAMembersGroup
{
    /** The name of the group this field keeps for an entry, or null when it keeps none. */
    public function membersGroupOf(string $tag): ?string;

    /** Whether the entry's author fills the group while no entry is chosen. */
    public function membersGroupFallsBackToOwner(): bool;

    /** @return string|null */
    public function getPropertyName();
}
