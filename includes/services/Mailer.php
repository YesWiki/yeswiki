<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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
                baz_voir_fiche(0, $data['id_fiche'])
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
}
