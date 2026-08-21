<?php

namespace YesWiki\Identity\Service;

use YesWiki\Kernel\Service\TripleStore;

class GroupManager
{
    protected TripleStore $tripleStore;
    protected UserManager $userManager;

    public function __construct(
        TripleStore $tripleStore,
        UserManager $userManager
    ) {
        $this->tripleStore = $tripleStore;
        $this->userManager = $userManager;
    }

    /** Check if group already exists or name used by user. */
    public function groupExists(string $group_name): bool
    {
        return $this->tripleStore->hasAnyProperty($group_name, GROUP_PREFIX) || $this->userManager->userExist($group_name);
    }

    /**
     * create group with members.
     *
     * @param string[] $members users and/or groups the group is made of
     */
    public function create(string $group_name, array $members): int
    {
        $member_str = implode("\n", $members);

        return $this->tripleStore->create($group_name, WIKINI_VOC_ACLS, $member_str, GROUP_PREFIX);
    }

    public function delete(string $group_name): void
    {
        $group_list = $this->tripleStore->getMatching(GROUP_PREFIX . '%', null, '%@' . $group_name . '%', 'LIKE', '=', 'LIKE');
        $this->tripleStore->delete($group_name, WIKINI_VOC_ACLS, null, GROUP_PREFIX);
        $prefix_len = strlen(GROUP_PREFIX);
        foreach ($group_list as $group) {
            $group = substr($group['resource'], $prefix_len);
            $this->removeMembers($group, ['@' . $group_name]);
        }
    }

    /**
     * get list of all groups.
     *
     * @return string[]
     */
    public function getall(): array
    {
        $group_list = $this->tripleStore->getMatching(GROUP_PREFIX . '%');
        $prefix_len = strlen(GROUP_PREFIX);

        return array_map(fn ($value): string => substr($value['resource'], $prefix_len), $group_list);
    }

    /**
     * get direct members of group.
     *
     * @return string[]
     */
    public function getMembers(string $group_name): array
    {
        $members = $this->tripleStore->getOne($group_name, WIKINI_VOC_ACLS, GROUP_PREFIX) ?? '';

        return explode("\n", $members);
    }

    /** @param string[] $members users and/or groups to add */
    public function addMembers(string $group_name, array $members): void
    {
        $old_members = $this->getMembers($group_name);
        $stored_members = implode("\n", $old_members);
        $new_members = array_merge($old_members, $members);
        $new_members = array_unique($new_members);
        $new_members = array_filter($new_members);
        $new_members = implode("\n", $new_members);
        if ($this->tripleStore->delete($group_name, WIKINI_VOC_ACLS, null, GROUP_PREFIX)) {
            $this->tripleStore->create($group_name, WIKINI_VOC_ACLS, $new_members, GROUP_PREFIX);
        } else {
            $this->tripleStore->update($group_name, WIKINI_VOC_ACLS, $stored_members, $new_members, GROUP_PREFIX);
        }
    }

    /** @param string[] $members users and/or groups to remove */
    public function removeMembers(string $group_name, array $members): void
    {
        $old_members = $this->getMembers($group_name);
        $stored_members = implode("\n", $old_members);
        $new_members = array_diff($old_members, $members);
        $new_members = array_filter($new_members);
        $new_members = implode("\n", $new_members);
        if ($this->tripleStore->delete($group_name, WIKINI_VOC_ACLS, null, GROUP_PREFIX)) {
            $this->tripleStore->create($group_name, WIKINI_VOC_ACLS, $new_members, GROUP_PREFIX);
        } else {
            $this->tripleStore->update($group_name, WIKINI_VOC_ACLS, $stored_members, $new_members, GROUP_PREFIX);
        }
    }

    /** @param string[] $members the new member list, replacing the current one */
    public function updateMembers(string $group_name, array $members): void
    {
        $stored_members = implode("\n", $this->getMembers($group_name));
        $new_members = implode("\n", $members);
        if ($this->tripleStore->delete($group_name, WIKINI_VOC_ACLS, null, GROUP_PREFIX)) {
            $this->tripleStore->create($group_name, WIKINI_VOC_ACLS, $new_members, GROUP_PREFIX);
        } else {
            $this->tripleStore->update($group_name, WIKINI_VOC_ACLS, $stored_members, $new_members, GROUP_PREFIX);
        }
    }
}
