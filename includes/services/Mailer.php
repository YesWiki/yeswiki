<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Wiki;

class Mailer
{
    protected $wiki;
    protected $dbService;
    protected $params;
    protected $templateEngine;
    protected $userManager;

    public function __construct(
        Wiki $wiki,
        DbService $dbService,
        ParameterBagInterface $params,
        TemplateEngine $templateEngine,
        UserManager $userManager
    ) {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->params = $params;
        $this->templateEngine = $templateEngine;
        $this->userManager = $userManager;
    }

    public function notifyAdmins($data, $new)
    {
        include_once 'tools/contact/libs/contact.functions.php';
        

        // on va chercher les admins
        $requeteadmins = 'SELECT value FROM ' . $this->dbService->prefixTable('triples')
            . ' WHERE resource="ThisWikiGroup:admins" AND property="http://www.wikini.net/_vocabulary/acls" LIMIT 1';
        $ligne = $this->dbService->loadSingle($requeteadmins);
        $tabadmin = explode("\n", $ligne['value']);
        $admins = array_filter(array_map(function ($line) {
            return $this->wiki->LoadUser(trim($line));
        }, $tabadmin), function ($user) {
            return !empty($user) ;
        });

        // =======

        $baseUrl = $this->getBaseUrl();
        $sujet = removeAccents($this->templateEngine->render(
            '@contact/notify-admins-email-subject.twig',
            [
                'entry' => $data,
                'baseUrl' => $baseUrl,
                'new' => $new,
            ]
        ));
        $text = $this->templateEngine->render(
            '@contact/notify-admins-email-text.twig',
            [
                'entry' => $data,
                'baseUrl' => $baseUrl,
            ]
        );
        $userName = $admins[0]['name'] ?? null ;
        $html = $this->sanitizeLinksIfNeeded($this->templateEngine->render(
            '@contact/notify-admins-email-html.twig',
            [
                'style' => file_get_contents('tools/bazar/presentation/styles/bazar.css'),
                'entry' => $data,
                'entryHTML' => $this->wiki->services->get(EntryController::class)->view($data['id_fiche'], '', true, $userName),
                'baseUrl' => $baseUrl,
            ]
        ));

        foreach ($admins as $admin) {
            send_mail($this->params->get('BAZ_ADRESSE_MAIL_ADMIN'), $this->params->get('BAZ_ADRESSE_MAIL_ADMIN'), $admin['email'], $sujet, $text, $html);
        }
    }

    public function notifyAdminsListDeleted($id)
    {
        include_once 'tools/contact/libs/contact.functions.php';

        $baseUrl = $this->getBaseUrl();
        $sujet = removeAccents($this->templateEngine->render(
            '@contact/notify-admins-list-deleted-email-subject.twig',
            [
                'baseUrl' => $baseUrl,
                'listId' => $id,
            ]
        ));
        $text = $this->templateEngine->render(
            '@contact/notify-admins-list-deleted-email-text.twig',
            [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'userName' => $this->wiki->GetUserName(),
            ]
        );
        $html = $this->sanitizeLinksIfNeeded($this->templateEngine->render(
            '@contact/notify-admins-list-deleted-email-html.twig',
            [
                'style' => file_get_contents('tools/bazar/presentation/styles/bazar.css'),
                'ip' => $_SERVER['REMOTE_ADDR'],
                'userName' => $this->wiki->GetUserName(),
                'baseUrl' => $baseUrl,
            ]
        ));

        //on va chercher les admins
        $requeteadmins = 'SELECT value FROM '.$GLOBALS['wiki']
                ->config['table_prefix'].

            'triples WHERE resource="ThisWikiGroup:admins" AND property="http://www.wikini.net/_vocabulary/acls" LIMIT 1';
        $ligne = $GLOBALS['wiki']->LoadSingle($requeteadmins);
        $tabadmin = explode("\n", $ligne['value']);
        foreach ($tabadmin as $line) {
            $admin = $GLOBALS['wiki']->LoadUser(trim($line));
            send_mail($GLOBALS['wiki']->config['BAZ_ADRESSE_MAIL_ADMIN'], $GLOBALS['wiki']->config['BAZ_ADRESSE_MAIL_ADMIN'], $admin['email'], $sujet, $text, $html);
        }
    }

    public function notifyEmail($email, $data)
    {
        include_once 'includes/email.inc.php';
        $baseUrl = $this->getBaseUrl();
        $sujet = removeAccents($this->templateEngine->render(
            '@contact/notify-email-subject.twig',
            [
                'entry' => $data,
                'baseUrl' => $baseUrl,
            ]
        ));
        $text = $this->templateEngine->render(
            '@contact/notify-email-text.twig',
            [
                'entry' => $data,
                'baseUrl' => $baseUrl,
            ]
        );
        $user = $this->userManager->getOneByEmail($email);
        $userName = $user['name'] ?? null ;
        $html = $this->sanitizeLinksIfNeeded($this->templateEngine->render(
            '@contact/notify-email-html.twig',
            [
                'style' => file_get_contents('tools/bazar/presentation/styles/bazar.css'),
                'entry' => $data,
                'entryHTML' => $this->wiki->services->get(EntryController::class)->view($data['id_fiche'], '', true, $userName),
                'baseUrl' => $baseUrl,
                'mailCustomMessage' => $this->params->has('mail_custom_message') ? $this->params->get('mail_custom_message') : null,
            ]
        ));

        send_mail($this->params->get('BAZ_ADRESSE_MAIL_ADMIN'), $this->params->get('BAZ_ADRESSE_MAIL_ADMIN'), $email, $sujet, $text, $html);
    }

    public function notifyNewUser($wikiName, $email)
    {
        include_once 'includes/email.inc.php';
        $baseUrl = $this->getBaseUrl();
        $objetmail = removeAccents($this->templateEngine->render(
            '@contact/notify-newuser-email-subject.twig',
            [
                'baseUrl' => $baseUrl,
                'wakkaName' => $this->params->get('wakka_name'),
            ]
        ));
        $messagemail = $this->templateEngine->render(
            '@contact/notify-newuser-email-text.twig',
            [
                'wikiName' => $wikiName,
                'email' => $email,
                'baseUrl' => $baseUrl,
            ]
        );

        send_mail(
            $this->params->get('BAZ_ADRESSE_MAIL_ADMIN'),
            $this->params->get('BAZ_ADRESSE_MAIL_ADMIN'),
            $email,
            removeAccents($objetmail),
            $messagemail
        );
    }

    public function subscribeToMailingList($email, $mailingList)
    {
        include_once 'includes/email.inc.php';
        send_mail(
            $email,
            $email,
            $mailingList,
            'inscription a la liste de discussion',
            'inscription'
        );
    }

    private function getBaseUrl(): string
    {
        return preg_replace('/(\\/wakka\\.php\\?wiki=|\\/\\?wiki=|\\/\\?|\\/)$/m', '', $this->params->get('base_url')) ;
    }

    /**
     * add $_GET['wiki'] in url if smtp use a relay that put a new parameter as the beginning of url's query
     * @param string $text
     * @return string $text
     */
    private function sanitizeLinksIfNeeded(string $text):string
    {
        if ($this->params->get('contact_mail_func') === 'smtp'
            && $this->params->has('contact_use_long_wiki_urls_in_emails')
            && $this->params->get('contact_use_long_wiki_urls_in_emails')
            ) {
            $baseUrl = $this->getBaseUrl();
            $text = str_replace("href=\"{$baseUrl}/?", "href=\"{$baseUrl}/?wiki=", $text);
        }
        return $text;
    }
}
