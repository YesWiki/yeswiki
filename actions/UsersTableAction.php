<?php

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\User;

class UsersTableAction extends YesWikiAction
{
    protected $csrfTokenController;
    protected $userManager;

    public function formatArguments($arg)
    {
        if (isset($arg['last'])) {
            $last = (int) $arg['last'];
            $last = ($last < 1) ? 12 : $last;
        } else {
            $last = null;
        }
        return [
            'last' => $last,
        ];
    }

    public function run()
    {
        // get Services
        $this->userManager  = $this->getService(UserManager::class);
        $this->csrfTokenController = $this->getService(CsrfTokenController::class);

        $isAdmin = $this->wiki->UserIsAdmin();

        // manage POST actions
        $postActionMessages = $this->managePostActions($_POST ?? [], $isAdmin);

        // get Users
        $users = $this->userManager->getAll();

        // order by signuptime decreasing
        if (empty($users)) {
            $users = [];
        } else {
            uasort($users, function ($a, $b) {
                $valueIfLower = 1; // decreasing (-1) for ascending
                if (isset($a['signuptime']) && isset($b['signuptime'])) {
                    if ($a['signuptime'] == $b['signuptime']) {
                        return 0 ;
                    } else {
                        return ($a['signuptime'] < $b['signuptime']) ? $valueIfLower : -$valueIfLower ;
                    }
                } elseif (isset($a['signuptime'])) {
                    return -$valueIfLower ;
                } elseif (isset($b['signuptime'])) {
                    return $valueIfLower ;
                } else {
                    return 0 ;
                }
            });

            // limit
            if (!empty($this->arguments['last'])) {
                $users = array_slice($users, 0, $this->arguments['last'], true);
            }

            // add groups
            $users = $this->addGroups($users);
        }

        // connected user
        $connectedUser = $this->userManager->getLoggedUser();
        $connectedUserName = empty($connectedUser['name']) ? '' : $connectedUser['name'];

        return $this->render('@core/users-table.twig', [
            'connectedUserName' => $connectedUserName,
            'isAdmin' => $isAdmin,
            'postActionMessages' => $postActionMessages,
            'tag' => $this->wiki->tag,
            'users' => $users,
        ]) ;
    }

    private function addGroups(array $users): array
    {
        $groups = $this->wiki->GetGroupsList();
        return array_map(function ($user) use ($groups) {
            $userGroups = [];
            foreach ($groups as $group) {
                if ($this->userManager->isInGroup($group, $user['name'], false)) { // false to not display admins in other groups
                    $userGroups[] = $group ;
                }
            }
            return array_merge($user, ['groups' => $userGroups]);
        }, $users);
    }

    /**
     * manage Post Actions (delete)
     * with management of csrf token
     * @param array $post
     * @param bool $isAdmin
     * @return string|null postActionMessages
     */
    private function managePostActions(array $post, bool $isAdmin): ?string
    {
        if ($isAdmin && (!empty($post['userstable_action']))) { // Check if the page received a post named 'userstable_action'
            $action = filter_var($post['userstable_action'],FILTER_UNSAFE_RAW);
            $action = ($action === false) ? "" : htmlspecialchars(strip_tags($action));
            if ($action != 'deleteUser' || empty($post['username'])) {
                return $this->render('@templates/alert-message.twig', [
                        'type' => 'danger',
                        'message' => _t('USER_USERSTABLE_MISTAKEN_ARGUMENT')
                ]);
            }
            $userName = filter_var($post['username'],FILTER_UNSAFE_RAW);
            $userName = ($userName === false) ? "" : htmlspecialchars(strip_tags($userName));
            try {
                $rawUserName = str_replace(['&#039;','&#39;'], ['\'','\''], $userName);
                $this->csrfTokenController->checkToken("action\\userstable\\deleteUser\\{$rawUserName}", 'POST', 'csrf-token-delete');
                $user = $this->userManager->getOneByName($rawUserName);
                if (empty($user)) {
                    return $this->render("@templates/alert-message.twig", [
                        'type' => 'danger',
                        'message' => str_replace('{username}', $userName, _t('USERSTABLE_NOT_EXISTING_USER'))
                    ]);
                } else {
                    // TODO use future $this->userManager->delete($user); instead of following lines
                    require_once 'includes/User.class.php';
                    $tmpUser = new User($this->wiki);
                    $OK= $tmpUser->loadByNameFromDB($rawUserName);
                    if ($OK) {
                        $OK = $tmpUser->delete();
                        if (!$OK) {
                            return $this->render('@templates/alert-message.twig', [
                                'type' => 'warning',
                                'message' => $tmpUser->error
                            ]);
                        } else {
                            return $this->render("@templates/alert-message.twig", [
                                'type' => 'success',
                                'message' => str_replace('{username}', $userName, _t('USERSTABLE_USER_DELETED'))
                            ]);
                        }
                    }
                    unset($tmpUser);
                }
            } catch (TokenNotFoundException $th) {
                return $this->render("@templates/alert-message.twig", [
                    'type' => 'danger',
                    'message' => str_replace('{username}', $userName, _t('USERSTABLE_USER_NOT_DELETED')).' '.$th->getMessage()
                ]);
            }
        }
        return null;
    }
}
