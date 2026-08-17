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
    $batchSize = 10;

    $mail = new PHPMailer(true);

    try {
        $mail->set('CharSet', 'utf-8');

        if ($GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\RuntimeConfig::class)['contact_mail_func'] == 'smtp') {
            $mail->isSMTP();

            $mail->SMTPDebug = $GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\RuntimeConfig::class)['contact_debug'];

            $mail->Debugoutput = 'html';

            $mail->Host = $GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\RuntimeConfig::class)['contact_smtp_host'];

            $mail->Port = $GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\RuntimeConfig::class)['contact_smtp_port'];

            if (!empty($GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\RuntimeConfig::class)['contact_smtp_user'])) {
                $mail->SMTPAuth = true;

                $mail->Username = $GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\RuntimeConfig::class)['contact_smtp_user'];

                $mail->Password = $GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\RuntimeConfig::class)['contact_smtp_pass'];

                $vSMTPSecure = $GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\RuntimeConfig::class)['contact_smtp_secure'] ?? null;

                if (empty($vSMTPSecure)) {
                    $vSMTPSecure = getSMTPSecure($mail->Port ?? null);
                }

                switch ($vSMTPSecure) {
                    case 'ssl':
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        break;
                    case 'tls':
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        break;
                }
            } else {
                $mail->SMTPAuth = false;
            }
        } elseif ($GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\RuntimeConfig::class)['contact_mail_func'] == 'sendmail') {
            $mail->isSendmail();
        }

        if (!empty($GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\RuntimeConfig::class)['contact_reply_to'])) {
            $mail->addReplyTo($GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\RuntimeConfig::class)['contact_reply_to']);
        } else {
            $mail->addReplyTo($mail_sender, $name_sender);
        }

        if (!empty($GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\RuntimeConfig::class)['contact_from'])) {
            $mail_sender = $GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\RuntimeConfig::class)['contact_from'];
        }

        if (empty($name_sender)) {
            $name_sender = $mail_sender;
        }
        $mail->setFrom($mail_sender, $name_sender);

        $mail->Subject = $subject;

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

        if (!is_array($mail_receiver)) {
            $mailReceiver = [];
            if (filter_var($mail_receiver, FILTER_VALIDATE_EMAIL)) {
                $mailReceiver[] = $mail_receiver;
            }
            $mail_receiver = $mailReceiver;
        }

        $recipientBatches = array_chunk($mail_receiver, $batchSize);

        foreach ($recipientBatches as $batchIndex => $batch) {
            $mail->clearBCCs();

            foreach ($batch as $bccEmail) {
                $mail->addBCC($bccEmail);
            }

            $mail->send();

            sleep(1);
        }

        return true;
    } catch (Exception $e) {
        if ($GLOBALS['yeswikiServices']->get(YesWiki\Identity\Service\AclService::class)->isAdmin()) {
            echo $e->errorMessage();
        }

        return false;
    }
}

function getSMTPSecure($pPort = null)
{
    if (!empty($pPort)) {
        switch ($pPort) {
            case '465': return 'ssl';
            case '587': return 'tls';
        }
    }

    return '';
}
