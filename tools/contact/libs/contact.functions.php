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
    require_once('tools/contact/libs/vendor/PHPMailer/PHPMailerAutoload.php');
    //Create a new PHPMailer instance
    $mail = new PHPMailer;
    $mail->CharSet = 'UTF-8';
    if ($GLOBALS['wiki']->config['contact_mail_func'] == 'smtp') {
        //Tell PHPMailer to use SMTP
        $mail->isSMTP();

        //Enable SMTP debugging
        // 0 = off (for production use)
        // 1 = client messages
        // 2 = client and server messages
        $mail->SMTPDebug = 0;
        //Ask for HTML-friendly debug output
        $mail->Debugoutput = 'html';
        //Set the hostname of the mail server
        $mail->Host = $GLOBALS['wiki']->config['contact_smtp_host'];
        //Set the SMTP port number - likely to be 25, 465 or 587
        $mail->Port = $GLOBALS['wiki']->config['contact_smtp_port'];
        //Whether to use SMTP authentication
        $mail->SMTPAuth = true;
        //Username to use for SMTP authentication
        $mail->Username = $GLOBALS['wiki']->config['contact_smtp_user'];
        //Password to use for SMTP authentication
        $mail->Password = $GLOBALS['wiki']->config['contact_smtp_pass'];
    } elseif ($GLOBALS['wiki']->config['contact_mail_func'] == 'sendmail') {
        // Set PHPMailer to use the sendmail transport
        $mail->isSendmail();
    }

    //Set who the message is to be sent from
    if (empty($name_sender)) {
        $name_sender = $mail_sender;
    }
    $mail->setFrom($mail_sender, $name_sender);
    //Set an alternative reply-to address
    $mail->addReplyTo($mail_sender, $name_sender);
    //Set who the message is to be sent to
    $mail->addAddress($mail_receiver, $mail_receiver);
    //Set the subject line
    $mail->Subject = $subject;
    //convert HTML into a basic plain-text alternative body
    $mail->isHTML(true);
    if (empty($message_txt)) {
        $message_txt = 'empty email body';
    }
    if (empty($message_html)) {
        $message_html = nl2br($message_txt);
    }
    if (!empty($GLOBALS['wiki']->config['mail_template'])) {
        // affichage des resultats
        include_once 'tools/libs/squelettephp.class.php';
        // On cherche un template personnalise dans le repertoire themes/tools
        $templatetoload = 'themes/tools/contact/templates/'.$GLOBALS['wiki']->config['mail_template'];
        if (!is_file($templatetoload)) {
            if (is_file('tools/contact/presentation/templates/'.$GLOBALS['wiki']->config['mail_template'])) {
                $templatetoload = 'tools/contact/presentation/templates/'.$GLOBALS['wiki']->config['mail_template'];
            } else {
                exit('<div class="alert alert-danger">'._t('CONTACT_TEMPLATE_NOT_FOUND').' : '.$GLOBALS['wiki']->config['mail_template'].'</div>');
            }
        }
        $squel = new SquelettePhp($templatetoload);
        $squel->set(array('message' => $message_html));
        $message_html = $squel->analyser();
    }
    $mail->Body = $message_html;
    //Replace the plain text body with one created manually
    $mail->AltBody = $message_txt;
    //Attach an image file
    //$mail->addAttachment('images/phpmailer_mini.png');
    //send the message, check for errors
    if (!$mail->send()) {
        echo "Mailer Error: " . $mail->ErrorInfo;
        return false;
    } else {
        return true;
    }
}
