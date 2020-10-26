<?php

namespace YesWiki\Core\Service;

class Mailer
{
    protected $wiki;
    protected $adminEmails;

    public function __construct($wiki, $adminEmails)
    {
        $this->wiki = $wiki;
        $this->adminEmails = $adminEmails;
    }

    public function notifyAdmins($data, $new)
    {
        include_once 'tools/contact/libs/contact.functions.php';

        $lien = str_replace('/wakka.php?wiki=', '', $this->wiki->config['base_url']);
        $sujet = removeAccents('[' . str_replace('http://', '', $lien) . '] nouvelle fiche ' . ($new ? 'ajoutee' : 'modifiee') . ' : ' . $data['bf_titre']);
        $text = 'Voir la fiche sur le site pour l\'administrer : ' . $this->wiki->href('', $data['id_fiche']);
        $texthtml = '<br /><br /><a href="' . $this->wiki->href('', $data['id_fiche']) . '" title="Voir la fiche">Voir la fiche sur le site pour l\'administrer</a>';
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
        $requeteadmins = 'SELECT value FROM ' . $this->wiki->config['table_prefix'] . 'triples '
            . 'WHERE resource="ThisWikiGroup:admins" AND property="http://www.wikini.net/_vocabulary/acls" LIMIT 1';
        $ligne = $this->wiki->LoadSingle($requeteadmins);
        $tabadmin = explode("\n", $ligne['value']);
        foreach ($tabadmin as $line) {
            $admin = $this->wiki->LoadUser(trim($line));
            send_mail($this->adminEmails, $this->adminEmails, $admin['email'], $sujet, $text, $html);
        }
    }
}
