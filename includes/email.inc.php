<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

// sends a mail through the transport set in the contact_* config, to a lone recipient in To or in batches of bcc recipients
function send_mail($mail_sender, $name_sender, $mail_receiver, $subject, $message_txt, $message_html = '')
{
    $batchSize = 10;

    $mail = new PHPMailer(true);

    try {
        $mail->set('CharSet', 'utf-8');

        $wikiHost = parse_url($GLOBALS['wiki']->config['base_url'] ?? '', PHP_URL_HOST);
        if (!empty($wikiHost)) {
            $mail->Hostname = $wikiHost;
        }

        if ($GLOBALS['wiki']->config['contact_mail_func'] == 'smtp') {
            $mail->isSMTP();
            $mail->SMTPDebug = $GLOBALS['wiki']->config['contact_debug'];
            $mail->Debugoutput = 'html';
            $mail->Host = $GLOBALS['wiki']->config['contact_smtp_host'];
            $mail->Port = $GLOBALS['wiki']->config['contact_smtp_port'];
            if (!filter_var($GLOBALS['wiki']->config['contact_smtp_verify_peer'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }
            if (!empty($GLOBALS['wiki']->config['contact_smtp_user'])) {
                $mail->SMTPAuth = true;
                $mail->Username = $GLOBALS['wiki']->config['contact_smtp_user'];
                $mail->Password = $GLOBALS['wiki']->config['contact_smtp_pass'];

                $vSMTPSecure = $GLOBALS['wiki']->config['contact_smtp_secure'] ?? null;

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
        } elseif ($GLOBALS['wiki']->config['contact_mail_func'] == 'sendmail') {
            $mail->isSendmail();
        }

        if (!empty($GLOBALS['wiki']->config['contact_reply_to'])) {
            $mail->addReplyTo($GLOBALS['wiki']->config['contact_reply_to']);
        } else {
            $mail->addReplyTo($mail_sender, $name_sender);
        }
        if (!empty($GLOBALS['wiki']->config['contact_from'])) {
            $mail_sender = $GLOBALS['wiki']->config['contact_from'];
        }
        if (empty($name_sender)) {
            $name_sender = $mail_sender;
        }
        $mail->setFrom($mail_sender, $name_sender);
        signWithDkim($mail, $GLOBALS['wiki']->config);

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

        if (count($mail_receiver) === 1) {
            $mail->addAddress(reset($mail_receiver));
            $mail->send();

            return true;
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
        if ($GLOBALS['wiki']->UserIsAdmin()) {
            echo $e->errorMessage();
        }

        return false;
    }
}

/** Signs $mail with DKIM when the domain, the selector and a readable private key file are configured; returns whether it will be signed. */
function signWithDkim(PHPMailer $mail, $config): bool
{
    $domain = trim((string)($config['contact_dkim_domain'] ?? ''));
    $selector = trim((string)($config['contact_dkim_selector'] ?? ''));
    $keyFile = trim((string)($config['contact_dkim_private_key'] ?? ''));
    if ($domain === '' || $selector === '' || $keyFile === '' || !is_readable($keyFile)) {
        return false;
    }
    $mail->DKIM_domain = $domain;
    $mail->DKIM_selector = $selector;
    $mail->DKIM_private = $keyFile;
    $mail->DKIM_identity = $mail->From;

    return true;
}

// returns the last two labels of a host name, without www
function getMailDomain($pHost)
{
    $vHost = preg_replace('/^www\./', '', $pHost);
    $vParts = explode('.', $vHost);
    $vDomain = implode('.', array_slice($vParts, -2));

    return $vDomain;
}

// guesses the smtp encryption from the port
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
