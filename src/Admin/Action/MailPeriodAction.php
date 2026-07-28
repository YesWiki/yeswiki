<?php

namespace YesWiki\Admin\Action;
// ticket 18: relocated from tools/contact/actions/MailPeriodAction.php.

use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

// TODO create GroupManager

class MailPeriodAction extends YesWikiAction implements RegisteredAction
{
    /** `{{mailperiod}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'mailperiod';
    }

    protected $authenticationService;
    protected $userManager;

    public function run()
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

            // Updating again to get modification from previous operations
            $periods = $this->updatePeriods($periods, $userName);
        }

        return $this->render('@core/mailperiod.twig', [
            'user' => $user,
            'messages' => $messages,
            'periods' => $periods,
        ]);
    }

    private function updatePeriods($periods, $userName)
    {
        foreach ($periods as $period => $config) {
            $group = $this->groupName($period);
            $periods[$period]['subscribed'] = $this->userManager->isInGroup($group, $userName, false);
            $periods[$period]['group'] = $this->groupName($period);
        }

        return $periods;
    }

    private function groupName($period): string
    {
        return "Mail{$this->wiki->getPageTag()}" . ucfirst($period);
    }

    private function subscribeUserToGroup($userName, $group): void
    {
        $this->wiki->SetGroupACL($group, $this->wiki->GetGroupACL($group) . "\n" . $userName);
    }

    private function unsubscribeUserFromGroup($userName, $group): void
    {
        $newgroup = str_replace($userName, '', $this->wiki->GetGroupACL($group));
        $newgroup = explode("\n", $newgroup);
        $newgroup = array_map('trim', $newgroup);
        $newgroup = array_filter($newgroup);
        $newgroup = implode("\n", $newgroup);
        $this->wiki->SetGroupACL($group, $newgroup);
    }

    private function unsubscribUserFromAllGroups($userName, $periods)
    {
        // unsubscribe all groups
        foreach ($periods as $period => $config) {
            if ($config['subscribed']) {
                $this->unsubscribeUserFromGroup($userName, $config['group']);
            }
        }
    }
}
