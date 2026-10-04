<?php

namespace YesWiki\Kernel\Service;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/** Sends an email through the configured transport, and nothing about what is in it (ADR-0013). */
class Mailer
{
    /** How many recipients one message carries before the next batch. */
    private const BATCH_SIZE = 10;

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
     * Sends to a lone recipient in To, or to several in batches of BCC.
     *
     * @param string          $mailSender
     * @param string          $nameSender
     * @param string|string[] $mailReceiver
     */
    public function send($mailSender, $nameSender, $mailReceiver, string $subject, string $messageTxt, string $messageHtml = ''): bool
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
            $this->configureSender($mail, $mailSender, $nameSender);

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
                $mail->addAddress((string)reset($mailReceiver));
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
            $this->lastError = $e->errorMessage();

            return false;
        }
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

    private function configureSender(PHPMailer $mail, string $mailSender, string $nameSender): void
    {
        if (!empty($this->config()['contact_reply_to'])) {
            $mail->addReplyTo($this->config()['contact_reply_to']);
        } else {
            $mail->addReplyTo($mailSender, $nameSender);
        }

        if (!empty($this->config()['contact_from'])) {
            $mailSender = $this->config()['contact_from'];
        }

        $mail->setFrom($mailSender, empty($nameSender) ? $mailSender : $nameSender);
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
