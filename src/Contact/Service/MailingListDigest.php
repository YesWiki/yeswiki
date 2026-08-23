<?php

namespace YesWiki\Contact\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Service\PageSummary;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\Mailer;

/** The daily, weekly and monthly digests, sent to the groups that asked for them. */
class MailingListDigest
{
    public function __construct(private readonly ContainerInterface $services)
    {
    }

    /** Send the digest for $period to every group that subscribes to one. */
    public function sendForPeriod(string $period = '', string $subject = ''): void
    {
        $groups = $this->services->get(\YesWiki\Identity\Service\GroupOperationsService::class)->getAll();
        $groups = array_filter($groups, static fn (string $name): bool => str_starts_with($name, 'Mail'));

        if ($period == 'day') {
            $dayGroups = array_filter($groups, static fn (string $name): bool => str_ends_with($name, 'Day'));
            $this->sendTo('day', $dayGroups, $subject);
        }

        if ($period == 'week') {
            $weekGroups = array_filter($groups, static fn (string $name): bool => str_ends_with($name, 'Week'));
            $this->sendTo('week', $weekGroups, $subject);
        }

        if ($period == 'month') {
            $monthGroups = array_filter($groups, static fn (string $name): bool => str_ends_with($name, 'Month'));
            $this->sendTo('month', $monthGroups, $subject);
        }
    }

    /**
     * One period's digest, to the groups named for it.
     *
     * @param array<string> $groups
     */
    private function sendTo(string $period, array $groups, string $subject = ''): void
    {
        $sub = '';
        if ($period == 'day') {
            $sub = _t('CONTACT_DAILY_REPORT');
        } elseif ($period == 'week') {
            $sub = _t('CONTACT_WEEKLY_REPORT');
        } elseif ($period == 'month') {
            $sub = _t('CONTACT_MONTHLY_REPORT');
        }

        $mailer = $this->services->get(Mailer::class);

        foreach ($groups as $group) {
            $page = preg_replace(['/^Mail/', '/' . ucfirst($period) . '$/'], '', $group);
            $_GET['period'] = $period;
            $page = $this->services->get(\YesWiki\Content\Service\PageManager::class)->getOne($page);

            $groupmembers = $this->services->get(\YesWiki\Identity\Service\GroupOperationsService::class)->getMembersText($group);
            $groupmembers = explode("\n", $groupmembers);
            $groupmembers = array_map('trim', $groupmembers);

            $mailheader = '[' . str_replace(['http://', 'https://', '/?'], '', $this->services->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['base_url']) . ']';
            if (empty($subject)) {
                $subject = $mailheader . ' ' . $this->services->get(PageSummary::class)->title($page) . ' (' . $sub . ' ' . date('d.m.Y') . ')';
            }
            $messageHtml = $this->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->format('{{include page="' . $page['tag'] . '"}}');
            $messageHtml = preg_replace(
                '/(\<\!\-\- mailperiod start \-\-\>.*\<\!\-\- mailperiod end \-\-\>)/Uims',
                '',
                $messageHtml
            );
            $messageTxt = nl2br(strip_tags($messageHtml));
            foreach ($groupmembers as $member) {
                $user = $this->services->get(UserManager::class)->getOneByName($member);
                if (!empty($user['email'])) {
                    $mailer->send($this->services->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_ADRESSE_MAIL_ADMIN'], $this->services->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_ADRESSE_MAIL_ADMIN'], $user['email'], $subject, $messageTxt, $messageHtml);
                }
            }
        }
    }
}
