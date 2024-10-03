<?php

namespace YesWiki\Core\Service;

use YesWiki\Core\Service\TripleStore;
use Throwable;

class GroupManager
{
    protected $tripleStore;


    public function __construct(
        TripleStore $tripleStore
    ) {
        $this->tripleStore = $tripleStore;
    }

    /**
     * Check if group already exists
     * @param string $group_name
     * @return bool
     */
    public function groupExists(string $group_name): bool
    {
        return $this->tripleStore->getMatching(GROUP_PREFIX . $group_name, WIKINI_VOC_ACLS_URI, null, '=') != null;
    }

    /**
     * create group with members
     * @param string $group_name
     * @param array $members
     * @return void
     */
    public function create(string $group_name, array $members): void
    {
        $member_str =  implode("\n", $members);
        $this->tripleStore->create($group_name, WIKINI_VOC_ACLS, $member_str, GROUP_PREFIX);
    }

    public function delete(string $group_name): void
    {
        $this->tripleStore->delete($group_name, WIKINI_VOC_ACLS, null, GROUP_PREFIX);
    }


    /**
     * get list of all groups
     * @return string[]
     *
     */
    public function getall(): array
    {
        $group_list = $this->tripleStore->getMatching(GROUP_PREFIX . '%', WIKINI_VOC_ACLS_URI);
        $prefix_len = strlen(GROUP_PREFIX);
        return array_map(fn ($value): string => substr($value['resource'], $prefix_len), $group_list);
    }

    /**
     * get direct members of group. Do not list member of child groups.
     * @param string group_name
     * @return string[]
     */
    public function getMembers(string $group_name): array
    {
        $members = $this->tripleStore->getOne($group_name, WIKINI_VOC_ACLS, GROUP_PREFIX);
        return explode("\n", $members);
    }

    /**
     * @param string $group_name
     * @param array $members
     * @return void
     */
    public function addMembers(string $group_name, array $members): void
    {
        $old_members = $this->getMembers($group_name);
        $new_members = array_merge($old_members, $members);
        $new_members = array_unique($$new_members);
        $new_members = implode("\n", $new_members);
        if($this->tripleStore->delete($group_name, WIKINI_VOC_ACLS, $old_members, GROUP_PREFIX)) {
            $this->tripleStore->create($group_name, WIKINI_VOC_ACLS, $new_members, GROUP_PREFIX);
        } else {
            $this->tripleStore->update($group_name, WIKINI_VOC_ACLS, $old_members, $new_members, GROUP_PREFIX);
        }
    }


    /**
     * @param string $group_name
     * @param array $members
     * @return void
     */
    public function removeMembers(string $group_name, array $members): void
    {
        $old_members = $this->getMembers($group_name);
        $new_members = array_diff($$old_members, $members);
        $new_members = implode("\n", $new_members);
        if($this->tripleStore->delete($group_name, WIKINI_VOC_ACLS, $old_members, GROUP_PREFIX)) {
            $this->tripleStore->create($group_name, WIKINI_VOC_ACLS, $new_members, GROUP_PREFIX);
        } else {
            $this->tripleStore->update($group_name, WIKINI_VOC_ACLS, $old_members, $new_members, GROUP_PREFIX);
        }
    }

    /**
     * @param string $group_name
     * @param array $members
     * @return void
     */
    public function updateMembers(string $group_name, array $members): void
    {
        if($this->tripleStore->delete($group_name, WIKINI_VOC_ACLS, null, GROUP_PREFIX)) {
            $this->tripleStore->create($group_name, WIKINI_VOC_ACLS, $members, GROUP_PREFIX);
        } else {
            $this->tripleStore->update($group_name, WIKINI_VOC_ACLS, null, $members, GROUP_PREFIX);
        }
    }

}
