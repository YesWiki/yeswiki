<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Core\Service\UserManager;
use YesWiki\Wiki;

class Guard
{
    protected $wiki;
    protected $formManager;
    protected $userManager;

    public function __construct(Wiki $wiki, FormManager $formManager, UserManager $userManager)
    {
        $this->wiki = $wiki;
        $this->formManager = $formManager;
        $this->userManager = $userManager;
    }

    // TODO remove this method and use YesWiki::HasAccess
    public function isAllowed($action = 'saisie_fiche', $ownerId = '') : bool
    {
        $loggedUserName = $this->userManager->getLoggedUserName();
        $isOwner = $ownerId === $loggedUserName || $ownerId === '';

        // Admins are allowed all actions
        if ($GLOBALS['wiki']->UserIsInGroup('admins')) {
            return true;
        }

        switch ($action) {
            case 'supp_fiche':
            case 'voir_champ':
                return $isOwner;

            case 'modif_fiche':
            case 'saisie_fiche':
            case 'voir_mes_fiches':
                return true;

            case 'valider_fiche':
            case 'saisie_formulaire':
            case 'saisie_liste':
            default:
                return false;
        }
    }

    // Teste les droits d'acces champ par champ du contenu d'un fiche bazar
    // Si utilisateur connecte est  proprietaire ou adminstrateur : acces a tous les champs
    // Sinon ne sont retournes que les champs dont les droits d'acces sont compatibles.
    // Introduction du droit % : seul le proprietaire peut acceder
    public function checkAcls($page, $tag)
    {
        // TODO :
        // loadpagebyid
        // bazarliste ...
        // champ mot de passe ?
        //

        $INDEX_CHELOUS = ['radio', 'liste', 'checkbox', 'listefiche', 'checkboxfiche'];
        if ($this->isPageOwner($page)) {
            // Pas de controle si proprietaire
            return $page;
        }
        if ($page) {
            $valjson = $page["body"];
            $valeur = json_decode($valjson, true);

            if ($valeur) {
                $val_formulaire = $this->formManager->getOne($valeur['id_typeannonce']);
                if ($val_formulaire) {
                    $fieldname = array();
                    foreach ($val_formulaire['template'] as $line) {
                        // cas des formulaires champs mails, qui ne doivent pas apparaitre en /raw
                        if ($line[0] == 'champs_mail' and !empty($line[6]) and $line[6] == 'form') {
                            if ($this->wiki->getMethod() == 'raw' || $this->wiki->getMethod() == 'json') {
                                $fieldname[] = $line[1];
                            }
                        }
                        if (isset($line[11]) && $line[11] != '') {
                            if ($line[11] == "%") {
                                $line[11] = $this->wiki->GetUserName();
                            }
                            if (!$this->wiki->CheckACL($line[11])) {
                                // on memorise les champs non autorisés
                                if (in_array($line[0], $INDEX_CHELOUS)) {
                                    $fieldname[] = $line[0] . $line[1] . $line[6];
                                } else {
                                    $fieldname[] = $line[1];
                                }
                            }
                        }
                    }
                    if (count($fieldname) > 0) {
                        //
                        foreach ($fieldname as $field) {
                            $valeur[$field] = "";
                            // on vide le champ
                        }
                        //$valeur = array_map("utf8_encode", $valeur);
                        $page["body"] = json_encode($valeur);
                    }
                }
            }
        }
        return $page;
    }

    protected function isPageOwner($page) : bool
    {
        // check if user is logged in
        if (!$this->wiki->GetUser()) {
            return false;
        }
        // check if user is owner
        if ($page['owner'] == $this->wiki->GetUserName()) {
            return true;
        }
        return false;
    }
}
