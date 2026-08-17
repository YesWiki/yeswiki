<?php

namespace YesWiki\Identity\Service;

use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Exception\GroupNameAlreadyUsedException;
use YesWiki\Identity\Exception\GroupNameDoesNotExistException;
use YesWiki\Identity\Exception\InvalidGroupNameException;
use YesWiki\Identity\Exception\UserNameDoesNotExistException;
use YesWiki\Kernel\Exception\InvalidInputException;

class GroupOperationsService extends YesWikiController
{
    protected $groupManager;
    protected $userManager;
    protected $authenticationService;

    public function __construct(
        GroupManager $groupManager,
        UserManager $userManager,
        AuthenticationService $authenticationService
    ) {
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->authenticationService = $authenticationService;
    }

    private function isNameValid(string $name): bool
    {
        if (str_starts_with($name, '@')) {
            $name = substr($name, 1);
        }

        return !preg_match('/[^A-Za-z0-9]/', $name);
    }

    public function groupExists(string $name): bool
    {
        if (str_starts_with($name, '@')) {
            $name = substr($name, 1);
        }

        return $this->groupManager->groupExists($name);
    }

    /**
     * The group's members as historic ACL text, one name per line -- "" when the group does not exist (historic Wiki::GetGroupACL()).
     */
    public function getMembersText(string $group): string
    {
        try {
            return implode("\n", $this->getMembers($group));
        } catch (GroupNameDoesNotExistException $th) {
            return '';
        }
    }

    /**
     * Create or update a group from an ACL-style newline-separated member list (historic Wiki::SetGroupACL()).
     *
     * @return int 0 on success, 1000 when the definition would be recursive,
     *             1001 when the group name is invalid
     */
    public function setMembersFromAclText(string $groupName, string $aclText): int
    {
        $aclText = str_replace(["\r\n", "\r"], "\n", $aclText);
        $members = array_map('trim', explode("\n", $aclText));
        try {
            if ($this->groupExists($groupName)) {
                $this->update($groupName, $members);
            } else {
                $this->create($groupName, $members);
            }
        } catch (InvalidGroupNameException $th) {
            return 1001;
        } catch (InvalidInputException $th) {
            return 1000;
        }

        return 0;
    }

