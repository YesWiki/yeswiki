<?php

namespace YesWiki\Core\Controller;

use Exception;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Exception\DeleteUserException;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Security\Controller\SecurityController;

class UserController extends YesWikiController
{
    protected $dbService;
    protected $securityController;
    protected $userManager;

    public function __construct(
        DbService $dbService,
        SecurityController $securityController,
        UserManager $userManager
    ) {
        $this->dbService = $dbService;
        $this->securityController = $securityController;
        $this->userManager = $userManager;
    }

    /**
     * delete a user but check if possible before
     * @param User $user
     * @throws DeleteUserException
     * @throws Exception
     */
    public function delete(User $user)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$this->wiki->UserIsAdmin()) {
            throw new DeleteUserException(_t('USER_MUST_BE_ADMIN_TO_DELETE').'.');
        }
        if ($this->isRunner()) {
            throw new DeleteUserException(_t('USER_CANT_DELETE_ONESELF').'.');
        }
        $this->checkIfUserIsNotAloneInEachGroup($user);
        $this->deleteUserFromEveryGroup($user);
        $this->userManager->delete($user);
    }

    /**
     * check if current user is the user to delete
     * @param User $user
     * @return bool
     */
    private function isRunner(User $user): bool
    {
        $loggedUser = $this->userManager->getLoggedUser();
        return (!empty($loggedUser) && ($loggedUser['name'] == $user['name']));
    }

    /**
     * check if user is not alone in each group
     * @param User $user
     * @throws DeleteUserException
     */
    private function checkIfUserIsNotAloneInEachGroup(User $user)
    {
        $grouptab = $this->userManager->groupsWhereIsMember($user);
        foreach ($grouptab as $group) {
            $groupmembers = $this->wiki->GetGroupACL($group);
            $groupmembers = str_replace(["\r\n","\r"], "\n", $groupmembers);
            $groupmembers = explode("\n", $groupmembers);
            $groupmembers = array_unique(array_filter(array_map('trim', $groupmembers)));
            if (count($groupmembers) == 1) { // Only one user in (this user then)
                throw new DeleteUserException(_t('USER_DELETE_LONE_MEMBER_OF_GROUP')." ($group).");
            }
        }
    }

    /**
     * remove user from every group
     * @param User $user
     * @throws DeleteUserException
     */
    private function deleteUserFromEveryGroup(User $user)
    {
        // Delete user in every group
        $triplesTable = $this->dbService->prefixTable('triples');
        $searchedValue = $this->dbService->escape($user['name']);

        $queries = [];

        // replace for first line
        $queries[] = "UPDATE $triplesTable SET `value` = ".
            "REPLACE(`value`,\"$searchedValue\\n\",\"\") ".
            "WHERE `resource` LIKE \"".GROUP_PREFIX."\" AND `value` LIKE \"$searchedValue\\n%\";";
        $queries[] = "UPDATE $triplesTable SET `value` = ".
            "REPLACE(`value`,\"$searchedValue\\r\\n\",\"\") ".
            "WHERE `resource` LIKE \"".GROUP_PREFIX."\" AND `value` LIKE \"$searchedValue\\r\\n%\";";
        $queries[] = "UPDATE $triplesTable SET `value` = ".
            "REPLACE(`value`,\"$searchedValue\\r\",\"\") ".
            "WHERE `resource` LIKE \"".GROUP_PREFIX."\" AND `value` LIKE \"$searchedValue\\r%\";";
        // replace for next lines (except last)
        $queries[] = "UPDATE $triplesTable SET `value` = ".
            "REPLACE(`value`,\"\\n$searchedValue\\n\",\"\\n\") ".
            "WHERE `resource` LIKE \"".GROUP_PREFIX."\" AND `value` LIKE \"%\\n$searchedValue\\n%\";";
        $queries[] = "UPDATE $triplesTable SET `value` = ".
            "REPLACE(`value`,\"\\r$searchedValue\\n\",\"\\n\") ".
            "WHERE `resource` LIKE \"".GROUP_PREFIX."\" AND `value` LIKE \"%\\r$searchedValue\\n%\";";
        $queries[] = "UPDATE $triplesTable SET `value` = ".
            "REPLACE(`value`,\"\\n$searchedValue\\r\\n\",\"\\n\") ".
            "WHERE `resource` LIKE \"".GROUP_PREFIX."\" AND `value` LIKE \"%\\n$searchedValue\\r\\n%\";";
        $queries[] = "UPDATE $triplesTable SET `value` = ".
            "REPLACE(`value`,\"\\r$searchedValue\\r\\n\",\"\\n\") ".
            "WHERE `resource` LIKE \"".GROUP_PREFIX."\" AND `value` LIKE \"%\\r$searchedValue\\r\\n%\";";
        $queries[] = "UPDATE $triplesTable SET `value` = ".
            "REPLACE(`value`,\"\\n$searchedValue\\r\",\"\\n\") ".
            "WHERE `resource` LIKE \"".GROUP_PREFIX."\" AND `value` LIKE \"%\\n$searchedValue\\r%\";";
        $queries[] = "UPDATE $triplesTable SET `value` = ".
            "REPLACE(`value`,\"\\r$searchedValue\\r\",\"\\n\") ".
            "WHERE `resource` LIKE \"".GROUP_PREFIX."\" AND `value` LIKE \"%\\r$searchedValue\\r%\";";
        // replace for last line
        $queries[] = "UPDATE $triplesTable SET `value` = ".
            "REPLACE(`value`,\"\\n$searchedValue\",\"\\n\") ".
            "WHERE `resource` LIKE \"".GROUP_PREFIX."\" AND `value` LIKE \"%\\n$searchedValue\";";
        $queries[] = "UPDATE $triplesTable SET `value` = ".
            "REPLACE(`value`,\"\\r\\n$searchedValue\",\"\\n\") ".
            "WHERE `resource` LIKE \"".GROUP_PREFIX."\" AND `value` LIKE \"%\\r\\n$searchedValue\";";
        $queries[] = "UPDATE $triplesTable SET `value` = ".
            "REPLACE(`value`,\"\\r$searchedValue\",\"\\n\") ".
            "WHERE `resource` LIKE \"".GROUP_PREFIX."\" AND `value` LIKE \"%\\r$searchedValue\";";

        foreach ($queries as $query) {
            try {
                if (!$this->dbService->query($query)) {
                    throw new DeleteUserException(_t('USER_DELETE_QUERY_FAILED').'.');
                }
            } catch (Exception $ex) {
                throw new DeleteUserException(_t('USER_DELETE_QUERY_FAILED').'.');
            }
        }
    }
}
