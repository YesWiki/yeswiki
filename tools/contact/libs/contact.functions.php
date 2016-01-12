<?php

function FindMailFromWikiPage($wikipage, $nbactionmail)
{
    preg_match_all('/{{(contact|abonnement|desabonnement).*mail=\"(.*)\".*}}/U', $wikipage, $matches);
    return $matches[2][$nbactionmail - 1];
}

function ValidateEmail($email)
{
    $regex = "/([a-z0-9_\.\-]+)" . # name
    "@" . # at
    "([a-z0-9\.\-]+){2,255}" . # domain & possibly subdomains
    "\." . # period
    "([a-z]+){2,10}/i"; # domain extension
    $eregi = preg_replace($regex, '', $email);
    return empty($eregi) ? true : false;
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
    if ($mail_receiver && !ValidateEmail($mail_receiver)) {
        $message['message'] .= _t('CONTACT_RECEIVER_MAIL_INVALID') . '<br />';
    }

    // Check message (length)
    if ($type == 'contact' && (!$messagebody || strlen($messagebody) < 10)) {
        $message['message'] .= _t('CONTACT_ENTER_MESSAGE') . '<br />';
    }

    // If no errors, we inform of success!
    if ($message['message'] == '') {
        $message['class'] = 'success';
    }

    return $message;
}

function send_mail($mail_sender, $name_sender, $mail_receiver, $subject, $message_txt, $message_html = '')
{
    require_once 'tools/contact/libs/Mail.php';
    require_once 'tools/contact/libs/Mail/mime.php';
    $headers['From'] = $mail_sender;
    $headers['To'] = $mail_sender;
    $headers['Subject'] = $subject;
    $headers["Return-path"] = $mail_sender;

    if ($message_html == '') {
        $message_html == $message_txt;
    }
    $mime = new Mail_mime("\n");

    $mimeparams = array();
    $mimeparams['text_encoding'] = "7bit";
    $mimeparams['text_charset'] = "UTF-8";
    $mimeparams['html_charset'] = "UTF-8";
    $mimeparams['head_charset'] = "UTF-8";

    $mime->setTXTBody($message_txt);
    $mime->setHTMLBody($message_html);
    $message = $mime->get($mimeparams);
    $headers = $mime->headers($headers);

    // Creer un objet mail en utilisant la methode Mail::factory.
    $object_mail = &Mail::factory(CONTACT_MAIL_FACTORY);

    return $object_mail->send($mail_receiver, $headers, $message);
}
