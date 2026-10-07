<?php

namespace YesWiki\Contact\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\PageSummary;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Mailer;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Render\Service\MarkdownFormatterService;

/** Mail subscriptions to pages, stored as triples. */
class MailSubscriptions implements EventSubscriberInterface
{
    public const PERIODS = ['day', 'week', 'month'];
    public const PROPERTY_PREFIX = 'https://yeswiki.net/vocabulary/mailSubscription/';

    private const REPORTS = ['day' => 'CONTACT_DAILY_REPORT', 'week' => 'CONTACT_WEEKLY_REPORT', 'month' => 'CONTACT_MONTHLY_REPORT'];

    private static bool $sending = false;

    public function __construct(
        private readonly TripleStore $tripleStore,
        private readonly DbService $db,
        private readonly ContainerInterface $services,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return ['user.deleted' => 'onUserDeleted'];
    }

    public function onUserDeleted(Event $event): void
    {
        $name = $event->getData()['name'] ?? '';
        if (is_string($name) && $name !== '') {
            $this->forgetUser($name);
        }
    }

    /** The period a user receives a page at, or null. */
    public function periodOf(string $pageTag, string $userName): ?string
    {
        foreach ($this->rows('resource = ? AND property LIKE ? AND value = ?', $pageTag, self::PROPERTY_PREFIX . '%', $userName) as $row) {
            $period = substr((string)$row['property'], strlen(self::PROPERTY_PREFIX));
            if (in_array($period, self::PERIODS, true)) {
                return $period;
            }
        }

        return null;
    }

    /** Subscribes a user to a page at a period. */
    public function subscribe(string $pageTag, string $userName, string $period): void
    {
        if (!in_array($period, self::PERIODS, true)) {
            throw new \InvalidArgumentException("Unknown period '$period'");
        }
        $this->unsubscribe($pageTag, $userName);
        $this->tripleStore->create($pageTag, self::PROPERTY_PREFIX . $period, $userName, '', '');
    }

    /** Unsubscribes a user from a page. */
    public function unsubscribe(string $pageTag, string $userName): void
    {
        foreach (self::PERIODS as $period) {
            $this->tripleStore->delete($pageTag, self::PROPERTY_PREFIX . $period, $userName, '', '');
        }
    }

    /** Removes every subscription of a user. */
    public function forgetUser(string $userName): void
    {
        foreach ($this->rows('property LIKE ? AND value = ?', self::PROPERTY_PREFIX . '%', $userName) as $row) {
            $this->tripleStore->delete((string)$row['resource'], (string)$row['property'], $userName, '', '');
        }
    }

    /** @return array<string, list<string>> the user names subscribed to each page at $period */
    public function subscribersAt(string $period): array
    {
        $subscribers = [];
        foreach ($this->rows('property = ?', self::PROPERTY_PREFIX . $period) as $row) {
            $subscribers[(string)$row['resource']][] = (string)$row['value'];
        }

        return $subscribers;
    }

    /** Whether anybody is subscribed to anything. */
    public function hasAny(): bool
    {
        return $this->db->loadSingle(
            'SELECT id FROM ' . $this->db->prefixTable('triples') . ' WHERE property LIKE ? LIMIT 1',
            [self::PROPERTY_PREFIX . '%']
        ) !== null;
    }

    /** @return list<array<string, mixed>> subscription triples read from the table, not TripleStore's memo, which a worker keeps across requests */
    private function rows(string $where, string ...$params): array
    {
        return $this->db->loadAll('SELECT resource, property, value FROM ' . $this->db->prefixTable('triples') . ' WHERE ' . $where . ' ORDER BY id', $params);
    }

    /** Whether a subscription mail is being rendered. */
    public function isSending(): bool
    {
        return self::$sending;
    }

    /** Sends the pages subscribed at a period; returns the number of mails. */
    public function send(string $period, string $subject = ''): int
    {
        if (!in_array($period, self::PERIODS, true)) {
            throw new \InvalidArgumentException("Unknown period '$period'");
        }
        $config = $this->services->get(RuntimeConfig::class);
        $header = '[' . str_replace(['http://', 'https://', '/?'], '', (string)$config['base_url']) . ']';
        $sent = 0;
        foreach ($this->subscribersAt($period) as $pageTag => $userNames) {
            $page = $this->services->get(PageManager::class)->getOne($pageTag);
            if (empty($page)) {
                continue;
            }
            self::$sending = true;
            try {
                $html = $this->services->get(MarkdownFormatterService::class)->format('{{include page="' . $page['tag'] . '"}}');
            } finally {
                self::$sending = false;
            }
            $text = nl2br(strip_tags($html));
            $pageSubject = $subject !== ''
                ? $subject
                : $header . ' ' . $this->services->get(PageSummary::class)->title($page) . ' (' . _t(self::REPORTS[$period]) . ' ' . date('d.m.Y') . ')';
            foreach ($userNames as $userName) {
                $user = $this->services->get(UserManager::class)->getOneByName($userName);
                if (!empty($user['email']) && $this->deliver((string)$user['email'], $pageSubject, $text, $html)) {
                    $sent++;
                }
            }
        }

        return $sent;
    }

    protected function deliver(string $email, string $subject, string $text, string $html): bool
    {
        $sender = (string)$this->services->get(RuntimeConfig::class)['BAZ_ADRESSE_MAIL_ADMIN'];

        return $this->services->get(Mailer::class)->send($sender, $sender, $email, $subject, $text, $html);
    }
}
