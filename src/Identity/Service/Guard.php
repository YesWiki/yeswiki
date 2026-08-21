<?php

namespace YesWiki\Identity\Service;

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Field\EmailField;
use YesWiki\Content\Service\FormManager;

class Guard
{
    protected AclService $aclService;
    protected AuthenticationService $authenticationService;
    protected FormManager $formManager;
    protected UserManager $userManager;

    public function __construct(
        AclService $aclService,
        AuthenticationService $authenticationService,
        FormManager $formManager,
        UserManager $userManager
    ) {
        $this->aclService = $aclService;
        $this->authenticationService = $authenticationService;
        $this->formManager = $formManager;
        $this->userManager = $userManager;
    }

    public function isAllowed(string $action = 'saisie_fiche', string $ownerId = ''): bool
    {
        $loggedUserName = $this->authenticationService->getLoggedUserName();
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

    /**
     * Teste les droits d'acces champ par champ du contenu d'un fiche bazar Si utilisateur connecte est proprietaire ou adminstrateur : acces a tous les champs Sinon ne sont retournes que les champs dont les droits d'acces sont compatibles.
     *
     * @param array<string, mixed> $page
     * @param string               $tag
     * @param string|null          $userNameForCheckingACL username used to check ACL, if empty, uses en the connectd user
     *
     * @return array<string, mixed> $page
     */
    public function checkAcls($page, $tag, ?string $userNameForCheckingACL = null)
    {
        if ($this->aclService->isAdmin($userNameForCheckingACL) || $this->isPageOwner($page, $userNameForCheckingACL)) {
            return $page;
        }
        if ($page) {
            $rawBody = $page['body'] ?? null;
            $body = is_array($rawBody) ? $rawBody : PageBody::decode(is_string($rawBody) ? $rawBody : null);

            if ($body) {
                $form = $this->formManager->getOne($body['form_id']);
                if ($form) {
                    $redactedFields = [];
                    foreach ($form['prepared'] as $field) {
                        if ($field instanceof BazarField
                             && !$field->canRead(['tag' => $tag], $userNameForCheckingACL)
                        ) {
                            $redactedFields[] = $field->getPropertyName();
                        }
                    }
                    if (count($redactedFields) > 0) {
                        foreach ($redactedFields as $field) {
                            $body[$field] = '';
                        }
                        $page['body'] = is_array($rawBody) ? $body : PageBody::encode($body);
                    }
                }
            }
        }

        return $page;
    }

    private const USER_ALWAYS_HIDDEN_FIELDS = ['password', 'activation_status', 'activation_key'];
    private const USER_OWNER_OR_ADMIN_ONLY_FIELDS = ['email', 'revisioncount', 'changescount', 'doubleclickedit', 'show_comments'];

    /**
     * Redacts a users-type Content page's sensitive fields for display, the same way checkAcls() does for bazar entries -- and, like checkAcls(), applies uniformly whether $page is the current revision or a historical one, since both flow through this same call from PageManager::checkEntriesACL().
     *
     * @param array<string, mixed> $page
     * @param string               $tag
     * @param string|null          $userNameForCheckingACL username used to check ACL, if empty uses the connected user
     *
     * @return array<string, mixed> $page
     */
    public function checkUserAcls($page, $tag, ?string $userNameForCheckingACL = null)
    {
        if (empty($page['body'])) {
            return $page;
        }

        $rawBody = $page['body'];
        $body = is_array($rawBody) ? $rawBody : PageBody::decode(is_string($rawBody) ? $rawBody : null);
        if (!$body) {
            return $page;
        }

        $fieldsToHide = self::USER_ALWAYS_HIDDEN_FIELDS;
        if (!$this->aclService->isAdmin($userNameForCheckingACL) && !$this->isPageOwner($page, $userNameForCheckingACL)) {
            $fieldsToHide = array_merge($fieldsToHide, self::USER_OWNER_OR_ADMIN_ONLY_FIELDS);
        }
        $fieldsToHide = array_merge($fieldsToHide, $this->userFieldsFailingTheirAcl($page, $tag, $userNameForCheckingACL));

        $modified = false;
        foreach ($fieldsToHide as $field) {
            if (array_key_exists($field, $body) && $body[$field] !== '') {
                $body[$field] = '';
                $modified = true;
            }
        }
        if ($modified) {
            $page['body'] = is_array($rawBody) ? $body : PageBody::encode($body);
        }

        return $page;
    }

    /**
     * The User form's own fields whose Field ACL denies this reader (ticket 10).
     *
     * @param array<string, mixed> $page
     *
     * @return list<string>
     */
    private function userFieldsFailingTheirAcl(array $page, string $tag, ?string $userNameForCheckingACL): array
    {
        $form = $this->formManager->getByContentType(ContentTypeSchema::TYPE_USER);
        if (empty($form['prepared'])) {
            return [];
        }

        $denied = [];
        foreach ($form['prepared'] as $field) {
            if (!$field instanceof BazarField) {
                continue;
            }
            $propertyName = $field->getPropertyName();
            if (empty($propertyName)) {
                continue;
            }
            if (!$field->canRead(['tag' => $tag], $userNameForCheckingACL)) {
                $denied[] = $propertyName;
            }
        }

        return $denied;
    }

    /**
     * @param array<string, mixed>|null $page
     */
    protected function isPageOwner($page, ?string $userName = null): bool
    {
        if (empty($page['owner'])) {
            return false;
        }

        if (!empty($userName)) {
            return $page['owner'] === $userName;
        }

        if (!$this->authenticationService->getLoggedUser()) {
            return false;
        }

        if ($page['owner'] == $this->authenticationService->getLoggedUserName()) {
            return true;
        }

        return false;
    }

    /**
     * sanitize data for a field mapping: the field value, or an empty string when the visitor may not read it.
     *
     * @param array<string, mixed>|null $page
     * @param array<string, mixed>|null $entry
     * @param string                    $fieldName
     *
     * @return mixed the raw field value, or '' when it is hidden from this visitor
     */
    public function isFieldDataAuthorizedForFieldMapping(?array $page, ?array $entry, $fieldName)
    {
        if (!$this->aclService->isAdmin()
                && !$this->isPageOwner($page)
                && !empty($fieldName)
                && isset($entry[$fieldName])
                && !empty($entry['form_id'])) {
            $formId = $entry['form_id'];
            $field = $this->formManager->findFieldFromNameOrPropertyName($fieldName, $formId);
            if (!empty($field) && $field instanceof EmailField && $field->getShowContactForm()) {
                return '';
            }
        }

        return (empty($fieldName) || !isset($entry[$fieldName])) ? '' : $entry[$fieldName];
    }
}
