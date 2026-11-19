<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\EmailField;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Entity\Translations;
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

    public function isAllowed($action = 'saisie_fiche', $ownerId = ''): bool
    {
        $loggedUserName = $this->authController->getLoggedUserName();
        $isOwner = $ownerId === $loggedUserName || $ownerId === '';

        if ($this->userManager->isInGroup('admins')) {
            return true;
        }

        switch ($action) {
            case 'supp_fiche':
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

    /** $page with every field the user may not read blanked, together with its translations. */
    public function checkAcls($page, $tag, ?string $userNameForCheckingACL = null)
    {
        if (!$page || $this->wiki->UserIsAdmin($userNameForCheckingACL) || $this->isPageOwner($page, $userNameForCheckingACL)) {
            return $page;
        }
        $valeur = json_decode($page['body'], true);
        if (!is_array($valeur) || empty($valeur['id_typeannonce'])) {
            return $page;
        }
        $form = $this->formManager->getOne($valeur['id_typeannonce']);
        if (!$form) {
            return $page;
        }
        $unreadable = [];
        foreach ($form['prepared'] as $field) {
            if ($field instanceof BazarField && !$field->canRead(['id_fiche' => $tag], $userNameForCheckingACL)) {
                $unreadable[] = $field->getPropertyName();
            }
        }
        if ($unreadable === []) {
            return $page;
        }
        foreach ($unreadable as $propertyName) {
            $valeur[$propertyName] = '';
            $valeur = Translations::withoutPath($valeur, (string)$propertyName);
        }
        $page['body'] = json_encode(Translations::forEncoding($valeur));

        return $page;
    }

    protected function isPageOwner($page, ?string $userName = null): bool
    {
        if (!empty($userName)) {
            return $page['owner'] === $userName;
        }
        if (!$this->authController->getLoggedUser()) {
            return false;
        }
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
