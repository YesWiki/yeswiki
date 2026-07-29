<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Wiki;

class Mailer
{
    protected $wiki;
    protected $authenticationService;
    protected $dbService;
    protected $params;
    protected $templateEngine;
    protected $userManager;

    public function __construct(
        Wiki $wiki,
        AuthenticationService $authenticationService,
        DbService $dbService,
        ParameterBagInterface $params,
        TemplateEngine $templateEngine,
        UserManager $userManager
    ) {
        $this->wiki = $wiki;
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
                'style' => file_get_contents(YESWIKI_SOURCE_DIR . '/styles/bazar/bazar.css'),
                'entry' => $data,
                'entryHTML' => $this->wiki->services->get(EntryController::class)->view($data['tag'], '', true, $userName),
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
                'ip' => $this->wiki->isCli() ? '' : $this->wiki->request->getClientIp(),
                'userName' => $this->authenticationService->getLoggedUserName(),
            ]
        );
        $html = $this->templateEngine->render(
            '@core/notify-admins-list-deleted-email-html.twig',
            [
                'style' => file_get_contents(YESWIKI_SOURCE_DIR . '/styles/bazar/bazar.css'),
                'ip' => $this->wiki->isCli() ? '' : $this->wiki->request->getClientIp(),
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
        $adminsAcl = $this->wiki->services->get(\YesWiki\Identity\Service\GroupOperationsService::class)->getMembersText(ADMIN_GROUP);
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
            removeAccents($subject),
            $text,
            empty($html) ? $html : $this->sanitizeLinksIfNeeded($html)
        );
    }

    /**
     * Generic send, the single seam every mail-sending caller in core should go
     * through (ticket 18) instead of calling send_mail() directly -- a thin wrapper
     * today (send_mail() itself, in src/email.inc.php, is unchanged), but the seam
     * this ticket asked for so callers don't reach past the abstraction.
     *
     * @param string|string[] $mailReceiver
     */
    public function send($mailSender, $nameSender, $mailReceiver, string $subject, string $messageTxt, string $messageHtml = ''): bool
    {
        include_once YESWIKI_SOURCE_DIR . '/src/email.inc.php';

        return send_mail($mailSender, $nameSender, $mailReceiver, $subject, $messageTxt, $messageHtml);
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
            // in this case, we can used empty $userName otherwise the acl will be check for the current user not the email
            // so we use a fake username
            do {
                $randomString = md5(rand());
                $existingUser = $this->userManager->getOneByName($randomString);
            } while (!empty($existingUser));
            $userName = $randomString;
        }
        $html = $this->templateEngine->render(
            '@core/notify-email-html.twig',
            [
                'style' => file_get_contents(YESWIKI_SOURCE_DIR . '/styles/bazar/bazar.css'),
                'entry' => $data,
                'entryHTML' => $this->wiki->services->get(EntryController::class)->view($data['tag'], '', true, $userName),
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

    // TODO when PR #967 merged, refactor this part with YesWiki::getBaseUrl
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
            $text = preg_replace('/(' . preg_quote("href=\"{$baseUrl}/?", '/') . ')(?=' . WN_CAMEL_CASE_EVOLVED_WITH_SLASH . '(?:&|\\"))/u', '$1wiki=', $text);
        }

        return $text;
    }
}
