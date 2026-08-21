<?php

namespace YesWiki\Kernel\Service;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Render\Service\TemplateEngine;

class Mailer
{
    /** How many recipients one message carries before the next batch. */
    private const BATCH_SIZE = 10;

    protected ContainerInterface $container;
    protected $authenticationService;
    protected $dbService;
    protected $params;
    protected $templateEngine;
    protected $userManager;

    public function __construct(
        ContainerInterface $container,
        AuthenticationService $authenticationService,
        DbService $dbService,
        ParameterBagInterface $params,
        TemplateEngine $templateEngine,
        UserManager $userManager
    ) {
        $this->container = $container;
        $this->authenticationService = $authenticationService;
        $this->dbService = $dbService;
        $this->params = $params;
        $this->templateEngine = $templateEngine;
        $this->userManager = $userManager;
    }

    public function notifyAdmins($data, $new)
    {
        $admins = $this->getAdminsList();

        $baseUrl = $this->getBaseUrl();
        $sujet = $this->templateEngine->render(
            '@core/notify-admins-email-subject.twig',
            [
                'entry' => $data,
                'baseUrl' => $baseUrl,
                'new' => $new,
            ]
        );
        $text = $this->templateEngine->render(
            '@core/notify-admins-email-text.twig',
            [
                'entry' => $data,
                'baseUrl' => $baseUrl,
            ]
        );
        $userName = $admins[0]['name'] ?? null;
        $html = $this->templateEngine->render(
            '@core/notify-admins-email-html.twig',
            [
                'style' => file_get_contents(YESWIKI_SOURCE_DIR . '/styles/email.css'),
                'entry' => $data,
                'entryHTML' => $this->container->get(EntryController::class)->view($data['tag'], '', true, $userName),
                'baseUrl' => $baseUrl,
            ]
        );

        foreach ($admins as $admin) {
            $this->sendEmailFromAdmin($admin['email'], $sujet, $text, $html);
        }
    }

    public function notifyAdminsListDeleted($id)
    {
        $baseUrl = $this->getBaseUrl();
        $sujet = $this->templateEngine->render(
            '@core/notify-admins-list-deleted-email-subject.twig',
            [
                'baseUrl' => $baseUrl,
                'listId' => $id,
            ]
        );
        $text = $this->templateEngine->render(
            '@core/notify-admins-list-deleted-email-text.twig',
            [
                'ip' => \YesWiki\YesWikiKernel::isCli() ? '' : $this->container->get(CurrentRequest::class)->get()->getClientIp(),
                'userName' => $this->authenticationService->getLoggedUserName(),
            ]
        );
        $html = $this->templateEngine->render(
            '@core/notify-admins-list-deleted-email-html.twig',
            [
                'style' => file_get_contents(YESWIKI_SOURCE_DIR . '/styles/email.css'),
                'ip' => \YesWiki\YesWikiKernel::isCli() ? '' : $this->container->get(CurrentRequest::class)->get()->getClientIp(),
                'userName' => $this->authenticationService->getLoggedUserName(),
                'baseUrl' => $baseUrl,
            ]
        );

        foreach ($this->getAdminsList() as $admin) {
            $this->sendEmailFromAdmin($admin['email'], $sujet, $text, $html);
        }
    }

    private function getAdminsList(): array
    {
        $adminsAcl = $this->container->get(\YesWiki\Identity\Service\GroupOperationsService::class)->getMembersText(ADMIN_GROUP);
        $admins = [];
        foreach (explode("\n", $adminsAcl) as $line) {
            $line = trim($line);
            if (!empty($line)
                && substr($line, 0, 1) != '#'
                && substr($line, 0, 1) != '@') {
                $adminUser = $this->userManager->getOneByName($line);
                if (!empty($adminUser)) {
                    $admins[] = $adminUser;
                }
            }
        }

        return $admins;
    }

