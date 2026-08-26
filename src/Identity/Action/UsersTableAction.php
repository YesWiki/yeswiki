<?php

namespace YesWiki\Identity\Action;

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Exception\DeleteUserException;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\AvatarService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\Service\UserOperationsService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\PageContext;

class UsersTableAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{userstable}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'userstable';
    }

    public function components(): array
    {
        return [
            Component::for('userstable')
                ->category(Category::Admin)
                ->label(_t('AB_management_userstable_label'))
                ->icon('users')
                ->previewHeight('200px')
                ->adminOnly()
                ->settings(
                    Setting::number('last')
                        ->label(_t('AB_advanced_action_listusers_last_label'))
                        ->hint(_t('AB_advanced_action_listusers_last_hint'))
                        ->default('')
                        ->min(1),
                ),
        ];
    }

    protected AuthenticationService $authenticationService;
    protected CsrfTokenChecker $csrfTokenChecker;
    protected UserOperationsService $userOperationsService;
    protected UserManager $userManager;

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

    public function run(): string
    {
        $this->authenticationService = $this->getService(AuthenticationService::class);
        $this->userOperationsService = $this->getService(UserOperationsService::class);
        $this->userManager = $this->getService(UserManager::class);
        $this->csrfTokenChecker = $this->getService(CsrfTokenChecker::class);

        $isAdmin = $this->getService(AclService::class)->isAdmin();

        if ($isAdmin) {
            $this->getService(AssetRegistry::class)->addJsFile('javascripts/users-table-addon.js');
        }

        $postActionMessages = $this->managePostActions($this->getRequest()->request->all(), $isAdmin);

        $users = $this->userManager->getAll();

        if (empty($users)) {
            $users = [];
        } else {
            uasort($users, function ($a, $b) {
                $valueIfLower = 1;
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

            if (!empty($this->arguments['last'])) {
                $users = array_slice($users, 0, $this->arguments['last'], true);
            }

            $users = $this->addGroups($users);
        }

        $connectedUser = $this->authenticationService->getLoggedUser();
        $connectedUserName = is_array($connectedUser) ? strval($connectedUser['name'] ?? '') : '';

        $avatarService = $this->getService(AvatarService::class);

        return $this->render('@core/users-table.twig', [
            'avatars' => array_combine(
                array_map(fn ($user) => (string)$user['name'], $users),
                array_map(fn ($user) => $avatarService->forName((string)$user['name']), $users)
            ),
            'connectedUserName' => $connectedUserName,
            'isAdmin' => $isAdmin,
            'postActionMessages' => $postActionMessages,
            'tag' => $this->getService(PageContext::class)->getTag(),
            'users' => $users,
        ]);
    }

    /**
     * @param User[] $users
     *
     * @return array<int|string, array<string, mixed>> the same users, each with its group list added
     */
    private function addGroups(array $users): array
    {
        return array_map(function ($user) {
            $userGroups = $this->userManager->groupsWhereIsMember($user);

            return array_merge($user->getArrayCopy(), ['groups' => $userGroups]);
        }, $users);
    }

    /**
     * manage Post Actions (delete) with management of csrf token.
     *
     * @param array<string, mixed> $post
     *
     * @return string|null postActionMessages
     */
    private function managePostActions(array $post, bool $isAdmin): ?string
    {
        if ($isAdmin && (!empty($post['userstable_action']))) {
            $action = filter_var($post['userstable_action'], FILTER_UNSAFE_RAW);
            $action = in_array($action, [false, null], true) ? '' : htmlspecialchars(strip_tags($action));
            if ($action != 'deleteUser' || empty($post['username'])) {
                return $this->render('@core/alert-message.twig', [
                    'type' => 'danger',
                    'message' => _t('USER_USERSTABLE_MISTAKEN_ARGUMENT'),
                ]);
            }
            $userName = filter_var($post['username'], FILTER_UNSAFE_RAW);

            $userName = in_array($userName, [false, null], true) ? '' : htmlspecialchars(strip_tags($userName));
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
