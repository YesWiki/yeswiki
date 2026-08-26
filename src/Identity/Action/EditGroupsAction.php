<?php

namespace YesWiki\Identity\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Entity\Avatar;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AvatarService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;

class EditGroupsAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{editgroups}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'editgroups';
    }

    public function components(): array
    {
        return [
            Component::for('editgroups')
                ->category(Category::Admin)
                ->label(_t('AB_management_editgroups_label'))
                ->icon('users')
                ->previewHeight('200px')
                ->adminOnly(),
        ];
    }

    public function run(): string
    {
        $groupOperationsService = $this->getService(GroupOperationsService::class);
        $userManager = $this->getService(UserManager::class);

        if (!$this->getService(AclService::class)->isAdmin()) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => 'EditGroupsAction : ' . _t('BAZ_NEED_ADMIN_RIGHTS'),
            ]);
        }

        $message = '';
        $type = 'danger';
        $currentGroupAcl = '';
        $selectedGroupName = '';
        $action = '';
        $error_message = '';
        $post = $this->getRequest()->request;

        if ($post->count() === 0) {
        } else {
            if (empty($post->get('groupname'))) {
                $type = 'danger';
                $message = _t('NO_VAR_GROUP');
            } elseif (!is_string($post->get('groupname'))) {
                $type = 'danger';
                $message = 'Invalid ' . _t('GROUP_NOT_STRING');
            } else {
                $selectedGroupName = strval($post->get('groupname'));
                try {
                    $this->confirmToken();
                    if (!empty($post->get('action-view'))) {
                        $currentGroupAcl = $groupOperationsService->getMembers($selectedGroupName);
                    } elseif (!empty($post->get('action-create'))) {
                        $groupOperationsService->create($selectedGroupName, []);
                        $type = 'success';
                        $message = str_replace('{group}', $selectedGroupName, _t('GROUP_CREATED'));
                    } elseif (!empty($post->get('action-update'))) {
                        $members = array_map('trim', $post->all('members'));
                        $groupOperationsService->update($selectedGroupName, $members);
                        $message = str_replace('{group}', $selectedGroupName, _t('GROUP_SAVED'));
                        $type = 'success';
                    } elseif (!empty($post->get('action-delete'))) {
                        $groupOperationsService->delete($selectedGroupName);
                        $message = str_replace('{group}', $selectedGroupName, _t('GROUP_DELETED'));
                        $type = 'success';
                        $selectedGroupName = '';
                    }
                } catch (\Throwable $th) {
                    $type = 'danger';
                    $message = _t('ERROR_WHILE_EDITING_GROUP') . '"' . $selectedGroupName . '" :<br/>' . $th->getMessage();
                }
            }
        }

        if ($groupOperationsService->groupExists($selectedGroupName)) {
            $currentGroupAcl = $groupOperationsService->getMembers($selectedGroupName);
        }

        if (!empty($message)) {
            $error_message = ['type' => $type, 'message' => $message];
        }

        $list = $groupOperationsService->getAll();
        sort($list);
        $users = array_map(function ($user) { return $user['name']; }, $userManager->getAll());
        sort($users);
        $merged_list = array_merge(array_map(function ($el) { return '@' . $el; }, $list), $users);
        $ownEntry = array_search('@' . $selectedGroupName, $merged_list);
        if ($ownEntry !== false) {
            unset($merged_list[$ownEntry]);
        }

        $groups = array_map(function ($name) use ($groupOperationsService) {
            return ['name' => $name, 'members' => count($groupOperationsService->getMembers($name))];
        }, $list);

        $field = ['name' => '', 'propertyName' => '', 'required' => false, 'label' => $selectedGroupName];

        return $this->render(
            '@core/actions/edit-group-action.twig',
            ['error_message' => $error_message, 'list' => $list, 'groups' => $groups, 'selectedGroupName' => $selectedGroupName, 'field' => $field, 'options' => $merged_list, 'selectedOptionsId' => $currentGroupAcl, 'avatars' => $this->avatarsFor($merged_list), 'formName' => _t('USERS_GROUPS_LIST'), 'name' => _t('GROUP_SELECTION')]
        );
    }

    /**
     * A face for every member that is an account.
     *
     * @param array<int|string, string> $members
     *
     * @return array<string, Avatar>
     */
    private function avatarsFor(array $members): array
    {
        $avatarService = $this->getService(AvatarService::class);
        $avatars = [];
        foreach ($members as $member) {
            if (!str_starts_with($member, '@')) {
                $avatars[$member] = $avatarService->forName($member);
            }
        }

        return $avatars;
    }

    protected function confirmToken(): void
    {
        $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'confirmToken', false);
    }
}
