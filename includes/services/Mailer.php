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

    public function __construct(Wiki $wiki, DbService $dbService, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->params = $params;
    }

    public function notifyAdmins($data, $new)
    {
        include_once 'tools/contact/libs/contact.functions.php';

        $lien = str_replace('/wakka.php?wiki=', '', $this->params->get('base_url'));
        $sujet = removeAccents('[' . str_replace('http://', '', $lien) . '] nouvelle fiche ' . ($new ? 'ajoutee' : 'modifiee') . ' : ' . $data['bf_titre']);
        $text = 'Voir la fiche sur le site pour l\'administrer : ' . $this->wiki->Href('', $data['id_fiche']);
        $texthtml = '<br /><br /><a href="' . $this->wiki->Href('', $data['id_fiche']) . '" title="Voir la fiche">Voir la fiche sur le site pour l\'administrer</a>';
        $fichier = 'tools/bazar/presentation/styles/bazar.css';
        $style = file_get_contents($fichier);
        $style = str_replace('url(', 'url(' . $lien . '/tools/bazar/presentation/', $style);
        $fiche = str_replace(
            'src="tools',
            'src="' . $lien . '/tools',
            $this->wiki->services->get(EntryController::class)->view($data['id_fiche'])
        ) . $texthtml;
        $html =
            '<html><head><style type="text/css">' . $style .
            '</style></head><body>' . $fiche . '</body></html>';

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

        $lien = str_replace('/wakka.php?wiki=', '', $GLOBALS['wiki']->config['base_url']);
        $sujet = removeAccents('['.str_replace('http://', '', $lien).'] liste supprimee : '.$id);
        $text =
            'IP utilisee : '.$_SERVER['REMOTE_ADDR'].' ('.
            $GLOBALS['wiki']->GetUserName().')';
        $texthtml = $text;
        $fichier = 'tools/bazar/presentation/styles/bazar.css';
        $style = file_get_contents($fichier);
        $style = str_replace('url(', 'url('.$lien.'/tools/bazar/presentation/', $style);
        $html =
            '<html><head><style type="text/css">'.$style.
            '</style></head><body>'.$texthtml.'</body></html>';

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
        $lien = str_replace('/wakka.php?wiki=', '', $this->params->get('base_url'));
        $sujet = removeAccents('['.str_replace(array('http://', 'https://'), '', $lien).'] Votre fiche : '.$data['bf_titre']);
        $lienfiche = $this->params->get('base_url').$data['id_fiche'];
        $texthtml = 'Bienvenue sur '.removeAccents(str_replace('http://', '', $lien).' , ');
        $text = 'Bienvenue sur '.removeAccents(str_replace('http://', '', $lien).' , ');
        $text .= 'allez sur le site pour gérer votre inscription  : '.$lienfiche;
        $texthtml .= '<br /><br /><a href="'.$lienfiche.'" title="Voir la fiche">Voir la fiche sur le site</a>';
        if ($this->params->has('mail_custom_message')) {
            $texthtml .= nl2br($this->params->get('mail_custom_message'));
        }
        $fichier = 'tools/bazar/presentation/styles/bazar.css';
        $style = file_get_contents($fichier);
        $style = str_replace('url(', 'url('.$lien.'/tools/bazar/presentation/', $style);
        $fiche = $texthtml.str_replace('src="tools', 'src="'.$lien.'/tools', $this->wiki->services->get(EntryController::class)->view($data['id_fiche']));
        $html = '<html><head><style type="text/css">'.$style.'</style></head><body>'.$fiche.'</body></html>';

        send_mail($this->params->get('BAZ_ADRESSE_MAIL_ADMIN'), $this->params->get('BAZ_ADRESSE_MAIL_ADMIN'), $email, $sujet, $text, $html);
    }

    public function notifyNewUser($wikiName, $email)
    {
        include_once 'includes/email.inc.php';
        $lien = str_replace("/wakka.php?wiki=", "", $this->params->get('base_url'));
        $objetmail = '['.str_replace("http://", "", $lien).'] Vos nouveaux identifiants sur le site '.$this->params->get('wakka_name');
        $messagemail = "Bonjour!\n\nVotre inscription sur le site a ete finalisee, dorenavant vous pouvez vous identifier avec les informations suivantes :\n\nVotre identifiant NomWiki : ".$wikiName."\n\nVotre email : ".$email."\n\nVotre mot de passe : (le mot de passe que vous avez choisi)\n\n\n\nA tres bientot ! \n\n";

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
}
