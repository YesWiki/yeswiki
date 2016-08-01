<?php

/**
 *
 * @param string $mail_sender
 * @param string $name_sender
 * @param string $mail_receiver
 * @param string $subject
 * @param string $message_txt
 * @param string $message_html
 */
function send_mail($mail_sender, $name_sender, $mail_receiver, $subject, $message_txt, $message_html = '')
{
    require_once('vendor/PHPMailer/PHPMailerAutoload.php');
    //Create a new PHPMailer instance
    $mail = new PHPMailer;

    $mail->set('CharSet', 'utf-8');

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

    // That's bad if only text passed to function: Linebreaks won't be rendered.
    //if (empty($message_html)) {
    //  $message_html = $message_txt;
    //}

    if (empty($message_html)) {
        $mail->isHTML(false);
        $mail->Body = $message_txt ;
    } else {
        $mail->isHTML(true);
        $mail->Body = $message_html ;
        if (! empty($message_txt)) {
            $mail->AltBody = $message_txt;
        }
    }

    //Attach an image file
    //$mail->addAttachment('images/phpmailer_mini.png');

    //send the message, check for errors
    if (!$mail->send()) {
        // TODO: remove hardcoded html (eg. return the error message OR true)
        echo '<div class="alert alert-danger">Mailer Error: ' . $mail->ErrorInfo .'</div>';
        return false;
    } else {
        return true;
    }
}
