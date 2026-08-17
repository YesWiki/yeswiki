<?php

use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\Mailer;

function FindMailFromWikiPage($wikipage, $nbactionmail)
{
    preg_match_all('/{{(contact|subscribe|unsubscribe).*mail=\"(.*)\".*}}/U', $wikipage, $matches);

    return $matches[2][$nbactionmail - 1];
}

function parseMails($emails)
{
    $mailList = [];
    $groupManager = $GLOBALS['yeswikiServices']->get(GroupManager::class);
    $userManager = $GLOBALS['yeswikiServices']->get(UserManager::class);
    foreach ($emails as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mailList[$email] = $email;
        } elseif (preg_match('/^@[a-zA-Z0-9]+/m', $email)) {
            $group = substr($email, 1);
            if ($groupManager->groupExists($group)) {
                $users = $groupManager->getMembers($group);
                foreach ($users as $user) {
                    $em = $userManager->getOneByName($user)->getEmail();
                    if (filter_var($em, FILTER_VALIDATE_EMAIL)) {
                        $mailList[$em] = $em;
                    }
                }
            }
        }
    }

    return $mailList;
}

function ValidateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function check_parameters_mail($type, $mail_sender, $name_sender, $mail_receiver, $subject, $messagebody)
{
    $message['message'] = '';
    $message['class'] = 'danger';

    if ($type == 'contact' && !$name_sender) {
        $message['message'] .= _t('CONTACT_ENTER_NAME') . '<br />';
    }

    if (!$mail_sender) {
        $message['message'] .= _t('CONTACT_ENTER_SENDER_MAIL') . '<br />';
    }
    if ($mail_sender && !ValidateEmail($mail_sender)) {
        $message['message'] .= _t('CONTACT_SENDER_MAIL_INVALID') . '<br />';
    }

    if (!$mail_receiver) {
        $message['message'] .= _t('CONTACT_ENTER_RECEIVER_MAIL') . '<br />';
    }
    if (is_string($mail_receiver)) {
        $mail_receiver = [$mail_receiver];
    }
    if (!is_array($mail_receiver) || count($mail_receiver) < 1) {
        $message['message'] .= _t('CONTACT_RECEIVER_MAIL_INVALID') . '<br />';
    }

    if ($type != 'subscribe' && $type != 'unsubscribe' && (!$messagebody || strlen($messagebody) < 10)) {
        $message['message'] .= _t('CONTACT_ENTER_MESSAGE') . '<br />';
    }

    if ($message['message'] == '') {
        $message['class'] = 'success';
    }

    return $message;
}

function getPageTitle($page)
{
    $body = $page['body'] ?? [];
    $content = YesWiki\Content\Entity\PageBody::content($body);

    $entryTitle = $body[YesWiki\Content\Entity\PageBody::TITLE] ?? $body['bf_titre'] ?? '';
    if ($entryTitle != '') {
        $title = $entryTitle;
    } else {
        preg_match_all("/\={6}(.*)\={6}/U", $content, $titles);
        if (isset($titles[1][0]) && $titles[1][0] != '') {
            $title = $GLOBALS['yeswikiServices']->get(YesWiki\Render\Service\MarkdownFormatterService::class)->format(trim($titles[1][0]));
        } else {
            preg_match_all('/={5}(.*)={5}/U', $content, $titles);
            if (isset($titles[1][0]) && $titles[1][0] != '') {
                $title = $GLOBALS['yeswikiServices']->get(YesWiki\Render\Service\MarkdownFormatterService::class)->format(trim($titles[1][0]));
            } else {
                $title = $page['tag'];
            }
        }
    }

    return strip_tags($title);
}

function filterMailGroups($var)
{
    return preg_match('/^Mail/', $var);
}

function filterDailyMailGroups($var)
{
    return preg_match('/Day$/', $var);
}

function filterWeeklyMailGroups($var)
{
    return preg_match('/Week$/', $var);
}

function filterMonthlyMailGroups($var)
{
    return preg_match('/Month$/', $var);
}

function sendPeriodicalMailToGroup($period, $groups, $subject = '')
{
    $sub = '';
    if ($period == 'day') {
        $sub = _t('CONTACT_DAILY_REPORT');
    } elseif ($period == 'week') {
        $sub = _t('CONTACT_WEEKLY_REPORT');
    } elseif ($period == 'month') {
        $sub = _t('CONTACT_MONTHLY_REPORT');
    }

    $mailer = $GLOBALS['yeswikiServices']->get(Mailer::class);

    foreach ($groups as $group) {
        $page = preg_replace(['/^Mail/', '/' . ucfirst($period) . '$/'], '', $group);
        $_GET['period'] = $period;
        $page = $GLOBALS['yeswikiServices']->get(YesWiki\Content\Service\PageManager::class)->getOne($page);

        $groupmembers = $GLOBALS['yeswikiServices']->get(YesWiki\Identity\Service\GroupOperationsService::class)->getMembersText($group);
        $groupmembers = explode("\n", $groupmembers);
        $groupmembers = array_map('trim', $groupmembers);

        $mailheader = '[' . str_replace(['http://', 'https://', '/?'], '', $GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\RuntimeConfig::class)['base_url']) . ']';
        if (empty($subject)) {
            $subject = $mailheader . ' ' . getPageTitle($page) . ' (' . $sub . ' ' . date('d.m.Y') . ')';
        }
        $message_html = $GLOBALS['yeswikiServices']->get(YesWiki\Render\Service\MarkdownFormatterService::class)->format('{{include page="' . $page['tag'] . '"}}');
        $message_html = preg_replace(
            '/(\<\!\-\- mailperiod start \-\-\>.*\<\!\-\- mailperiod end \-\-\>)/Uims',
            '',
            $message_html
        );
        $message_txt = nl2br(strip_tags($message_html));
        foreach ($groupmembers as $member) {
            $user = $GLOBALS['yeswikiServices']->get(UserManager::class)->getOneByName($member);
            if (!empty($user['email'])) {
                $mailer->send($GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_ADRESSE_MAIL_ADMIN'], $GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_ADRESSE_MAIL_ADMIN'], $user['email'], $subject, $message_txt, $message_html);
            }
        }
    }
}

function sendEmailsToSubscribers($period = '', $subject = '')
{
    $groups = $GLOBALS['yeswikiServices']->get(YesWiki\Identity\Service\GroupOperationsService::class)->getAll();
    $groups = array_filter($groups, 'filterMailGroups');

    if ($period == 'day') {
        $dayGroups = array_filter($groups, 'filterDailyMailGroups');
        sendPeriodicalMailToGroup('day', $dayGroups, $subject);
    }

    if ($period == 'week') {
        $weekGroups = array_filter($groups, 'filterWeeklyMailGroups');
        sendPeriodicalMailToGroup('week', $weekGroups, $subject);
    }

    if ($period == 'month') {
        $monthGroups = array_filter($groups, 'filterMonthlyMailGroups');
        sendPeriodicalMailToGroup('month', $monthGroups, $subject);
    }
}
