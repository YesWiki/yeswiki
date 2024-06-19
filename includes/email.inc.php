<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * @param string $mail_sender
 * @param string $name_sender
 * @param string $mail_receiver
 * @param string $subject
 * @param string $message_txt
 * @param string $message_html
 */
function send_mail($mail_sender, $name_sender, $mail_receiver, $subject, $message_txt, $message_html = '')
{
    //Create a new PHPMailer instance
    $mail = new PHPMailer(true);

    try {
        $mail->set('CharSet', 'utf-8');

        if ($GLOBALS['wiki']->config['contact_mail_func'] == 'smtp') {
            //Tell PHPMailer to use SMTP
            $mail->isSMTP();
            //Enable SMTP debugging
            // 0 = off (for production use)
            // 1 = client messages
            // 2 = client and server messages
            $mail->SMTPDebug = $GLOBALS['wiki']->config['contact_debug'];
            //Ask for HTML-friendly debug output
            $mail->Debugoutput = 'html';
            //Set the hostname of the mail server
            $mail->Host = $GLOBALS['wiki']->config['contact_smtp_host'];
            //Set the SMTP port number - likely to be 25, 465 or 587
            $mail->Port = $GLOBALS['wiki']->config['contact_smtp_port'];
            //Whether to use SMTP authentication
            if (!empty($GLOBALS['wiki']->config['contact_smtp_user'])) {
                $mail->SMTPAuth = true;
                //Username to use for SMTP authentication
                $mail->Username = $GLOBALS['wiki']->config['contact_smtp_user'];
                //Password to use for SMTP authentication
                $mail->Password = $GLOBALS['wiki']->config['contact_smtp_pass'];
            } else {
                $mail->SMTPAuth = false;
            }
        } elseif ($GLOBALS['wiki']->config['contact_mail_func'] == 'sendmail') {
            // Set PHPMailer to use the sendmail transport
            $mail->isSendmail();
        }

        //Set an alternative reply-to address
        if (!empty($GLOBALS['wiki']->config['contact_reply_to'])) {
            $mail->addReplyTo($GLOBALS['wiki']->config['contact_reply_to']);
        } else {
            $mail->addReplyTo($mail_sender, $name_sender);
        }
        // Set always the same 'from' address (to avoid spam, it's a good practice to set the from field with an address from
        // the same domain than the sending mail server)
        if (!empty($GLOBALS['wiki']->config['contact_from'])) {
            $mail_sender = $GLOBALS['wiki']->config['contact_from'];
        }
        //Set who the message is to be sent from
        if (empty($name_sender)) {
            $name_sender = $mail_sender;
        }
        $mail->setFrom($mail_sender, $name_sender);

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
            $mail->Body = $message_txt;
        } else {
            $mail->isHTML(true);
            $mail->Body = $message_html;
            if (!empty($message_txt)) {
                $mail->AltBody = $message_txt;
            }
        }

        $mail->send();

        return true;
    } catch (Exception $e) {
        if ($GLOBALS['wiki']->UserIsAdmin()) {
            echo $e->errorMessage();
        }

        return false;
    }
}
