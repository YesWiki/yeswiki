<?php

namespace YesWiki\Core\Controller;

use Exception;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Exception\DeleteUserException;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Security\Controller\SecurityController;

class UserController extends YesWikiController
{
    protected $dbService;
    protected $pageManager;
    protected $securityController;
    protected $tripleStore;
    protected $userManager;

    public function __construct(
        DbService $dbService,
        PageManager $pageManager,
        SecurityController $securityController,
        TripleStore $tripleStore,
        UserManager $userManager
    ) {
        $this->dbService = $dbService;
        $this->pageManager = $pageManager;
        $this->securityController = $securityController;
        $this->tripleStore = $tripleStore;
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
        if ($this->isRunner($user)) {
            throw new DeleteUserException(_t('USER_CANT_DELETE_ONESELF').'.');
        }
        $this->checkIfUserIsNotAloneInEachGroup($user);
        $this->deleteUserFromEveryGroup($user);
        $this->removeOwnership($user);
        $this->userManager->delete($user);
    }

    /**
     * get first admin name
     * @return string $adminName
     * @throws Exception
     */
    public function getFirstAdmin(): string
    {
        $admins = $this->wiki->GetGroupACL(ADMIN_GROUP);
        $admins = str_replace(["\r\n","\r"], "\n", $admins);
        $admins = explode("\n", $admins);
        foreach ($admins as $line) {
            $line = trim($line);
            if (!empty($line) &&
                !in_array(substr($line, 0, 1), ['@','!','#'])) {
                $adminUser = $this->userManager->getOneByName($line);
                if (!empty($adminUser['name'])) {
                    $admin = $adminUser['name'];
                    break;
                }
            }
        }
        if (empty($admin)) {
            throw new Exception("No admin found");
        }

        return $admin;
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
        $grouptab = $this->userManager->groupsWhereIsMember($user, false);
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
        $searchedValue = $this->dbService->escape($user['name']);
        $groups = $this->tripleStore->getMatching(
            GROUP_PREFIX."%",
            "http://www.wikini.net/_vocabulary/acls",
            "%$searchedValue%",
            "LIKE",
            "=",
            "LIKE"
        );
        $error = false;
        if (is_array($groups)) {
            $pregQuoteSearchValue = preg_quote($searchedValue, '/');
            foreach ($groups as $group) {
                $newValue = $group['value'];
                $newValue = preg_replace("/(?<=^|\\n|\\r)$pregQuoteSearchValue(?:\\r\\n|\\n|\\r|$)/", "", $newValue);
                if ($newValue != $group['value'] &&
                    !in_array($this->tripleStore->update(
                        $group['resource'],
                        $group['property'],
                        $group['value'],
                        $newValue,
                        '',
                        ''
                    ), [0,3])) {
                    $error = true;
                }
            }
        }
        if ($error) {
            throw new DeleteUserException(_t('USER_DELETE_QUERY_FAILED').'.');
        }
    }

    /**
     * remove user from every group
     * @param User $user
     * @throws Exception
     */
    private function removeOwnership(User $user)
    {
        $pagesWhereOwner = $this->dbService->loadAll("
            SELECT `tag` FROM {$this->dbService->prefixTable('pages')} 
            WHERE `owner` = \"{$this->dbService->escape($user['name'])}\"
            AND `latest` = \"Y\" ;
        ");
        $pagesWhereOwner = array_map(function ($page) {
            return $page['tag'];
        }, $pagesWhereOwner);

        $firstAdmin = $this->getFirstAdmin();
        foreach ($pagesWhereOwner as $tag) {
            $this->pageManager->setOwner($tag, $firstAdmin);
        }
    }
}