    /**
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
     * create group.
     *
     * @param string $name group name
     *
     * @throws GroupNameAlreadyExist
     */
    public function create(string $name, ?array $members): ?array
    {
        if ($this->groupManager->groupExists($name)) {
            throw new GroupNameAlreadyUsedException(_t('GROUP_NAME_ALREADY_USED'));
        }
        if ($this->isNameValid($name)) {
            foreach ($members as $member) {
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
            $group_created = $this->groupManager->create($name, $members);
        } else {
            throw new InvalidGroupNameException(_t('INVALID_GROUP_NAME'));
        }
        if ($group_created != 1) {
            $entry = $this->groupManager->getMembers($name);

            return ['name' => $name, 'members' => $entry];
        }
        throw new \Exception(_t('ERROR_SAVING_GROUP') . '.');
    }

    public function getAll(): array
    {
        return $this->groupManager->getAll();
    }

    /** delete group. */
    public function delete(string $name): void
    {
        if (strtolower($name) == ADMIN_GROUP) {
            throw new InvalidInputException(_t('GROUP_NAME_DOES_NOT_EXIST'));
        }
        if ($this->groupManager->groupExists($name)) {
            $this->groupManager->delete($name);
        } else {
            throw new GroupNameDoesNotExistException(_t('GROUP_NAME_DOES_NOT_EXIST'));
        }
    }

    /**
     * add users and/or groups to group.
     *
     * @param array $members users and/or groups to add
     *
     * @throws UserDoesNotExistException
     * @throws GroupDoesNotExistException
     * @throws InvalidInputException
     */
    public function add(string $groupName, array $members): void
    {
        $this->addOrUpdate($groupName, $members, true);
    }

    /**
     * Check if member is valid for group.
     *
     * @param string $groupName (without !/@)
     * @param string $member    (with !/@ if present)
     *
     * @return int 0 if ok, 1 if the user does not exist, 2 if the group does not exist,
     *             3 if the group is recursive
     */
    private function checkMemberValidity(string $groupName, string $member): int
    {
        if (str_starts_with($member, '!')) {
            $member = substr($member, 1);
        }
        if (str_starts_with($member, '@')) {
            if (!$this->groupExists($member)) {
                return 2;
            }
            if ($this->CheckGroupRecursive(substr($member, 1), strtolower(trim($groupName)))) {
                return 3;
            }
        } else {
            if (!$this->userManager->userExist($member)) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * Checks if a new group acl is defined recursively (this method expects that groups that are already defined are not themselves defined recursively...).
     *
     * @param string $groupName
     *                          The name of the group to test against origin
     * @param string $origin    group name to save test recursivity
     *
     * @return bool True if the new acl defines the group recursively
     */
    private function CheckGroupRecursive($groupName, $origin, $checked = []): bool
    {
        $groupName = trim($groupName);
        if (strtolower($groupName) === $origin) {
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
                if (!in_array(strtolower($line), $checked)) {
                    if ($this->CheckGroupRecursive($line, $origin, $checked)) {
                        return true;
                    }
                }
            }
        }
        $checked[] = strtolower($groupName);

        return false;
    }

    /**
     * remove users and/or groups from group.
     *
     * @param array $members users and/or groups to add
     *
     * @throws GroupDoesNotExistException
     */
    public function remove(string $groupName, array $members): void
    {
        if (!$this->groupManager->groupExists($groupName)) {
            throw new GroupNameDoesNotExistException(_t('GROUP_NAME_DOES_NOT_EXIST'));
        }
        if ($groupName == ADMIN_GROUP) {
            $currentUser = $this->authenticationService->getLoggedUser()['name'];
            if (in_array($currentUser, $members)) {
                throw new InvalidInputException(_t('USER_CANNOT_REMOVE_THEIRSELF_FROM_ADMIN'));
            }
        }
        $this->groupManager->removeMembers($groupName, $members);
    }

    /**
     * add or replace current members with new one.
     *
     * @param array $members new members List
     *
     * @return bool
     *
     * @throws UserDoesNotExistException
     * @throws GroupDoesNotExistException
     * @throws InvalidInputException
     */
    private function addOrUpdate(string $groupName, array $members, bool $add): void
    {
        if (!$this->groupManager->groupExists($groupName)) {
            throw new GroupNameDoesNotExistException(_t('GROUP_NAME_DOES_NOT_EXIST'));
        }
        foreach ($members as $member) {
            switch ($this->checkMemberValidity($groupName, $member)) {
                case 0:
                    break;
                case 1:
                    throw new UserNameDoesNotExistException(_t('USER_NAME_DOES_NOT_EXIST') . ' : ' . $member);
                case 2:
                    throw new GroupNameDoesNotExistException('included "' . $member . '" ' . _t('GROUP_NAME_DOES_NOT_EXIST'));
                case 3:
                    throw new InvalidInputException(_t('ERROR_RECURSIVE_GROUP'));
            }
        }
        if ($add) {
            $this->groupManager->addMembers($groupName, $members);
        } else {
            $this->groupManager->updateMembers($groupName, $members);
        }
    }

    /**
     * replace current members with new one.
     *
     * @param array $members new members List
     *
     * @throws UserDoesNotExistException
     * @throws GroupDoesNotExistException
     * @throws InvalidInputException
     */
    public function update(string $groupName, array $members): void
    {
        if ($groupName == ADMIN_GROUP) {
            $currentUser = $this->authenticationService->getLoggedUser()['name'];
            if (!in_array($currentUser, $members)) {
                throw new InvalidInputException(_t('USER_CANNOT_REMOVE_THEIRSELF_FROM_ADMIN'));
            }
        }
        $this->addOrUpdate($groupName, $members, false);
    }
}
