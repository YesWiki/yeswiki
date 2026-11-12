<?php

namespace YesWiki\Kernel\Service;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Files\Exception\StorageException;
use YesWiki\Files\Service\Storage;

/** Sends an email through the configured transport, and nothing about what is in it (ADR-0013). */
class Mailer
{
    /** How many recipients one message carries before the next batch. */
    private const BATCH_SIZE = 10;

    protected const DIRECT_PORT = 25;

    protected ContainerInterface $container;
    protected ParameterBagInterface $params;

    /** Why the last send failed, for a caller that has somewhere to show it. */
    private string $lastError = '';

    public function __construct(
        ContainerInterface $container,
        ParameterBagInterface $params
    ) {
        $this->container = $container;
        $this->params = $params;
    }

    /** The PHPMailer message from the last failed send, or nothing if the last one worked. */
    public function lastError(): string
    {
        return $this->lastError;
    }

    public function sendEmailFromAdmin(string $address, string $subject, string $text, string $html = ''): void
    {
        $this->send(
            $this->stringParam('BAZ_ADRESSE_MAIL_ADMIN'),
            $this->stringParam('BAZ_ADRESSE_MAIL_ADMIN'),
            $address,
            StringUtilService::withoutDiacritics($subject),
            $text,
            empty($html) ? $html : $this->sanitizeLinksIfNeeded($html)
        );
    }

    /**
     * Sends a mail to one or several recipients.
     *
     * @param string          $mailSender
     * @param string          $nameSender
     * @param string|string[] $mailReceiver
     */
    public function send($mailSender, $nameSender, $mailReceiver, string $subject, string $messageTxt, string $messageHtml = '', bool $keepSender = false): bool
    {
        $mail = $this->newMessage();

        try {
            $this->lastError = '';
            $mail->set('CharSet', 'utf-8');
            $host = parse_url($this->getBaseUrl(), PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $mail->Hostname = $host;
            }
            $this->configureTransport($mail);
            $this->configureSender($mail, $mailSender, $nameSender, $keepSender);

            $mail->Subject = $subject;
            if (empty($messageHtml)) {
                $mail->isHTML(false);
                $mail->Body = $messageTxt;
            } else {
                $mail->isHTML(true);
                $mail->Body = $messageHtml;
                if (!empty($messageTxt)) {
                    $mail->AltBody = $messageTxt;
                }
            }

            if (!is_array($mailReceiver)) {
                $mailReceiver = filter_var($mailReceiver, FILTER_VALIDATE_EMAIL) ? [$mailReceiver] : [];
            }

            if (count($mailReceiver) === 1) {
                $recipient = (string)reset($mailReceiver);
                $mail->addAddress($recipient);
                if ($keepSender && $this->deliverToMailServer($mail, $recipient)) {
                    return true;
                }
                $mail->send();

                return true;
            }

            foreach (array_chunk($mailReceiver, self::BATCH_SIZE) as $batch) {
                $mail->clearBCCs();
                foreach ($batch as $bccEmail) {
                    $mail->addBCC($bccEmail);
                }
                $mail->send();
                sleep(1);
            }

            return true;
        } catch (PHPMailerException $e) {
            $this->lastError = self::failureMessage($mail, $e);

            return false;
        }
    }

