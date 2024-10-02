<?php

namespace YesWiki\Core\Controller;


use YesWiki\Core\Exception\InvalidGroupNameException;
use YesWiki\Core\Exception\GroupNameDoesNotExistException;
use YesWiki\Core\Exception\GroupNameAlreadyUsedException;
use YesWiki\Core\Exception\UserNameDoesNotExistException;
use YesWiki\Core\Exception\InvalidInputException;
use YesWiki\Core\Service\GroupManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;

class GroupController extends YesWikiController
{
    protected $groupManager;
    protected $userManager;

    public function __construct(
        GroupManager $groupManager,
        UserManager $userManager
    ) {
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
    }

    /**
     * @param string $groupName
     * @return bool
     */
    private function isNameValid(string $name):bool {
        if ( str_starts_with($name, "@")) {
            $name = substr($name, 1);
        }
        return preg_match('/[^A-Za-z0-9]/', $name);
    }
      
    
    /**
     * @param string $groupName
     * @return array the ACL associated with the current group
     */
    public function getMembers(string $groupName) : array
    {
            return $this->groupManager->getMembers($groupName);
    }
    
     /**
     *  create group
     * @param string $name group name
     * @param ?array $users users and/or groups to add
     * @throws GroupNameAlreadyExist
     * @return void
     */
    public function create(string $name, ?array $members): void
    {
        if ($this->groupManager->groupExists($name)) {
                   throw new GroupNameAlreadyUsedException(_t('GROUP_NAME_ALREADY_USED'));
        } 
        if ($this->isNameValid($name))
        {
            $this->groupManager->create($name, $members);
        } else {
            throw new InvalidGroupNameException(_t('INVALID_GROUP_NAME'));  // FIXME vérifier INVALID_GROUP_NAME
        }
    }
    
     /**
     *  delete group
     * @return void
     */
    public function delete(string $name): void
    {
        if ($this->groupManager->groupExists($name)) {
            $this->groupManager->delete($name);
        }
    }
    
     /**
     *  add users and/or groups to group
     * @param string $group_name
     * @param array $names users and/or groups to add
     * @throws UserDoesNotExistException
     * @throws GroupDoesNotExistException
     * @return void
     */
    public function add(string $group_name, array $names): void
    {
        if(!$this->groupManager->groupExists($group_name)) {
            throw new GroupNameDoesNotExistException();
        }
        foreach ($names as $name) {
            if(str_starts_with($name, "!")) {
              $name = substr($name, 1);
            }
            if(str_starts_with($name, "@")) {
                
                if(!$this->groupManager->groupExists($name)) {
                    throw new GroupNameDoesNotExistException(_t('GROUP_NAME_DOES_NOT_EXIST')); 
                } 
                if(!this->CheckGroupRecursive($name,$group_name)) {
                    throw new InvalidInputException(_t('RECURSIVE_GROUP_ERROR')); //FIXME
                }
            } else {
                if(!$this->userManager->userExist($name)) {
                    throw new UserNameDoesNotExistException(_t('USER_NAME_DOES_NOT_EXIST')); 
                }
            }
            }
        $this->groupManager->add($group_name, $name);
    }
    
        /**
     * Checks if a new group acl is not defined recursively
     * (this method expects that groups that are already defined are not themselves defined recursively...)
     *
     * @param string $group_name
     *            The name of the group to test against origin
     * @param string $origin group name to save test recursivity
     * @return bool True if the new acl defines the group recursively
     */
    private function CheckGroupRecursive($group_name, $origin, $checked = array()): bool
    {
        $group_name = strtolower(trim($group_name));
       if ($group_name === $origin) {
            return true;
        }
        $members = this->get_members($group_name);
        $recursive_members = str_replace(["\r\n", "\r"], "\n", $recursive_members);
        foreach (explode("\n", $recursive_members) as $line) {
            $line = trim($line);
            if (!$line) {
                continue;
            }

            if ($line[0] == '!') {
                $line = substr($line, 1);
            }
            if (!$line) {
                continue;
            }

            if ($line[0] == '@') {
                $line = substr($line, 1);
                if (!in_array($line, $checked)) {
                    if ($this->CheckGroupRecursive($line, $origin, $checked)) {
                        return true;
                    }
                }
            }
        }
        $checked[] = $group_name;
        return false;
    }
    
    /**
     *  remove  users  and/or groups from group
     * @param array $names users and/or groups to add
     * @return bool
     * @throws UserDoesNotExistException
     * @throws GroupDoesNotExistException
     */
    public function remove(array $names): void
    {
     $this->groupManager->removeMembers($names);
    }
    
    /**
     *  replace current members with new one
     * @param string $groupName 
     * @param array $names new members List
     * @return bool
     * @throws UserDoesNotExistException
     * @throws GroupDoesNotExistException
     */
     public function update(string $groupName, array $names) {
        
    }
}
