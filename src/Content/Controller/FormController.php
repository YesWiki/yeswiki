<?php

namespace YesWiki\Content\Controller;

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\FieldRole;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Field\MapField;
use YesWiki\Content\Service\ContentCreator;
use YesWiki\Content\Service\EntryDisplay;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\FormPropertiesService;
use YesWiki\Content\Service\IcalFormatter;
use YesWiki\Core\YesWikiController;
use YesWiki\Federation\Service\ActivityPubService;
use YesWiki\Federation\Service\WebfingerService;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Identity\Service\Guard;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Search\Service\SearchIndexQuery;

class FormController extends YesWikiController
{
    protected $csrfTokenChecker;
    protected $formManager;
    protected $hibernationService;
    protected $activityPubService;
    protected $webfingerService;

    public function __construct(FormManager $formManager, HibernationService $hibernationService, CsrfTokenChecker $csrfTokenChecker, ActivityPubService $activityPubService, WebfingerService $webfingerService)
    {
        $this->csrfTokenChecker = $csrfTokenChecker;
        $this->formManager = $formManager;
        $this->hibernationService = $hibernationService;
        $this->activityPubService = $activityPubService;
        $this->webfingerService = $webfingerService;
    }

    public function displayAll($message)
    {
        $forms = $this->formManager->getAll();

        $values = [];
        if (is_array($forms)) {
            foreach ($forms as $form) {
                $values[$form['id']]['title'] = $form['label'];
                $values[$form['id']]['description'] = $form['description'];
                $values[$form['id']]['canEdit'] = !$this->hibernationService->isWikiHibernated() && $this->getService(Guard::class)->isAllowed('saisie_formulaire');
                $values[$form['id']]['canDelete'] = !$this->hibernationService->isWikiHibernated() && $this->getService(AclService::class)->isAdmin();
                $values[$form['id']]['isSemantic'] = !empty($form['sem_template']);
                $values[$form['id']]['isActivityPubEnabled'] = $form['activitypub_enable'] === '1';
                $values[$form['id']]['isGeo'] = !empty(array_filter($form['prepared'], function ($field) {
                    return $field instanceof MapField;
                }));
                $values[$form['id']]['isDate'] = $this->getService(IcalFormatter::class)->isICALForm($form);
                $values[$form['id']]['bookmarklet'] = $form['entry_bookmarklet'] ?? null;
                // core's own Content types are listed apart from a webmaster's forms:
                // they describe the wiki's pages, accounts and files rather than data
                // someone designed, and they cannot be emptied or deleted (ticket 10)
                $contentType = $form[ContentTypeSchema::CONTENT_TYPE] ?? null;
                $values[$form['id']]['isSystem'] = ContentTypeSchema::isBuiltIn($contentType);
                $values[$form['id']]['contentType'] = $contentType;
                // a built-in type's form creates Content like any other form (ticket 13)
                $values[$form['id']]['canCreateContent'] = ContentCreator::supports($contentType);
            }
        }

        $systemForms = array_filter($values, fn ($form) => $form['isSystem']);
        // in the order the types are declared (Page, User, File) rather than by form id,
        // which is just whatever order the migration happened to create them in
        $declaredOrder = array_flip(ContentTypeSchema::types());
        uasort(
            $systemForms,
            fn ($a, $b) => ($declaredOrder[$a['contentType']] ?? PHP_INT_MAX) <=> ($declaredOrder[$b['contentType']] ?? PHP_INT_MAX)
        );

        // how much Content each form holds and when it last changed, in one grouped read
        // of the search index rather than a query per form
        $stats = $this->getService(SearchIndexQuery::class)->contentStats();
        $withStats = function (array $forms) use ($stats): array {
            foreach ($forms as $id => $form) {
                $isSystem = (bool)($form['isSystem'] ?? false);
                $found = $isSystem
                    ? ($stats['byType'][(string)($form['contentType'] ?? '')] ?? null)
                    : ($stats['byForm'][(string)$id] ?? null);
                // No rows means none of that Content exists -- but only once the index has
                // been built at all. Before that (a fresh install, or between the migration
                // and the first reindex) every form would read "0", which is a lie where
                // "not counted yet" is the truth, so a wholly empty index says nothing.
                $forms[$id]['stats'] = $found ?? [
                    'count' => $stats['total'] > 0 ? 0 : null,
                    'last' => '',
                ];
            }

            return $forms;
        };

        return $this->render('@core/forms/forms_list.twig', [
            'message' => $message,
            'systemForms' => $withStats($systemForms),
            'forms' => $withStats(array_filter($values, fn ($form) => !$form['isSystem'])),
            'userIsAdmin' => $this->getService(AclService::class)->isAdmin(),
            'isWikiHibernated' => $this->hibernationService->isWikiHibernated(),
        ]);
    }

