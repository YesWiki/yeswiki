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
       return $this->tripleStore->getOne($group_name, WIKINI_VOC_ACLS, GROUP_PREFIX) == null;
    }
    
    /**
     * create group with members
     * @param string $group_name
     * @param array $members 
     * @return void
     */
    public function create(string $group_name, array $members): void {
        $member_str =  implode("\n", $members);
        $this->tripleStore->create($group_name, WIKINI_VOC_ACLS, $member_str, GROUP_PREFIX);
    }

    public function getMembers(string $group_name) :array {
            $members = $this->tripleStore->getOne($group_name, WIKINI_VOC_ACLS, GROUP_PREFIX);
            return explode("\n", $members);
    }
    
    /**
     * @param string $group_name
     * @param string $members
     * 
     */
    public function add(string $group_name, array $members):void {
        $old_members = $this->getMembers($group_name);
        if (!in_array($group_name, $members) ) {
                $new_members = implode("\n", $members);
                $new_members = $old_members . "\n" . $new_members;
            if($this->tripleStore->delete($group_name, WIKINI_VOC_ACLS, $old_members, GROUP_PREFIX)) {
                $this->tripleStore->create($group_name, WIKINI_VOC_ACLS, $new_members, GROUP_PREFIX);
            } else {
                $this->tripleStore->update($group_name, WIKINI_VOC_ACLS, $old_members, $new_members , GROUP_PREFIX)
            }
        }
    }

}
