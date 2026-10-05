<?php

namespace YesWiki\Contact;

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Contact\Service\MailSubscriptions;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Core\YesWikiAction;

/**
 * Lets the logged-in user choose how often they receive the current page by mail.
 */
class MailPeriodAction extends YesWikiAction
{
    public function run()
    {
        $subscriptions = $this->getService(MailSubscriptions::class);
        if ($subscriptions->isSending()) {
            return '';
        }
        $userName = $this->getService(AuthController::class)->getLoggedUserName();
        $pageTag = $this->wiki->getPageTag();
        $request = $this->getRequest();
        $messages = [];

        if (!empty($userName) && $request->isMethod('POST') && ($request->request->has('subscribe') || $request->request->has('unsubscribe'))) {
            $period = $request->request->get('subscribe');
            try {
                $this->getService(CsrfTokenController::class)->checkToken('main', 'POST', 'csrf-token', false);
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

        $subscribedPeriod = empty($userName) ? null : $subscriptions->periodOf($pageTag, $userName);

        return $this->render('@contact/mailperiod.twig', [
            'user' => !empty($userName),
            'messages' => $messages,
            'periods' => array_map(fn ($period, $label) => [
                'period' => $period,
                'label' => $label,
                'subscribed' => $period === $subscribedPeriod,
            ], array_keys($this->labels()), $this->labels()),
            'subscribed' => $subscribedPeriod !== null,
        ]);
    }

    private function labels(): array
    {
        return [
            'day' => _t('CONTACT_DAILY'),
            'week' => _t('CONTACT_WEEKLY'),
            'month' => _t('CONTACT_MONTHLY'),
        ];
    }
}
