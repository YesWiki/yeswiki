<?php

namespace YesWiki\Core\Controller;

use YesWiki\Core\Exception\InvalidGroupNameException;
use YesWiki\Core\Exception\GroupNameDoesNotExistException;
use YesWiki\Core\Exception\GroupNameAlreadyUsedException;
use YesWiki\Core\Exception\UserNameDoesNotExistException;
use YesWiki\Core\Exception\InvalidInputException;
use Exception;
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
    private function isNameValid(string $name): bool
    {
        if (str_starts_with($name, "@")) {
            $name = substr($name, 1);
        }
        return !preg_match('/[^A-Za-z0-9]/', $name);
    }
    
        /**
     * @param string $groupName
     * @return bool
     */
    public function groupExists(string $name): bool
    {
        if (str_starts_with($name, "@"))
        {
            $name = substr($name, 1);
        }
        return $this->groupManager->groupExists($name);
    }


    /**
     * @param string $groupName
     * @return array the ACL associated with the current group
     */
    public function getMembers(string $groupName): array
    {
        if ($this->groupManager->groupExists($groupName)) {
            return $this->groupManager->getMembers($groupName);
        }
        throw new GroupNameDoesNotExistException(_t('GROUP_NAME_DOES_NOT_EXIST'));
    }

    /**
    *  create group
    * @param string $name group name
    * @param ?array $users users and/or groups to add
    * @throws GroupNameAlreadyExist
    * @return array|null
    */
    public function create(string $name, ?array $members): array|null
    {
        if ($this->groupManager->groupExists($name)) {
            throw new GroupNameAlreadyUsedException(_t('GROUP_NAME_ALREADY_USED'));
        }
        if ($this->isNameValid($name)) {
            foreach($members as $member) {
                // plus nécessaire à la création du groupe ?
                switch ($this->checkMemberValidity($name, $member)) {
                    case 0:
                        break;
                    case 1:
                        throw new UserNameDoesNotExistException($member);
                    case 2:
                        throw new GroupNameDoesNotExistException(_t('GROUP_NAME_DOES_NOT_EXIST'));
                    case 3:
                        throw new InvalidInputException(_t('ERROR_RECURSIVE_GROUP'));
                }
            }
            $this->groupManager->create($name, $members);
        } else {
            throw new InvalidGroupNameException(_t('INVALID_GROUP_NAME'));
        }
        if ($this->groupManager->groupExists($name)) {
            $entry = $this->groupManager->getMembers($name);
            return array("name" => $name, "members" => $entry);
        }
        throw new Exception(_t('ERROR_SAVING_GROUP') . '.');
        return null;
    }

    public function getAll(): array
    {
        return $this->groupManager->getAll();
    }

    /**
    *  delete group
    * @return void
    */
    public function delete(string $name): void
    {
        if ($this->groupManager->groupExists($name)) {
            $this->groupManager->delete($name);
        } else {
            throw new GroupNameDoesNotExistException(_t('GROUP_NAME_DOES_NOT_EXIST'));
        }
    }

    /**
    *  add users and/or groups to group
    * @param string $groupName
    * @param array $members users and/or groups to add
    * @throws UserDoesNotExistException
    * @throws GroupDoesNotExistException
    * @throws InvalidInputException
    * @return void
    */
    public function add(string $groupName, array $members): void
    {
        $this->addOrUpdate($groupName, $members, true);
    }

    /**
     * Check if member is valid for group. Perform following check :
     *  - if $member is a user, check if the user exists
     *  - if $member is a group, check if the group exists
     *  - if $member is a group, check if the group doesn't define itself recursively
     *  @param string $groupName (without !/@)
     *  @param string $member (with !/@ if present)
     *  @return : int
     *      - 0 if ok
     *      - 1 if user doesn't exist
     *      - 2 if group doesn't exist
     *      - 3 if group is recursive
     */
    private function checkMemberValidity(string $groupName, string $member): int
    {
        if(str_starts_with($member, "!")) {
            $member = substr($member, 1);
        }
        if(str_starts_with($member, "@")) {

            if(!$this->groupExists($member)) {
                return 2;
            }
            if($this->CheckGroupRecursive(substr($member, 1), strtolower(trim($groupName)))) {
                return 3;
            }
        } else {
            if(!$this->userManager->userExist($member)) {
                return 1;
            }
        }
        return 0;
    }


    /**
     * Checks if a new group acl is defined recursively
     * (this method expects that groups that are already defined are not themselves defined recursively...)
     *
     * @param string $groupName
     *            The name of the group to test against origin
     * @param string $origin group name to save test recursivity
     * @return bool True if the new acl defines the group recursively
     */
    private function CheckGroupRecursive($groupName, $origin, $checked = array()): bool
    {
        $groupName = strtolower(trim($groupName));
        if ($groupName === $origin) {
            return true;
        }
        $recursive_members = $this->getMembers($groupName);
        foreach ($recursive_members as $line) {
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
        $checked[] = $groupName;
        return false;
    }

    /**
     *  remove  users  and/or groups from group
     * @param array $members users and/or groups to add
     * @return void
     * @throws GroupDoesNotExistException
     */
    public function remove(string $groupName, array $members): void
    {
        if(!$this->groupManager->groupExists($groupName)) {
            throw new GroupNameDoesNotExistException(_t('GROUP_NAME_DOES_NOT_EXIST'));
        }
        $this->groupManager->removeMembers($groupName, $members);
    }
    
    /**
     *  add or replace current members with new one
     * @param string $groupName
     * @param array $members new members List
     * @param string $action
     * @return bool
     * @throws UserDoesNotExistException
     * @throws GroupDoesNotExistException
     * @throws InvalidInputException
     */
    private function addOrUpdate(string $groupName, array $members, bool $add):void {
         if(!$this->groupManager->groupExists($groupName)) {
            throw new GroupNameDoesNotExistException(_t('GROUP_NAME_DOES_NOT_EXIST'));
        }
        foreach($members as $member) {
            switch ($this->checkMemberValidity($groupName, $member)) {
                case 0:
                    break;
                case 1:
                    throw new UserNameDoesNotExistException(_t('USER_NAME_DOES_NOT_EXIST'));
                case 2:
                    throw new GroupNameDoesNotExistException("included "._t('GROUP_NAME_DOES_NOT_EXIST'));
                case 3:
                    throw new InvalidInputException(_t('ERROR_RECURSIVE_GROUP'));
            }
        }
        if ($add) {
            $this->groupManager->addMembers($groupName, $members);
        } else {
            $this->groupManager->update($groupName, $members);
        }
    }

    /**
     *  replace current members with new one
     * @param string $groupName
     * @param array $members new members List
     * @return void
     * @throws UserDoesNotExistException
     * @throws GroupDoesNotExistException
     * @throws InvalidInputException
     */
    public function update(string $groupName, array $members):void 
    {
        this->addOrUpdate($groupName, $members, false);
    }
}
