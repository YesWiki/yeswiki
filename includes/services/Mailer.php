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

    public function __construct(Wiki $wiki, DbService $dbService, ParameterBagInterface $params, TemplateEngine $templateEngine)
    {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->params = $params;
        $this->templateEngine = $templateEngine;
    }

    public function notifyAdmins($data, $new)
    {
        include_once 'tools/contact/libs/contact.functions.php';

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
        $html = $this->templateEngine->render(
            '@contact/notify-admins-email-html.twig',
            [
                'style' => file_get_contents('tools/bazar/presentation/styles/bazar.css'),
                'entry' => $data,
                'entryHTML' => $this->wiki->services->get(EntryController::class)->view($data['id_fiche']),
                'baseUrl' => $baseUrl,
            ]
        );

        // on va chercher les admins
        $requeteadmins = 'SELECT value FROM ' . $this->dbService->prefixTable('triples')
            . ' WHERE resource="ThisWikiGroup:admins" AND property="http://www.wikini.net/_vocabulary/acls" LIMIT 1';
        $ligne = $this->dbService->loadSingle($requeteadmins);
        $tabadmin = explode("\n", $ligne['value']);
        foreach ($tabadmin as $line) {
            $admin = $this->wiki->LoadUser(trim($line));
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
        $html = $this->templateEngine->render(
            '@contact/notify-admins-list-deleted-email-html.twig',
            [
                'style' => file_get_contents('tools/bazar/presentation/styles/bazar.css'),
                'ip' => $_SERVER['REMOTE_ADDR'],
                'userName' => $this->wiki->GetUserName(),
                'baseUrl' => $baseUrl,
            ]
        );

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
        $html = $this->templateEngine->render(
            '@contact/notify-email-html.twig',
            [
                'style' => file_get_contents('tools/bazar/presentation/styles/bazar.css'),
                'entry' => $data,
                'entryHTML' => $this->wiki->services->get(EntryController::class)->view($data['id_fiche']),
                'baseUrl' => $baseUrl,
                'mailCustomMessage' => $this->params->has('mail_custom_message') ? $this->params->get('mail_custom_message') : null,
            ]
        );

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
}
