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
    if (is_string($mail_receiver)) {
        $mail_receiver = [$mail_receiver];
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
