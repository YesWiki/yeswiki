<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\EmailField;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\UserManager;
use YesWiki\Wiki;

class Guard
{
    protected $aclService;
    protected $authController;
    protected $formManager;
    protected $userManager;
    protected $wiki;

    public function __construct(
        AclService $aclService,
        AuthController $authController,
        FormManager $formManager,
        UserManager $userManager,
        Wiki $wiki
    ) {
        $this->aclService = $aclService;
        $this->authController = $authController;
        $this->formManager = $formManager;
        $this->userManager = $userManager;
        $this->wiki = $wiki;
    }

    // TODO remove this method and use YesWiki::HasAccess
    public function isAllowed($action = 'saisie_fiche', $ownerId = ''): bool
    {
        $loggedUserName = $this->authController->getLoggedUserName();
        $isOwner = $ownerId === $loggedUserName || $ownerId === '';

        // Admins are allowed all actions
        if ($this->userManager->isInGroup('admins')) {
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

            case 'valider_fiche':
            case 'saisie_formulaire':
            case 'saisie_liste':
            default:
                return false;
        }
    }

    /**
     * Teste les droits d'acces champ par champ du contenu d'un fiche bazar
     * Si utilisateur connecte est  proprietaire ou adminstrateur : acces a tous les champs
     * Sinon ne sont retournes que les champs dont les droits d'acces sont compatibles.
     * Introduction du droit % : seul le proprietaire peut acceder.
     *
     * @param array       $page
     * @param string      $tag
     * @param string|null $userNameForCheckingACL username used to check ACL, if empty, uses en the connectd user
     *
     * @return array $page
     */
    public function checkAcls($page, $tag, ?string $userNameForCheckingACL = null)
    {
        if ($this->wiki->UserIsAdmin($userNameForCheckingACL) || $this->isPageOwner($page, $userNameForCheckingACL)) {
            // Pas de controle si proprietaire ou administrateur
            return $page;
        }
        if ($page) {
            $valjson = $page['body'];
            $valeur = json_decode($valjson, true);

            if ($valeur) {
                $form = $this->formManager->getOne($valeur['id_typeannonce']);
                if ($form) {
                    $fieldname = [];
                    foreach ($form['prepared'] as $field) {
                        if ($field instanceof BazarField
                             && !$field->canRead(['id_fiche' => $tag], $userNameForCheckingACL)
                        ) {
                            $fieldname[] = $field->getPropertyName();
                        }
                    }
                    if (count($fieldname) > 0) {
                        foreach ($fieldname as $field) {
                            $valeur[$field] = '';
                            // on vide le champ
                        }
                        //$valeur = array_map(function($value){
                        //     return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                        // }, $valeur);
                        $page['body'] = json_encode($valeur);
                    }
                }
            }
        }

        return $page;
    }

    protected function isPageOwner($page, ?string $userName = null): bool
    {
        if (!empty($userName)) {
            // check if userName is owner
            return $page['owner'] === $userName;
        }
        // check if user is logged in
        if (!$this->authController->getLoggedUser()) {
            return false;
        }
        // check if user is owner
        if ($page['owner'] == $this->authController->getLoggedUserName()) {
            return true;
        }

        return false;
    }

    /**
     * sanitize data for correspondance.
     *
     * @param string $fieldName
     *
     * @return $value value or empty string
     */
    public function isFieldDataAuthorizedForCorrespondance(?array $page, ?array $entry, $fieldName)
    {
        if (!$this->wiki->UserIsAdmin()
                && !$this->isPageOwner($page)
                && !empty($fieldName)
                && isset($entry[$fieldName])
                && !empty($entry['id_typeannonce'])) {
            $formId = $entry['id_typeannonce'];
            $field = $this->formManager->findFieldFromNameOrPropertyName($fieldName, $formId);
            if (!empty($field) && $field instanceof EmailField && $field->getShowContactForm()) {
                return '';
            }
        }

        return (empty($fieldName) || !isset($entry[$fieldName])) ? '' : $entry[$fieldName];
    }
}
