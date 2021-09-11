<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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
    protected $params;
    protected $authorizedGroupsToEditForms;

    public function __construct(Wiki $wiki, FormManager $formManager, UserManager $userManager, AclService $aclService, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        $this->formManager = $formManager;
        $this->userManager = $userManager;
        $this->aclService = $aclService;
        $this->userManager = $userManager;
        $this->params = $params;
        $this->authorizedGroupsToEditForms = null;
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
                // it should not be possible to delete a file if not connected even if no owner (prevent spam)
                return $ownerId != '' && $isOwner;
            case 'voir_champ':
                return $isOwner;

            case 'modif_fiche':
            case 'saisie_fiche':
            case 'voir_mes_fiches':
                return true;

            case 'saisie_formulaire':
                return $this->isUserInAuthorizedGroupsToEditForms();
            case 'valider_fiche':
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

        if ($this->wiki->UserIsAdmin() || $this->isPageOwner($page)) {
            // Pas de controle si proprietaire ou administrateur
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
                                || $this->wiki->getMethod() == 'diff'
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

    /**
     * check if user in authorized groups to edit forms
     * @return bool
     */
    private function isUserInAuthorizedGroupsToEditForms(): bool
    {
        $user = $this->userManager->getLoggedUser();
        if (empty($user)) {
            return false;
        }
        if (is_null($this->authorizedGroupsToEditForms)) {
            $authorizedGroups = $this->params->get('baz_allowed_group_to_edit_forms');
        
            if (empty($authorizedGroups) || !is_array($authorizedGroups)) {
                $this->authorizedGroupsToEditForms = [];
            } else {
                $groupsList = $this->wiki->GetGroupsList();
                if (empty($groupsList)) {
                    $this->authorizedGroupsToEditForms = [];
                } else {
                    $groupsList = array_map(function ($group) {
                        return '@'.$group;
                    }, $groupsList);
                    $this->authorizedGroupsToEditForms = array_filter($authorizedGroups, function ($group) use ($groupsList) {
                        return is_string($group) && (substr($group, 0, 1) === "@") && in_array($group, $groupsList);
                    });
                }
            }
        }
        if (empty($this->authorizedGroupsToEditForms)) {
            return false;
        }
        foreach ($this->authorizedGroupsToEditForms as $group) {
            if ($this->wiki->UserIsInGroup(substr($group, 1))) {
                return true;
            }
        }
        return false;
    }
}