    /** @param list<string>|null $servers the servers to try, the recipient domain's mail exchangers when null; hands $mail over with an empty envelope, for a requester's address no authenticated relay would send */
    public function deliverToMailServer(PHPMailer $mail, string $recipient, ?array $servers = null, ?int $port = null): bool
    {
        $mail->preSend();
        foreach ($servers ?? $this->mailServersOf(substr((string)strrchr($recipient, '@'), 1)) as $server) {
            $smtp = new SMTP();
            $smtp->Timeout = 10;
            $steps = [
                fn (): bool => $smtp->connect($server, $port ?? static::DIRECT_PORT, 10, ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]),
                fn (): bool => $smtp->hello($mail->Hostname),
                fn (): bool => !$smtp->getServerExt('STARTTLS') || ($smtp->startTLS() && $smtp->hello($mail->Hostname)),
                fn (): bool => $smtp->mail(''),
                fn (): bool => $smtp->recipient($recipient),
                fn (): bool => $smtp->data($mail->getSentMIMEMessage()),
            ];
            $accepted = true;
            foreach ($steps as $step) {
                if (!$step()) {
                    $accepted = false;
                    break;
                }
            }
            $smtp->quit();
            $smtp->close();
            if ($accepted) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> the mail servers of $domain, preferred first, or the domain itself when it publishes none */
    protected function mailServersOf(string $domain): array
    {
        if ($domain === '') {
            return [];
        }
        $servers = [];
        $weights = [];
        if (!getmxrr($domain, $servers, $weights)) {
            return [$domain];
        }
        array_multisort($weights, SORT_ASC, SORT_NUMERIC, $servers);

        return $servers;
    }

    /** What went wrong with a send, with the SMTP server's own words when PHPMailer left them out. */
    private static function failureMessage(PHPMailer $mail, PHPMailerException $e): string
    {
        $message = $e->getMessage();
        $reply = trim((string)($mail->getSMTPInstance()->getError()['detail'] ?? ''));

        return $reply === '' || str_contains($message, $reply) ? $message : $message . ' ' . $reply;
    }

    /** A fresh message that throws on failure. */
    protected function newMessage(): PHPMailer
    {
        return new PHPMailer(true);
    }

    /** SMTP, sendmail or PHP's own mail(), as the wiki is configured. */
    private function configureTransport(PHPMailer $mail): void
    {
        $transport = $this->config()['contact_mail_func'] ?? '';

        if ($transport === 'sendmail') {
            $mail->isSendmail();

            return;
        }
        if ($transport !== 'smtp') {
            return;
        }

        $mail->isSMTP();
        $mail->SMTPDebug = $this->config()['contact_debug'];
        $mail->Debugoutput = 'html';
        $mail->Host = $this->config()['contact_smtp_host'];
        $mail->Port = $this->config()['contact_smtp_port'];
        if (!filter_var($this->config()['contact_smtp_verify_peer'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        if (empty($this->config()['contact_smtp_user'])) {
            $mail->SMTPAuth = false;

            return;
        }

        $mail->SMTPAuth = true;
        $mail->Username = $this->config()['contact_smtp_user'];
        $mail->Password = $this->config()['contact_smtp_pass'];

        $secure = $this->config()['contact_smtp_secure'] ?? null;
        if (empty($secure)) {
            $secure = self::encryptionForPort($mail->Port);
        }
        if ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
    }

    private function configureSender(PHPMailer $mail, string $mailSender, string $nameSender, bool $keepSender): void
    {
        if (!empty($this->config()['contact_reply_to'])) {
            $mail->addReplyTo($this->config()['contact_reply_to']);
        } else {
            $mail->addReplyTo($mailSender, $nameSender);
        }

        if (!$keepSender && !empty($this->config()['contact_from'])) {
            $mailSender = $this->config()['contact_from'];
        }

        $mail->setFrom($mailSender, empty($nameSender) ? $mailSender : $nameSender);
        $this->signWithDkim($mail);
    }

    /** Signs with DKIM when configured. */
    private function signWithDkim(PHPMailer $mail): void
    {
        $domain = trim((string)($this->config()['contact_dkim_domain'] ?? ''));
        $selector = trim((string)($this->config()['contact_dkim_selector'] ?? ''));
        $key = $this->dkimPrivateKey(trim((string)($this->config()['contact_dkim_private_key'] ?? '')));
        if ($domain === '' || $selector === '' || $key === '') {
            return;
        }

        $mail->DKIM_domain = $domain;
        $mail->DKIM_selector = $selector;
        $mail->DKIM_private_string = $key;
        $mail->DKIM_identity = str_ends_with(strtolower($mail->From), '@' . strtolower($domain)) ? $mail->From : '';
    }

    /** Reads the DKIM private key, or null. */
    private function dkimPrivateKey(string $path): string
    {
        if ($path === '') {
            return '';
        }
        $storage = $this->container->get(Storage::class);

        try {
            if (str_starts_with($path, '/')) {
                return $storage->readForeign($path);
            }

            return $storage->fileExists($path) ? $storage->read($path) : '';
        } catch (StorageException) {
            return '';
        }
    }

    /** The encryption the well-known submission ports imply, when nothing is configured. */
    private static function encryptionForPort(int $port): string
    {
        return match ((string)$port) {
            '465' => 'ssl',
            '587' => 'tls',
            default => '',
        };
    }

    private function config(): RuntimeConfig
    {
        return $this->container->get(RuntimeConfig::class);
    }

    public function getBaseUrl(): string
    {
        return $this->container->get(UrlFormatter::class)->getBaseUrl();
    }

    /** A configuration value the wiki always stores as text, read as text. */
    private function stringParam(string $name): string
    {
        $value = $this->params->get($name);

        return is_scalar($value) ? (string)$value : '';
    }

    /** Adds wiki= to links when the smtp relay prepends its own query parameter. */
    private function sanitizeLinksIfNeeded(string $text): string
    {
        if ($this->params->get('contact_mail_func') === 'smtp'
            && $this->params->has('contact_use_long_wiki_urls_in_emails')
            && $this->params->get('contact_use_long_wiki_urls_in_emails')
        ) {
            $baseUrl = $this->getBaseUrl();
            $text = (string)preg_replace('/(' . preg_quote("href=\"{$baseUrl}/?", '/') . ')(?=' . WN_CAMEL_CASE_EVOLVED_WITH_SLASH . '(?:&|\\"))/u', '$1wiki=', $text);
        }

        return $text;
    }
}
