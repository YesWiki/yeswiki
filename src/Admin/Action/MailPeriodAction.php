<?php

namespace YesWiki\Admin\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;

class MailPeriodAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{mailperiod}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'mailperiod';
    }

    /** @return list<Component> */
    public function components(): array
    {
        return [
            Component::for('mailperiod')
                ->category(Category::Admin)
                ->label(_t('AB_mailperiod_action_label'))
                ->icon('mail')
                ->hint(_t('AB_mailperiod_action_hint'))
                ->previewHeight('200px')
                ->adminOnly(),
        ];
    }

    protected AuthenticationService $authenticationService;
    protected UserManager $userManager;

    public function run(): string
    {
        $this->authenticationService = $this->getService(AuthenticationService::class);
        $this->userManager = $this->getService(UserManager::class);
        $user = $this->authenticationService->getLoggedUser();
        $userName = $this->authenticationService->getLoggedUserName();
        $periods = [
            'day' => ['label' => _t('CONTACT_DAILY')],
            'week' => ['label' => _t('CONTACT_WEEKLY')],
            'month' => ['label' => _t('CONTACT_MONTHLY')],
        ];
        $periods = $this->updatePeriods($periods, $userName);
        $messages = [];

        if ($user && !empty($userName)) {
            $request = $this->getRequest();
            if ($request->query->has('subscribe') || $request->request->has('subscribe')) {
                $period = $request->get('subscribe');
                $group = $periods[$period]['group'];
                $this->unsubscribUserFromAllGroups($userName, $periods);
                $this->subscribeUserToGroup($userName, $group);
                $messages['success'] = _t('CONTACT_SUCCESS_SUBSCRIBE') . $periods[$period]['label'];
            } elseif ($request->query->has('unsubscribe') || $request->request->has('unsubscribe')) {
                $this->unsubscribUserFromAllGroups($userName, $periods);
                $messages['info'] = _t('CONTACT_SUCCESS_UNSUBSCRIBE');
            }

            $periods = $this->updatePeriods($periods, $userName);
        }

        return $this->render('@core/mailperiod.twig', [
            'user' => $user,
            'messages' => $messages,
            'periods' => $periods,
        ]);
    }

    /**
     * @param array<string, array<string, mixed>> $periods
     * @param string                              $userName
     *
     * @return array<string, array<string, mixed>>
     */
    private function updatePeriods($periods, $userName)
    {
        foreach ($periods as $period => $config) {
            $group = $this->groupName($period);
            $periods[$period]['subscribed'] = $this->userManager->isInGroup($group, $userName, false);
            $periods[$period]['group'] = $this->groupName($period);
        }

        return $periods;
    }

    /** @param string $period */
    private function groupName($period): string
    {
        return "Mail{$this->getService(PageContext::class)->getTag()}" . ucfirst($period);
    }

    /**
     * @param string $userName
     * @param string $group
     */
    private function subscribeUserToGroup($userName, $group): void
    {
        $this->getService(GroupOperationsService::class)->setMembersFromAclText($group, $this->getService(GroupOperationsService::class)->getMembersText($group) . "\n" . $userName);
    }

    /**
     * @param string $userName
     * @param string $group
     */
    private function unsubscribeUserFromGroup($userName, $group): void
    {
        $newgroup = str_replace($userName, '', $this->getService(GroupOperationsService::class)->getMembersText($group));
        $newgroup = explode("\n", $newgroup);
        $newgroup = array_map('trim', $newgroup);
        $newgroup = array_filter($newgroup);
        $newgroup = implode("\n", $newgroup);
        $this->getService(GroupOperationsService::class)->setMembersFromAclText($group, $newgroup);
    }

    /**
     * @param string                              $userName
     * @param array<string, array<string, mixed>> $periods
     */
    private function unsubscribUserFromAllGroups($userName, $periods): void
    {
        foreach ($periods as $period => $config) {
            if ($config['subscribed']) {
                $this->unsubscribeUserFromGroup($userName, $config['group']);
            }
        }
    }
}