    /**
     * The form designer (javascripts/form-builder/) is pure JS and reads its labels
     * from wiki.lang; its keys live in the main PHP catalogs, so push them into the
     * javascript catalog when rendering the designer page.
     */
    private function loadDesignerTranslations(): void
    {
        $prefixes = ['BAZ_FORM_', 'FORM_BUILDER_', 'FORM_EDIT_', 'BAZ_REACTIONS_', 'BAZAR_VIDEO_'];
        $names = [
            'BAZ_ACTIVATE_COMMENTS', 'BAZ_ACTIVATE_COMMENTS_HINT', 'BAZ_ACTIVATE_REACTIONS',
            'BAZAR_URL_DISPLAY_VIDEO', 'BAZ_BOOKMARKLET_HINT', 'BAZ_FILEFIELD_FILE',
            'GEOLOCATER_GROUP_GEOLOCATIZATION', 'GEOLOCATER_GROUP_GEOLOCATIZATION_HINT',
            'EVERYONE', 'IDENTIFIED_USERS', 'MEMBER_OF_GROUP',
            'NO', 'YES', 'LEFT', 'RIGHT', 'PRIMARY', 'SECONDARY', 'NORMAL_F', 'SMALL_F',
        ];
        $designerKeys = array_filter(
            $GLOBALS['translations'] ?? [],
            function ($key) use ($prefixes, $names) {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($key, $prefix)) {
                        return true;
                    }
                }

                return in_array($key, $names, true);
            },
            ARRAY_FILTER_USE_KEY,
        );
        $this->getService(LanguageService::class)->loadTranslations($designerKeys, true);
    }

    /**
     * Massages the form-edit POST into the stored entry_* property shapes (ADR-0010):
     * checkbox-gated nested objects become arrays-or-null, empty sub-values are
     * compacted away, the comments toggle becomes a real boolean. A null/empty value
     * means "cleared" -- FormManager::update() removes the property from the body.
     */
    private function normalizeFormPropertiesPost(array $data): array
    {
        $data['entry_permit_activate_comments'] = !empty($data['entry_permit_activate_comments']);

        $metadatas = array_filter(array_map(function ($value) {
            return trim((string)$value);
        }, (array)($data['entry_metadatas'] ?? [])), function ($value) {
            return $value !== '';
        });
        $data['entry_metadatas'] = $metadatas ?: null;

        $data[FieldRole::FORM_PROPERTY] = FieldRole::normalizeMap($data[FieldRole::FORM_PROPERTY] ?? null);

        foreach (['entry_creates_user', 'entry_bookmarklet'] as $property) {
            if (empty($data[$property . '_enable'])) {
                $data[$property] = null;
            } else {
                $config = array_filter(array_map(function ($value) {
                    return trim((string)$value);
                }, (array)($data[$property] ?? [])), function ($value) {
                    return $value !== '';
                });
                $data[$property] = $config ?: null;
            }
            unset($data[$property . '_enable']);
        }

        return $data;
    }

    public function create()
    {
        if ($this->getService(AclService::class)->isAdmin()) {
            $form = null;
            $post = $this->getRequest()->request;
            if ($post->has('valider')) {
                $form = $this->formManager->getFromRawData($post->all());
                if ($this->formIsValid($form)) {
                    $this->formManager->create($this->normalizeFormPropertiesPost($post->all()));

                    /* mrflos : i think this is not used */
                    /* if ($this->activityPubService->isEnabled($form)) { */
                    /*     $this->activityPubService->postCreateActivity($form); */
                    /* } */

                    return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'BAZ_NOUVEAU_FORMULAIRE_ENREGISTRE'], false));
                }
            }

            $this->loadDesignerTranslations();

            return $this->render('@core/forms/forms_form.twig', [
                'form' => $form,
                'formAndListIds' => $this->getService(EntryDisplay::class)->formAndListNames(),
                'groupsList' => $this->getGroupsListIfEnabled(),
                'onlyOneEntryOptionAvailable' => $this->formManager->isAvailableOnlyOneEntryOption(),
                'lockedFields' => ContentTypeSchema::lockedFieldNames($form[ContentTypeSchema::CONTENT_TYPE] ?? null),
                'entryOnlyPropertiesAvailable' => ContentTypeSchema::acceptsEntryOnlyProperties($form[ContentTypeSchema::CONTENT_TYPE] ?? null),
                'fieldRoles' => $this->fieldRolesForDesigner($form),
            ]);
        }

        return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'BAZ_AUTH_NEEDED'], false));
    }

    public function update($id)
    {
        if ($this->getService(Guard::class)->isAllowed('saisie_formulaire')) {
            $form = $this->formManager->getOne($id);
            $post = $this->getRequest()->request;
            if ($post->has('valider')) {
                $form = $this->formManager->getFromRawData($post->all());
                if ($this->formIsValid($form)) {
                    $this->formManager->update($this->normalizeFormPropertiesPost($post->all()));

                    return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_MODIFIE'], false));
                }
            }

            $this->loadDesignerTranslations();

            return $this->render('@core/forms/forms_form.twig', [
                'form' => $form,
                'formAndListIds' => $this->getService(EntryDisplay::class)->formAndListNames(),
                'groupsList' => $this->getGroupsListIfEnabled(),
                'onlyOneEntryOptionAvailable' => $this->formManager->isAvailableOnlyOneEntryOption() && $this->formManager->isAvailableOnlyOneEntryMessage(),
                'lockedFields' => ContentTypeSchema::lockedFieldNames($form[ContentTypeSchema::CONTENT_TYPE] ?? null),
                'entryOnlyPropertiesAvailable' => ContentTypeSchema::acceptsEntryOnlyProperties($form[ContentTypeSchema::CONTENT_TYPE] ?? null),
                'fieldRoles' => $this->fieldRolesForDesigner($form),
            ]);
        }

        return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS'], false));
    }

    private function formIsValid($form)
    {
        // the entry title is computed from entry_title_template (ADR-0010): at least
        // one of its {{field}} references must exist in the template, otherwise every
        // entry title would come out empty
        $titleTemplate = trim((string)($this->getRequest()->request->get('entry_title_template') ?? $form['entry_title_template'] ?? ''));
        if ($titleTemplate === '') {
            $titleTemplate = FormPropertiesService::DEFAULT_TITLE_TEMPLATE;
        }
        $referenced = $this->getService(FormPropertiesService::class)->referencedFieldNames($titleTemplate);
        $fieldNames = array_filter(array_map(function ($field) {
            return $field->getPropertyName();
        }, $form['prepared']));
        $referencesExistingField = !empty(array_intersect($referenced, $fieldNames));
        if (!$referencesExistingField) {
            Flash::error(_t('BAZ_FORM_NEED_TITLE'));

            return false;
        }

        return $this->rolesAreValid($form);
    }

    /**
     * The role selects the designer offers: every known role, the field types that can
     * play it, and whatever this form has explicitly mapped (ticket 11).
     *
     * @param array<string, mixed>|null $form
     *
     * @return list<array<string, mixed>>
     */
    private function fieldRolesForDesigner(?array $form): array
    {
        $current = FieldRole::normalizeMap($form[FieldRole::FORM_PROPERTY] ?? null);

        return array_map(fn (string $role) => [
            'name' => $role,
            'property' => FieldRole::FORM_PROPERTY,
            'label' => 'FORM_EDIT_FIELD_ROLE_' . strtoupper($role),
            'types' => FieldRole::compatibleTypes($role),
            'current' => $current[$role] ?? '',
        ], FieldRole::all());
    }

    /**
     * A submitted role map has to name fields the form actually has, of a type that can
     * play the role, and no two roles may name the same field (ticket 11).
     *
     * FieldRole::normalizeMap() drops what it cannot use, which is right for storage and
     * wrong for a designer: a webmaster who picks an impossible mapping should be told,
     * not quietly given the type default back.
     *
     * @param array<string, mixed> $form
     */
    private function rolesAreValid(array $form): bool
    {
        $submitted = $this->getRequest()->request->all()[FieldRole::FORM_PROPERTY] ?? null;
        if (!is_array($submitted)) {
            return true;
        }

        /** @var array<string, BazarField> $byName */
        $byName = [];
        foreach ($form['prepared'] ?? [] as $field) {
            if ($field instanceof BazarField && $field->getPropertyName()) {
                $byName[$field->getPropertyName()] = $field;
            }
        }

        $claimed = [];
        foreach ($submitted as $role => $fieldName) {
            $fieldName = is_string($fieldName) ? trim($fieldName) : '';
            if ($fieldName === '' || !FieldRole::isKnown((string)$role)) {
                continue;
            }

            $field = $byName[$fieldName] ?? null;
            if ($field === null) {
                Flash::error(_t('BAZ_FORM_ROLE_UNKNOWN_FIELD', ['role' => $role, 'field' => $fieldName]));

                return false;
            }

            $compatible = FieldRole::compatibleTypes((string)$role);
            if (!empty($compatible) && !in_array($field->getType(), $compatible, true)) {
                Flash::error(_t('BAZ_FORM_ROLE_INCOMPATIBLE_FIELD', [
                    'role' => $role,
                    'field' => $fieldName,
                    'types' => implode(', ', $compatible),
                ]));

                return false;
            }

            if (isset($claimed[$fieldName])) {
                Flash::error(_t('BAZ_FORM_ROLE_FIELD_TWICE', [
                    'field' => $fieldName,
                    'roles' => $claimed[$fieldName] . ', ' . $role,
                ]));

                return false;
            }
            $claimed[$fieldName] = $role;
        }

        return true;
    }

    public function delete($id)
    {
        if ($this->getService(AclService::class)->isAdmin()) {
            try {
                $this->csrfTokenChecker->checkToken('main', 'POST', 'confirmDeleteToken', false);
                // delete() removes the form's entries itself
                $this->formManager->delete($id);

                return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_ET_FICHES_SUPPRIMES'], false));
            } catch (TokenNotFoundException $th) {
                $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => $th->getMessage()], false));
            }
        } else {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS'], false));
        }
    }

    public function empty($id)
    {
        if ($this->getService(AclService::class)->isAdmin()) {
            try {
                $this->csrfTokenChecker->checkToken('main', 'POST', 'confirmEmptyToken', false);
                $this->formManager->clear($id);

                return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_VIDE'], false));
            } catch (TokenNotFoundException $th) {
                $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => $th->getMessage()], false));
            }
        } else {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS'], false));
        }
    }

    public function clone($id)
    {
        if ($this->getService(Guard::class)->isAllowed('saisie_formulaire')) {
            $this->formManager->clone($id);

            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'BAZ_FORM_CLONED'], false));
        }

        return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'BAZ_AUTH_NEEDED'], false));
    }

    public function manageAbonnements($id)
    {
        $form = $this->formManager->getOne($id);

        $post = $this->getRequest()->request;
        if ($post->has('actor_handle')) {
            if (!$this->getService(AclService::class)->isAdmin() || $this->hibernationService->isWikiHibernated()) {
                return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS', 'formid' => $id], false));
            }

            $actorHandle = $post->get('actor_handle');
            $recipientUri = str_starts_with($actorHandle, 'http') ? $actorHandle : $this->webfingerService->getRemoteActor($actorHandle);

            $this->activityPubService->postActivity(['type' => 'Follow', 'object' => $recipientUri, 'to' => $recipientUri], $form);

            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_FOLLOWING_ADDED', 'formid' => $id], false));
        }

        $followers = $this->activityPubService->getFollowers($form);
        $following = $this->activityPubService->getFollowing($form);

        $domain = parse_url($this->getService(UrlFormatter::class)->getBaseUrl(), PHP_URL_HOST);

        return $this->render('@core/forms/abonnements.twig', [
            'message' => $this->getRequest()->query->get('msg'),
            'form' => $form,
            'domain' => $domain,
            'followers' => $followers,
            'following' => $following,
        ]);
    }

    public function addFollowing($id, $actorUri)
    {
        if (!$this->getService(AclService::class)->isAdmin() || $this->hibernationService->isWikiHibernated()) {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS', 'formid' => $id], false));
        }

        $form = $this->formManager->getOne($id);

        $this->activityPubService->postActivity(['type' => 'Follow', 'object' => $actorUri, 'to' => $actorUri], $form);

        return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_FOLLOWING_ADDED', 'formid' => $id], false));
    }

    public function removeFollowing($id, $actorUri)
    {
        if (!$this->getService(AclService::class)->isAdmin() || $this->hibernationService->isWikiHibernated()) {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS', 'formid' => $id], false));
        }

        $form = $this->formManager->getOne($id);
        $formActorUri = $this->activityPubService->getFormActorUri($form);

        $this->activityPubService->removeFollowing($form, $actorUri);

        $this->activityPubService->postActivity([
            'type' => 'Undo',
            'object' => [
                'type' => 'Follow',
                'actor' => $formActorUri,
                'object' => $actorUri,
                'to' => $actorUri,
            ],
            'to' => $actorUri,
        ], $form);

        return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_FOLLOWING_REMOVED', 'formid' => $id], false));
    }

    public function syncActorPosts($id, $actorUri)
    {
        if (!$this->getService(AclService::class)->isAdmin() || $this->hibernationService->isWikiHibernated()) {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS', 'formid' => $id], false));
        }

        $form = $this->formManager->getOne($id);
        $stats = $this->activityPubService->syncActorPosts($actorUri, $form);

        Flash::success(sprintf(
            _t('BAZ_SYNC_COMPLETE'),
            $stats['created'],
            $stats['updated'],
            $stats['deleted'],
        ));

        return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'formid' => $id], false));
    }

    public function removeFollower($id, $actorUri)
    {
        if (!$this->getService(AclService::class)->isAdmin() || $this->hibernationService->isWikiHibernated()) {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS', 'formid' => $id], false));
        }

        $form = $this->formManager->getOne($id);
        $formActorUri = $this->activityPubService->getFormActorUri($form);

        $this->activityPubService->removeFollower($form, $actorUri);

        $this->activityPubService->postActivity([
            'type' => 'Undo',
            'object' => [
                'type' => 'Accept',
                'actor' => $formActorUri,
                'object' => [
                    'type' => 'Follow',
                    'actor' => $formActorUri,
                    'object' => $actorUri,
                    'to' => $actorUri,
                ],
                'to' => $actorUri,
            ],
            'to' => $actorUri,
        ], $form);

        return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_FOLLOWER_REMOVED', 'formid' => $id], false));
    }

    private function getGroupsListIfEnabled(): ?array
    {
        return $this->getService(AclService::class)->isAdmin()
            ? $this->getService(GroupOperationsService::class)->getAll()
            : null;
    }
}
