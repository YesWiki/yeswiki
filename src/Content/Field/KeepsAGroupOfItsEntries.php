<?php

namespace YesWiki\Content\Field;

/** The linked-entry fields that can keep a group of the people behind the entries they hold: prefix plus the entry's tag. */
trait KeepsAGroupOfItsEntries
{
    protected string $membersGroup = '';

    protected bool $membersGroupOwner = false;

    /** @param array<int|string, mixed> $values */
    protected function readMembersGroup(array $values): void
    {
        $prefix = trim((string)($values[self::FIELD_MEMBERS_GROUP] ?? ''));
        $this->membersGroup = preg_match('/^[A-Za-z0-9]+$/', $prefix) === 1 ? $prefix : '';
        $this->membersGroupOwner = in_array($values[self::FIELD_MEMBERS_GROUP_OWNER] ?? '', [true, 1, '1', 'true', 'yes'], true);
    }

    /** The name of the group this field keeps for an entry, or null when it keeps none. */
    public function membersGroupOf(string $tag): ?string
    {
        return $this->membersGroup === '' || $tag === '' ? null : $this->membersGroup . $tag;
    }

    /** Whether the entry's author fills the group while no entry is chosen. */
    public function membersGroupFallsBackToOwner(): bool
    {
        return $this->membersGroupOwner;
    }
}