    public function sendEmailFromAdmin(string $address, string $subject, string $text, string $html = '')
    {
        $this->send(
            $this->params->get('BAZ_ADRESSE_MAIL_ADMIN'),
            $this->params->get('BAZ_ADRESSE_MAIL_ADMIN'),
            $address,
            StringUtilService::withoutDiacritics($subject),
            $text,
            empty($html) ? $html : $this->sanitizeLinksIfNeeded($html)
        );
    }

    /**
     * Generic send, the single seam every mail-sending caller in core goes through (ticket 18).
     *
     * Was a wrapper around the global `send_mail()` in `Kernel/email.inc.php`, which reached for
     * the container through `$GLOBALS['yeswikiServices']` fourteen times to read its own
     * configuration. Ticket 50 folded it in here, where the configuration is already injected.
     *
     * @param string|string[] $mailReceiver
     */
    public function send($mailSender, $nameSender, $mailReceiver, string $subject, string $messageTxt, string $messageHtml = ''): bool
    {
        $mail = new PHPMailer(true);

        try {
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

            // Sent in batches with a pause between them, which is what keeps a large mailing
            // from looking like a burst to the receiving side.
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
            if ($this->container->get(\YesWiki\Identity\Service\AclService::class)->isAdmin()) {
                echo $e->errorMessage();
            }

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

    public function notifyEmail($email, $data, bool $isCreation = false, ?array $previousEntry = null)
    {
        $baseUrl = $this->getBaseUrl();
        $sujet = $this->templateEngine->render(
            '@core/notify-email-subject.twig',
            [
                'entry' => $data,
                'baseUrl' => $baseUrl,
                'previousEntry' => $previousEntry,
                'isCreation' => $isCreation,
            ]
        );
        $text = $this->templateEngine->render(
            '@core/notify-email-text.twig',
            [
                'entry' => $data,
                'baseUrl' => $baseUrl,
                'previousEntry' => $previousEntry,
                'isCreation' => $isCreation,
            ]
        );
        $user = $this->userManager->getOneByEmail($email);
        $currentUser = $this->authenticationService->getLoggedUser();
        if (!empty($user['name'])) {
            $userName = $user['name'];
        } elseif (empty($currentUser)) {
            $userName = null;
        } else {
            do {
                $randomString = md5(rand());
                $existingUser = $this->userManager->getOneByName($randomString);
            } while (!empty($existingUser));
            $userName = $randomString;
        }
        $html = $this->templateEngine->render(
            '@core/notify-email-html.twig',
            [
                'style' => file_get_contents(YESWIKI_SOURCE_DIR . '/styles/email.css'),
                'entry' => $data,
                'entryHTML' => $this->container->get(EntryController::class)->view($data['tag'], '', true, $userName),
                'baseUrl' => $baseUrl,
                'mailCustomMessage' => $this->params->has('mail_custom_message') ? $this->params->get('mail_custom_message') : null,
                'previousEntry' => $previousEntry,
                'isCreation' => $isCreation,
            ]
        );

        $this->sendEmailFromAdmin($email, $sujet, $text, $html);
    }

    public function notifyNewUser($wikiName, $email)
    {
        $baseUrl = $this->getBaseUrl();
        $objetmail = $this->templateEngine->render(
            '@core/notify-newuser-email-subject.twig',
            [
                'baseUrl' => $baseUrl,
                'yeswikiName' => $this->params->get('yeswiki_name'),
            ]
        );
        $messagemail = $this->templateEngine->render(
            '@core/notify-newuser-email-text.twig',
            [
                'wikiName' => $wikiName,
                'email' => $email,
                'baseUrl' => $baseUrl,
            ]
        );

        $this->sendEmailFromAdmin($email, $objetmail, $messagemail);
    }

    public function subscribeToMailingList($email, $mailingList)
    {
        $this->send(
            $email,
            $email,
            $mailingList,
            'inscription a la liste de discussion',
            'inscription'
        );
    }

    public function getBaseUrl(): string
    {
        return preg_replace('/(\\/\\?wiki=|\\/\\?|\\/)$/m', '', $this->params->get('base_url'));
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
