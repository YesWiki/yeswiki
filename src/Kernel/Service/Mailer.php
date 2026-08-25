<?php

namespace YesWiki\Kernel\Service;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Sending an email, and nothing about what is in it.
 *
 * Composing a notification -- rendering an entry, looking a user up, picking a template -- moved
 * to `Content\Service\ContentNotifier`, which is where every caller of it already was. What was
 * left here is the transport: PHPMailer, the configured relay, the sender, and batching. That is
 * the whole reason a Kernel service can now be one (ADR-0013).
 */
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
     * Generic send, the single seam every mail-sending caller in core goes through (ticket 18).
     *
     * @param string          $mailSender
     * @param string          $nameSender
     * @param string|string[] $mailReceiver
     */
    public function send($mailSender, $nameSender, $mailReceiver, string $subject, string $messageTxt, string $messageHtml = ''): bool
    {
        $mail = new PHPMailer(true);

        try {
            $this->lastError = '';
            $mail->set('CharSet', 'utf-8');
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
            // Recorded rather than echoed. It used to ask AclService whether the visitor was an
            // admin and print the error into the page, which put an Identity lookup and a
            // rendering decision inside the transport. A caller with somewhere to show it asks
            // lastError(); one with nowhere gets false, which is what it could act on anyway.
            $this->lastError = $e->errorMessage();

            return false;
        }
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
        return (string)preg_replace('/(\\/\\?wiki=|\\/\\?|\\/)$/m', '', $this->stringParam('base_url'));
    }

    /** A configuration value the wiki always stores as text, read as text. */
    private function stringParam(string $name): string
    {
        $value = $this->params->get($name);

        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * add $_GET['wiki'] in url if smtp use a relay that put a new parameter as the beginning of url's query.
     *
     * @return string $text
     */
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
