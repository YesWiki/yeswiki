<?php

namespace YesWiki\Identity\Service;

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Field\EmailField;
use YesWiki\Content\Service\FormManager;

class Guard
{
    protected $aclService;
    protected $authenticationService;
    protected $formManager;
    protected $userManager;

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

    // TODO remove this method and use YesWiki::HasAccess
    public function isAllowed($action = 'saisie_fiche', $ownerId = ''): bool
    {
        $loggedUserName = $this->authenticationService->getLoggedUserName();
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
        if ($this->aclService->isAdmin($userNameForCheckingACL) || $this->isPageOwner($page, $userNameForCheckingACL)) {
            // Pas de controle si proprietaire ou administrateur
            return $page;
        }
        if ($page) {
            // PageManager::getAll() hands over raw `pages` rows, whose body is JSON *text*
            // (ticket 09) -- so this read `$body['form_id']` on a string, which under PHP 8
            // is a fatal "cannot access offset of type string on string" and took out every
            // screen listing pages. Decoded here and re-encoded below, so the caller gets
            // back the shape it passed in.
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
                            // on vide le champ
                        }
                        $page['body'] = is_array($rawBody) ? $body : PageBody::encode($body);
                    }
                }
            }
        }

        return $page;
    }

    // password: never surfaced via any generic page-read path (current view, history,
    // diffs, exports), not even to the account's own owner or an admin -- no legitimate
    // UI ever needs to display a raw hash back to anyone (password changes go through
    // AuthenticationService::setPassword(), not by reading the old hash). activation_status/
    // activation_key (ticket 07, accountactivationbyemail absorbed into core) get the same
    // treatment -- AccountActivationService's own internal (ACL-bypassing) reads are how
    // the admin users-table UI and the activation gate itself see the true values, the
    // same pattern password already uses for auth. email and the account preference
    // fields: hidden from everyone except the account owner and admins.
    private const USER_ALWAYS_HIDDEN_FIELDS = ['password', 'activation_status', 'activation_key'];
    private const USER_OWNER_OR_ADMIN_ONLY_FIELDS = ['email', 'revisioncount', 'changescount', 'doubleclickedit', 'show_comments'];

    /**
     * Redacts a users-type Content page's sensitive fields for display, the same way
     * checkAcls() does for bazar entries -- and, like checkAcls(), applies uniformly
     * whether $page is the current revision or a historical one, since both flow through
     * this same call from PageManager::checkEntriesACL().
     *
     * @param array       $page
     * @param string      $tag
     * @param string|null $userNameForCheckingACL username used to check ACL, if empty uses the connected user
     *
     * @return array $page
     */
    public function checkUserAcls($page, $tag, ?string $userNameForCheckingACL = null)
    {
        if (empty($page['body'])) {
            return $page;
        }
        // same as checkAcls(): a raw `pages` row carries its body as JSON text. Returning
        // early on one -- which is what this did -- skipped the redaction entirely, and the
        // first field in USER_ALWAYS_HIDDEN_FIELDS is the password hash.
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
     * The hardcoded lists above stay as a floor that a template cannot weaken -- the
     * password hash is never readable by anyone, whatever a webmaster sets (ADR-0003).
     * This adds the other direction: a field a webmaster added to the User form, or one
     * whose read ACL they tightened, is redacted by the same mechanism bazar entries use.
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

    protected function isPageOwner($page, ?string $userName = null): bool
    {
        if (!empty($userName)) {
            // check if userName is owner
            return $page['owner'] === $userName;
        }
        // check if user is logged in
        if (!$this->authenticationService->getLoggedUser()) {
            return false;
        }
        // check if user is owner
        if ($page['owner'] == $this->authenticationService->getLoggedUserName()) {
            return true;
        }

        return false;
    }

    /**
     * sanitize data for a field mapping: the field value, or an empty string
     * when the visitor may not read it.
     *
     * @param string $fieldName
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
