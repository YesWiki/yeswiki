<?php

namespace YesWiki\Admin\Action;

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Contact\Service\MailSubscriptions;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\CsrfTokenChecker;
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

    public function run(): string
    {
        $subscriptions = $this->getService(MailSubscriptions::class);
        if ($subscriptions->isSending()) {
            return '';
        }
        $userName = $this->getService(AuthenticationService::class)->getLoggedUserName();
        $pageTag = (string)$this->getService(PageContext::class)->getTag();
        $request = $this->getRequest();
        $messages = [];

        if ($userName !== '' && $request->isMethod('POST') && ($request->request->has('subscribe') || $request->request->has('unsubscribe'))) {
            $period = (string)$request->request->get('subscribe', '');
            try {
                $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);
                if ($request->request->has('unsubscribe')) {
                    $subscriptions->unsubscribe($pageTag, $userName);
                    $messages['info'] = _t('CONTACT_SUCCESS_UNSUBSCRIBE');
                } elseif (in_array($period, MailSubscriptions::PERIODS, true)) {
                    $subscriptions->subscribe($pageTag, $userName, $period);
                    $messages['success'] = _t('CONTACT_SUCCESS_SUBSCRIBE') . $this->labels()[$period];
                }
            } catch (TokenNotFoundException $e) {
                $messages['danger'] = $e->getMessage();
            }
        }

        $subscribedPeriod = $userName === '' ? null : $subscriptions->periodOf($pageTag, $userName);

        return $this->render('@core/mailperiod.twig', [
            'user' => $userName !== '',
            'messages' => $messages,
            'periods' => array_map(fn (string $period, string $label) => [
                'period' => $period,
                'label' => $label,
                'subscribed' => $period === $subscribedPeriod,
            ], array_keys($this->labels()), $this->labels()),
            'subscribed' => $subscribedPeriod !== null,
        ]);
    }

    /** @return array<string, string> each period's label */
    private function labels(): array
    {
        return [
            'day' => _t('CONTACT_DAILY'),
            'week' => _t('CONTACT_WEEKLY'),
            'month' => _t('CONTACT_MONTHLY'),
        ];
    }
}
