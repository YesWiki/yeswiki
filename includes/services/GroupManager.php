<?php

namespace YesWiki\Core\Service;

class GroupManager
{
    protected $tripleStore;
    protected $userManager;

    public function __construct(
        TripleStore $tripleStore,
        UserManager $userManager
    ) {
        $this->tripleStore = $tripleStore;
        $this->userManager = $userManager;
    }

    /**
     * Check if group already exists or name used by user.
     */
    public function groupExists(string $group_name): bool
    {
        return $this->tripleStore->getMatching(GROUP_PREFIX . $group_name, null, null, '=') != null || $this->userManager->userExist($group_name);
    }

    /**
     * create group with members.
     */
    public function create(string $group_name, array $members): int
    {
        $member_str = implode("\n", $this->cleanMembers($members));

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
     * get direct members of group. Do not list member of child groups.
     *
     * @param string group_name
     *
     * @return string[]
     */
    public function getMembers(string $group_name): array
    {
        $members = $this->tripleStore->getOne($group_name, WIKINI_VOC_ACLS, GROUP_PREFIX) ?? '';

        return $this->cleanMembers(preg_split('/[\r\n]+/', $members));
    }

    /**
     * trim members and drop blank or duplicated ones.
     *
     * @param string[] $members
     *
     * @return string[]
     */
    public function cleanMembers(array $members): array
    {
        $members = array_map('trim', $members);
        $members = array_filter($members, fn ($member): bool => $member !== '');

        return array_values(array_unique($members));
    }

    public function addMembers(string $group_name, array $members): void
    {
        $old_members = $this->getMembers($group_name);
        $new_members = implode("\n", $this->cleanMembers(array_merge($old_members, $members)));
        if ($this->tripleStore->delete($group_name, WIKINI_VOC_ACLS, null, GROUP_PREFIX)) {
            $this->tripleStore->create($group_name, WIKINI_VOC_ACLS, $new_members, GROUP_PREFIX);
        } else {
            $this->tripleStore->update($group_name, WIKINI_VOC_ACLS, $old_members, $new_members, GROUP_PREFIX);
        }
    }

    public function removeMembers(string $group_name, array $members): void
    {
        $old_members = $this->getMembers($group_name);
        $new_members = implode("\n", $this->cleanMembers(array_diff($old_members, $members)));
        if ($this->tripleStore->delete($group_name, WIKINI_VOC_ACLS, null, GROUP_PREFIX)) {
            $this->tripleStore->create($group_name, WIKINI_VOC_ACLS, $new_members, GROUP_PREFIX);
        } else {
            $this->tripleStore->update($group_name, WIKINI_VOC_ACLS, $old_members, $new_members, GROUP_PREFIX);
        }
    }

    public function updateMembers(string $group_name, array $members): void
    {
        $new_members = implode("\n", $this->cleanMembers($members));
        if ($this->tripleStore->delete($group_name, WIKINI_VOC_ACLS, null, GROUP_PREFIX)) {
            $this->tripleStore->create($group_name, WIKINI_VOC_ACLS, $new_members, GROUP_PREFIX);
        } else {
            $this->tripleStore->update($group_name, WIKINI_VOC_ACLS, null, $new_members, GROUP_PREFIX);
        }
    }
}
