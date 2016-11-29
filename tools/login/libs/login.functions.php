<?php

function loadUserbyEmail($email, $password = 0)
{
    global $wiki;
    return $wiki->LoadSingle(
        "select * from ".$wiki->getUserTablePrefix() . "users where email = '".mysqli_real_escape_string($wiki->dblink, $email).
        "' " . ($password === 0 ? "" : "and password = '" . mysqli_real_escape_string($wiki->dblink, $password) . "'") . " limit 1"
    );
}

function checkUNEmail($email)
{
    global $wiki;
    $error = array (
        'status' => false,
        'userID' => 0
    );
    if (isset($email) && trim($email) != '') {
        // email was entered
        $existingEmail = $wiki->LoadSingle('select * from ' . $wiki->getUserTablePrefix() . 'users where email = "' . mysqli_real_escape_string($wiki->dblink, $email) . '" limit 1');
        if ($existingEmail) {
            return array (
                'status' => true,
                'userID' => $existingEmail ['name']
            );
        } else {
            return $error;
        }
    } else {
        // nothing was entered;
        return $error;
    }
}

function sendPasswordEmail($userID)
{
    global $wiki;
    if ($existingUser = $wiki->LoadUser($userID)) {
        $key = md5($userID . '_' . $existingUser['email'] . rand(0, 10000) . date('Y-m-d H:i:s') . PW_SALT);
        $res = $wiki->InsertTriple($userID, 'http://outils-reseaux.org/_vocabulary/key', $key);
        $passwordLink = $wiki->Href() . '&a=recover&email=' . $key . '&u=' . urlencode(base64_encode($userID));

        $pieces = parse_url($GLOBALS['wiki']->GetConfigValue('base_url'));
        $domain = isset($pieces['host']) ? $pieces['host'] : '';

        $message = _t('LOGIN_DEAR').' ' . $userID . ",\n";
        $message .= _t('LOGIN_CLICK_FOLLOWING_LINK').' :' . "\n";
        $message .= '-----------------------' . "\n";
        $message .= $passwordLink . "\n";
        $message .= '-----------------------' . "\n";
        $message .= _t('LOGIN_THE_TEAM').' ' . $domain . "\n";

        $subject = _t('LOGIN_PASSWORD_LOST_FOR').' ' . $domain;

        if (!function_exists('send_mail')) {
            require_once('includes/email.inc.php');
        }
        send_mail($GLOBALS['wiki']->GetConfigValue('email_from', 'noreply@' . $domain), 'WikiAdmin', $existingUser['email'], $subject, $message);
    }
}

function checkEmailKey($key, $userID)
{
    global $wiki;
    // Pas de detournement possible car utilisation de _vocabulary/key ....
    $res = $wiki->TripleExists($userID, 'http://outils-reseaux.org/_vocabulary/key', $key);
    if ($res > 0) {
        return array (
            'status' => true,
            'userID' => $userID
        );
    } else {
        return false;
    }
}

function updateUserPassword($userID, $password, $key)
{
    global $wiki;
    if (checkEmailKey($key, $userID) === false) {
        return false;
    }

    $wiki->Query("update " . $wiki->getUserTablePrefix() . "users " . "set " . "password = '" . MD5($password) . "' " . "where name = '" . $userID . "' limit 1");

    $res = $wiki->DeleteTriple($userID, 'http://outils-reseaux.org/_vocabulary/key', $key);
    return true;
}
