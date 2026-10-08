<?php

/**
 * Shows admins how many submissions BotGuard refused, per day and per reason.
 */
use YesWiki\Core\Service\BotGuard;
use YesWiki\Core\YesWikiAction;

class AdminBotGuardAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        return [
            'days' => max(1, min((int)($arg['days'] ?? BotGuard::COUNTERS_KEPT_DAYS), BotGuard::COUNTERS_KEPT_DAYS)),
        ];
    }

    public function run()
    {
        if (!$this->wiki->UserIsAdmin()) {
            return $this->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => get_class($this) . ' : ' . _t('BAZ_NEED_ADMIN_RIGHTS'),
            ]);
        }
        $perDay = $this->getService(BotGuard::class)->refusedPerDay($this->arguments['days']);
        $reasons = [];
        foreach ($perDay as $counts) {
            foreach ($counts as $reason => $count) {
                $reasons[$reason] = ($reasons[$reason] ?? 0) + $count;
            }
        }
        arsort($reasons);

        return $this->render('@core/admin-bot-guard.twig', [
            'days' => $this->arguments['days'],
            'perDay' => $perDay,
            'reasons' => $reasons,
            'total' => array_sum($reasons),
        ]);
    }
}
