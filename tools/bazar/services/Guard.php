<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\EmailField;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\UserManager;
use YesWiki\Wiki;

class Guard
{
    protected $wiki;
    protected $formManager;
    protected $userManager;
    protected $aclService;

    public function __construct(Wiki $wiki, FormManager $formManager, UserManager $userManager, AclService $aclService)
    {
        $this->wiki = $wiki;
        $this->formManager = $formManager;
        $this->userManager = $userManager;
        $this->aclService = $aclService;
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

        if ($this->isPageOwner($page)) {
            // Pas de controle si proprietaire
            return $page;
        }
        if ($page) {
            $valjson = $page["body"];
            $valeur = json_decode($valjson, true);

            if ($valeur) {
                $form = $this->formManager->getOne($valeur['id_typeannonce']);
                if ($form) {
                    $fieldname = array();
                    foreach ($form['prepared'] as $field) {
                        // cas des formulaires champs mails, qui ne doivent pas apparaitre en /raw
                        if ($field instanceof EmailField
                                && $field->getShowContactForm() == 'form'
                                && ($this->wiki->getMethod() == 'raw'
                                || $this->wiki->getMethod() == 'json'
                                || $this->wiki->GetPageTag() == 'api')
                                ) {
                            $fieldname[] = $field->getPropertyName();
                        }
                        if ($field instanceof BazarField
                                && !$field->canRead(['id_fiche' => $tag])
                                ) {
                            $fieldname[] = $field->getPropertyName() ;
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
        if (!$this->userManager->getLoggedUser()) {
            return false;
        }
        // check if user is owner
        if ($page['owner'] == $this->userManager->getLoggedUserName()) {
            return true;
        }
        return false;
    }
}
