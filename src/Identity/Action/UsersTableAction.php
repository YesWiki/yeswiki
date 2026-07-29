<?php

namespace YesWiki\Identity\Action;

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Exception\DeleteUserException;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\Service\UserOperationsService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetsManager;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\User;

class UsersTableAction extends YesWikiAction implements RegisteredAction
{
    /** `{{userstable}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'userstable';
    }

    protected $authenticationService;
    protected $csrfTokenChecker;
    protected $userOperationsService;
    protected $userManager;

    public function formatArguments($arg)
    {
        if (isset($arg['last'])) {
            $last = (int)$arg['last'];
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
        $this->authenticationService = $this->getService(AuthenticationService::class);
        $this->userOperationsService = $this->getService(UserOperationsService::class);
        $this->userManager = $this->getService(UserManager::class);
        $this->csrfTokenChecker = $this->getService(CsrfTokenChecker::class);

        $isAdmin = $this->getService(AclService::class)->isAdmin();

        if ($isAdmin) {
            // adds the activate/inactivate column (accountactivationbyemail, ticket 07)
            $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/users-table-addon.js');
        }

        // manage POST actions
        $postActionMessages = $this->managePostActions($this->getRequest()->request->all(), $isAdmin);

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
                        return 0;
                    }

                    return ($a['signuptime'] < $b['signuptime']) ? $valueIfLower : -$valueIfLower;
                } elseif (isset($a['signuptime'])) {
                    return -$valueIfLower;
                } elseif (isset($b['signuptime'])) {
                    return $valueIfLower;
                }

                return 0;
            });

            // limit
            if (!empty($this->arguments['last'])) {
                $users = array_slice($users, 0, $this->arguments['last'], true);
            }

            // add groups
            $users = $this->addGroups($users);
        }

        // connected user
        $connectedUser = $this->authenticationService->getLoggedUser();
        $connectedUserName = empty($connectedUser['name']) ? '' : $connectedUser['name'];

        return $this->render('@core/users-table.twig', [
            'connectedUserName' => $connectedUserName,
            'isAdmin' => $isAdmin,
            'postActionMessages' => $postActionMessages,
            'tag' => $this->getService(PageContext::class)->getTag(),
            'users' => $users,
        ]);
    }

    private function addGroups(array $users): array
    {
        return array_map(function ($user) {
            $userGroups = $this->userManager->groupsWhereIsMember($user);

            return array_merge($user->getArrayCopy(), ['groups' => $userGroups]);
        }, $users);
    }

    /**
     * manage Post Actions (delete)
     * with management of csrf token.
     *
     * @return string|null postActionMessages
     */
    private function managePostActions(array $post, bool $isAdmin): ?string
    {
        if ($isAdmin && (!empty($post['userstable_action']))) { // Check if the page received a post named 'userstable_action'
            $action = filter_var($post['userstable_action'], FILTER_UNSAFE_RAW);
            $action = in_array($action, [false, null], true) ? '' : htmlspecialchars(strip_tags($action));
            if ($action != 'deleteUser' || empty($post['username'])) {
                return $this->render('@core/alert-message.twig', [
                    'type' => 'danger',
                    'message' => _t('USER_USERSTABLE_MISTAKEN_ARGUMENT'),
                ]);
            }
            $userName = filter_var($post['username'], FILTER_UNSAFE_RAW);
            $userName = in_array($username, [false, null], true) ? '' : htmlspecialchars(strip_tags($userName));
            try {
                $rawUserName = str_replace(['&#039;', '&#39;'], ['\'', '\''], $userName);
                $this->csrfTokenChecker->checkToken('main', 'POST', 'csrf-token-delete', false);
                $user = $this->userManager->getOneByName($rawUserName);
                if (empty($user)) {
                    return $this->render('@core/alert-message.twig', [
                        'type' => 'danger',
                        'message' => str_replace('{username}', $userName, _t('USERSTABLE_NOT_EXISTING_USER')),
                    ]);
                }
                try {
                    $this->userOperationsService->delete($user);

                    return $this->render('@core/alert-message.twig', [
                        'type' => 'success',
                        'message' => str_replace('{username}', $userName, _t('USERSTABLE_USER_DELETED')),
                    ]);
                } catch (DeleteUserException $ex) {
                    return $this->render('@core/alert-message.twig', [
                        'type' => 'warning',
                        'message' => $ex->getMessage(),
                    ]);
                }
            } catch (TokenNotFoundException $th) {
                return $this->render('@core/alert-message.twig', [
                    'type' => 'danger',
                    'message' => str_replace('{username}', $userName, _t('USERSTABLE_USER_NOT_DELETED')) . ' ' . $th->getMessage(),
                ]);
            }
        }

        return null;
    }
}
