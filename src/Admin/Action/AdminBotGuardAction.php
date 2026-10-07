<?php

namespace YesWiki\Admin\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\BotGuard;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;

/** `{{adminbotguard}}`: submissions BotGuard refused, per day and reason. */
class AdminBotGuardAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'adminbotguard';
    }

    public function components(): array
    {
        return [
            Component::for('adminbotguard')
                ->category(Category::Admin)
                ->label(_t('AB_management_adminbotguard_label'))
                ->icon('shield')
                ->previewHeight('200px')
                ->adminOnly()
                ->settings(
                    Setting::number('days')
                        ->label(_t('AB_management_adminbotguard_days_label'))
                        ->default(BotGuard::COUNTERS_KEPT_DAYS),
                ),
        ];
    }

    public function formatArguments($arg)
    {
        return [
            'days' => max(1, min((int)($arg['days'] ?? BotGuard::COUNTERS_KEPT_DAYS), BotGuard::COUNTERS_KEPT_DAYS)),
        ];
    }

    public function run(): string
    {
        if (!$this->getService(AclService::class)->isAdmin()) {
            return $this->render('@core/alert-message.twig', [
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
