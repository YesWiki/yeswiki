<?php

namespace YesWiki\Contact\Service;

use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Wiki;

/**
 * Who receives a page's content by mail, and how often: one triple (page, period, user) per subscription.
 */
class MailSubscriptions
{
    public const PERIODS = ['day', 'week', 'month'];
    public const PROPERTY_PREFIX = 'http://yeswiki.net/_vocabulary/mailSubscription/';

    private static bool $sending = false;

    public function __construct(
        protected TripleStore $tripleStore,
        protected PageManager $pageManager,
        protected UserManager $userManager,
        protected Wiki $wiki
    ) {
    }

    /** The period $userName receives $pageTag at, null when not subscribed. */
    public function periodOf(string $pageTag, string $userName): ?string
    {
        foreach (self::PERIODS as $period) {
            if (!empty($this->tripleStore->getMatching($pageTag, self::PROPERTY_PREFIX . $period, $userName, '=', '=', '='))) {
                return $period;
            }
        }

        return null;
    }

    /** Subscribes $userName to $pageTag at $period, replacing any other period. */
    public function subscribe(string $pageTag, string $userName, string $period): void
    {
        if (!in_array($period, self::PERIODS, true)) {
            throw new \InvalidArgumentException("Unknown period '$period'");
        }
        $this->unsubscribe($pageTag, $userName);
        $this->tripleStore->create($pageTag, self::PROPERTY_PREFIX . $period, $userName, '', '');
    }

    /** Removes $userName from every period of $pageTag. */
    public function unsubscribe(string $pageTag, string $userName): void
    {
        foreach (self::PERIODS as $period) {
            $this->tripleStore->delete($pageTag, self::PROPERTY_PREFIX . $period, $userName, '', '');
        }
    }

    /** Removes every subscription of $userName, as when their account is deleted. */
    public function forgetUser(string $userName): void
    {
        foreach (self::PERIODS as $period) {
            foreach ($this->tripleStore->getMatching(null, self::PROPERTY_PREFIX . $period, $userName, '=', '=', '=') as $triple) {
                $this->tripleStore->delete($triple['resource'], self::PROPERTY_PREFIX . $period, $userName, '', '');
            }
        }
    }

    /** The subscribers of each page at $period, as [pageTag => [userName, ...]]. */
    public function subscribersAt(string $period): array
    {
        $subscribers = [];
        foreach ($this->tripleStore->getMatching(null, self::PROPERTY_PREFIX . $period, null, '=', '=', '=') as $triple) {
            $subscribers[$triple['resource']][] = $triple['value'];
        }

        return $subscribers;
    }

    /** Whether a page is being rendered for a subscription mail, which must not carry the subscription buttons. */
    public function isSending(): bool
    {
        return self::$sending;
    }

    /** Mails every page with subscribers at $period to them; returns the number of mails sent. */
    public function send(string $period, string $subject = ''): int
    {
        if (!in_array($period, self::PERIODS, true)) {
            throw new \InvalidArgumentException("Unknown period '$period'");
        }
        require_once 'tools/contact/libs/contact.functions.php';
        $report = _t(['day' => 'CONTACT_DAILY_REPORT', 'week' => 'CONTACT_WEEKLY_REPORT', 'month' => 'CONTACT_MONTHLY_REPORT'][$period]);
        $header = '[' . str_replace(['/wakka.php?wiki=', 'http://', 'https://', '/?'], '', $this->wiki->config['base_url']) . ']';
        $sent = 0;
        foreach ($this->subscribersAt($period) as $pageTag => $userNames) {
            $page = $this->pageManager->getOne($pageTag);
            if (empty($page)) {
                continue;
            }
            self::$sending = true;
            try {
                $html = $this->wiki->Format('{{include page="' . $page['tag'] . '"}}');
            } finally {
                self::$sending = false;
            }
            $text = nl2br(strip_tags($html));
            $pageSubject = empty($subject) ? $header . ' ' . getPageTitle($page) . ' (' . $report . ' ' . date('d.m.Y') . ')' : $subject;
            foreach ($userNames as $userName) {
                $user = $this->userManager->getOneByName($userName);
                if (!empty($user['email']) && $this->deliver($user['email'], $pageSubject, $text, $html)) {
                    $sent++;
                }
            }
        }

        return $sent;
    }

    protected function deliver(string $email, string $subject, string $text, string $html): bool
    {
        $sender = $this->wiki->config['BAZ_ADRESSE_MAIL_ADMIN'];

        return send_mail($sender, $sender, $email, $subject, $text, $html);
    }
}
