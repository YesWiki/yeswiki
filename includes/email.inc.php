<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

// sends a mail through the transport set in the contact_* config, to a lone recipient in To or in batches of bcc recipients
function send_mail($mail_sender, $name_sender, $mail_receiver, $subject, $message_txt, $message_html = '', bool $keepSender = false)
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
        $mail_sender = mailSenderAddress($mail_sender, $GLOBALS['wiki']->config, $keepSender);
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
            if ($keepSender && deliverToMailServer($mail, reset($mail_receiver))) {
                return true;
            }
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
            echo mailFailureMessage($mail, $e);
        }

        return false;
    }
}

/** Hands $mail to the recipient domain's own servers with an empty envelope, for a sender no authenticated SMTP relay would accept; returns whether one took it. */
function deliverToMailServer(PHPMailer $mail, string $recipient, ?array $servers = null, int $port = 25): bool
{
    $mail->preSend();
    foreach ($servers ?? mailServersOf(substr((string)strrchr($recipient, '@'), 1)) as $server) {
        $smtp = new SMTP();
        $smtp->Timeout = 10;
        $accepted = $smtp->connect($server, $port, 10, ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
            && $smtp->hello($mail->Hostname)
            && (!$smtp->getServerExt('STARTTLS') || ($smtp->startTLS() && $smtp->hello($mail->Hostname)))
            && $smtp->mail('')
            && $smtp->recipient($recipient)
            && $smtp->data($mail->getSentMIMEMessage());
        $smtp->quit();
        $smtp->close();
        if ($accepted) {
            return true;
        }
    }

    return false;
}

/** The mail servers of $domain, preferred first. */
function mailServersOf(string $domain): array
{
    $servers = [];
    $weights = [];
    if ($domain === '' || !getmxrr($domain, $servers, $weights)) {
        return $domain === '' ? [] : [$domain];
    }
    array_multisort($weights, SORT_ASC, SORT_NUMERIC, $servers);

    return $servers;
}

/** What went wrong with a send, with the SMTP server's own words when PHPMailer left them out. */
function mailFailureMessage(PHPMailer $mail, Exception $e): string
{
    $message = $e->errorMessage();
    $reply = trim($mail->getSMTPInstance()->getError()['detail'] ?? '');

    return $reply === '' || str_contains($message, $reply) ? $message : $message . ' ' . htmlspecialchars($reply);
}

/** The address a mail goes out from: the wiki's contact_from when set, unless the recipient acts on the sender's own address, as a mailing list subscribing it does. */
function mailSenderAddress(string $mailSender, $config, bool $keepSender = false): string
{
    return !$keepSender && !empty($config['contact_from']) ? $config['contact_from'] : $mailSender;
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
    $mail->DKIM_identity = str_ends_with(strtolower($mail->From), '@' . strtolower($domain)) ? $mail->From : '';

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
