<?php

namespace YesWiki\Core\Service;

use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use Throwable;

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
     * Check if group already exists or name used by user
     * @param string $group_name
     * @return bool
     */
    public function groupExists(string $group_name): bool
    {
        
        return $this->tripleStore->getMatching(GROUP_PREFIX . $group_name, WIKINI_VOC_ACLS_URI, null, '=') != null | $this->userManager->userExist($group_name);
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
        $group_list = $this->tripleStore->getMatching(GROUP_PREFIX . '%', WIKINI_VOC_ACLS_URI, '%@'.$group_name.'%', 'LIKE', '=', 'LIKE');
        $this->tripleStore->delete($group_name, WIKINI_VOC_ACLS, null, GROUP_PREFIX);
        $prefix_len = strlen(GROUP_PREFIX);
        $list = array();
        foreach($group_list as $group) {
            $group = substr($group['resource'], $prefix_len);
            $this->removeMembers($group, ['@'.$group_name]);
        }
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
        $new_members = array_unique($new_members);
        $new_members = array_filter($new_members);
        $new_members = implode("\n", $new_members);
        if($this->tripleStore->delete($group_name, WIKINI_VOC_ACLS, null, GROUP_PREFIX)) {
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
        $new_members = array_diff($old_members, $members);
        $new_members = array_filter($new_members);
        $new_members = implode("\n", $new_members);
        if($this->tripleStore->delete($group_name, WIKINI_VOC_ACLS, null, GROUP_PREFIX)) {
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
        $new_members = implode("\n", $members);
        if($this->tripleStore->delete($group_name, WIKINI_VOC_ACLS, null, GROUP_PREFIX)) {
            $this->tripleStore->create($group_name, WIKINI_VOC_ACLS, $new_members, GROUP_PREFIX);
        } else {
            $this->tripleStore->update($group_name, WIKINI_VOC_ACLS, null, $new_members, GROUP_PREFIX);
        }
    }

}
