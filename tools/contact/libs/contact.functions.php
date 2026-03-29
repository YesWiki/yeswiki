<?php

use YesWiki\Core\Service\GroupManager;
use YesWiki\Core\Service\UserManager;

include_once 'includes/email.inc.php';

function FindMailFromWikiPage($wikipage, $nbactionmail)
{
    preg_match_all('/{{(contact|abonnement|desabonnement).*mail=\"(.*)\".*}}/U', $wikipage, $matches);

    return $matches[2][$nbactionmail - 1];
}

function parseMails($emails)
{
    $mailList = [];
    $groupManager = $GLOBALS['wiki']->services->get(GroupManager::class);
    $userManager = $GLOBALS['wiki']->services->get(UserManager::class);
    foreach ($emails as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mailList[$email] = $email; // using email as key to avoid duplicates
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

    // Check sender's name
    if ($type == 'contact' && !$name_sender) {
        $message['message'] .= _t('CONTACT_ENTER_NAME') . '<br />';
    }

    // Check sender's email
    if (!$mail_sender) {
        $message['message'] .= _t('CONTACT_ENTER_SENDER_MAIL') . '<br />';
    }
    if ($mail_sender && !ValidateEmail($mail_sender)) {
        $message['message'] .= _t('CONTACT_SENDER_MAIL_INVALID') . '<br />';
    }

    // Check the receiver's email
    if (!$mail_receiver) {
        $message['message'] .= _t('CONTACT_ENTER_RECEIVER_MAIL') . '<br />';
    }
    if (!is_array($mail_receiver) || count($mail_receiver) < 1) {
        $message['message'] .= _t('CONTACT_RECEIVER_MAIL_INVALID') . '<br />';
    }

    // Check message (length)
    if ($type != 'abonnement' && $type != 'desabonnement' && (!$messagebody || strlen($messagebody) < 10)) {
        $message['message'] .= _t('CONTACT_ENTER_MESSAGE') . '<br />';
    }

    // If no errors, we inform of success!
    if ($message['message'] == '') {
        $message['class'] = 'success';
    }

    return $message;
}

function getPageTitle($page)
{
    // on recupere les bf_titre ou les titres de niveau 1 et de niveau 2, on met la PageWiki sinon
    preg_match_all('/"bf_titre":"(.*)"/U', $page['body'], $titles);
    if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0] != '') {
        $title = _convert(preg_replace_callback('/\\\\u([a-f0-9]{4})/', 'utf8_special_decode', $titles[1][0]), 'UTF-8');
    // preg_replace("/\\\\u([a-f0-9]{4})/e", "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))", $titles[1][0]));
    } else {
        preg_match_all("/\={6}(.*)\={6}/U", $page['body'], $titles);
        if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0] != '') {
            $title = $GLOBALS['wiki']->Format(_convert(trim($titles[1][0]), 'ISO-8859-15'));
        } else {
            preg_match_all('/={5}(.*)={5}/U', $page['body'], $titles);
            if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0] != '') {
                $title = $GLOBALS['wiki']->Format(_convert(trim($titles[1][0]), 'ISO-8859-15'));
            } else {
                $title = $page['tag'];
            }
        }
    }

    return strip_tags($title);
}

function filterMailGroups($var)
{
    // returns all string starting with "Mail"
    return preg_match('/^Mail/', $var);
}

function filterDailyMailGroups($var)
{
    // returns all string ending with "Day"
    return preg_match('/Day$/', $var);
}

function filterWeeklyMailGroups($var)
{
    // returns all string ending with "Week"
    return preg_match('/Week$/', $var);
}

function filterMonthlyMailGroups($var)
{
    // returns all string ending with "Month"
    return preg_match('/Month$/', $var);
}

function sendPeriodicalMailToGroup($period, $groups, $subject = '')
{
    if ($period == 'day') {
        $sub = _t('CONTACT_DAILY_REPORT');
    } elseif ($period == 'week') {
        $sub = _t('CONTACT_WEEKLY_REPORT');
    } elseif ($period == 'month') {
        $sub = _t('CONTACT_MONTHLY_REPORT');
    }

    foreach ($groups as $group) {
        // get page name
        $page = preg_replace(['/^Mail/', '/' . ucfirst($period) . '$/'], '', $group);
        $_GET['period'] = $period;
        $page = $GLOBALS['wiki']->LoadPage($page);

        // send emails to all group members
        $groupmembers = $GLOBALS['wiki']->GetGroupACL($group);
        $groupmembers = explode("\n", $groupmembers);
        $groupmembers = array_map('trim', $groupmembers);

        $mailheader = '[' . str_replace(['/wakka.php?wiki=', 'http://', 'https://', '/?'], '', $GLOBALS['wiki']->config['base_url']) . ']';
        if (empty($subject)) {
            $subject = $mailheader . ' ' . getPageTitle($page) . ' (' . $sub . ' ' . date('d.m.Y') . ')';
        }
        $message_html = $GLOBALS['wiki']->Format('{{include page="' . $page['tag'] . '"}}');
        $message_html = preg_replace(
            '/(\<\!\-\- mailperiod start \-\-\>.*\<\!\-\- mailperiod end \-\-\>)/Uims',
            '',
            $message_html
        );
        $message_txt = nl2br(strip_tags($message_html));
        foreach ($groupmembers as $member) {
            $user = $GLOBALS['wiki']->LoadUser($member);
            if (!empty($user['email'])) {
                send_mail($GLOBALS['wiki']->config['BAZ_ADRESSE_MAIL_ADMIN'], $GLOBALS['wiki']->config['BAZ_ADRESSE_MAIL_ADMIN'], $user['email'], $subject, $message_txt, $message_html);
            }
        }
    }
}

function sendEmailsToSubscribers($period = '', $subject = '')
{
    // on recupere tous les groupes et on les trie par periode
    $groups = $GLOBALS['wiki']->GetGroupsList();
    $groups = array_filter($groups, 'filterMailGroups');

    // envois journaliers
    if ($period == 'day') {
        $dayGroups = array_filter($groups, 'filterDailyMailGroups');
        sendPeriodicalMailToGroup('day', $dayGroups, $subject);
    }

    // envois hebdomadaires
    if ($period == 'week') {
        $weekGroups = array_filter($groups, 'filterWeeklyMailGroups');
        sendPeriodicalMailToGroup('week', $weekGroups, $subject);
    }

    // envois mensuels
    if ($period == 'month') {
        $monthGroups = array_filter($groups, 'filterMonthlyMailGroups');
        sendPeriodicalMailToGroup('month', $monthGroups, $subject);
    }
}
