<?php

namespace YesWiki\Content\Controller;

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Action\BazarAction;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\FieldRole;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Field\FileContentField;
use YesWiki\Content\Field\PasswordField;
use YesWiki\Content\Service\EntryDisplay;
use YesWiki\Content\Service\ExportLinks;
use YesWiki\Content\Service\FieldRoleResolver;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\FormOverview;
use YesWiki\Content\Service\FormPropertiesService;
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
use YesWiki\Render\Entity\Presentation;
use YesWiki\Render\Service\Performer;
use YesWiki\Render\Service\PresentationCatalog;

class FormController extends YesWikiController
{
    /** What a form's own screen shows of its Content before paging. */
    public const ROWS_PER_PAGE = '50';

    /** The one presentation a form's own screen draws through another template: its admins want the datatable, not the Item table. */
    private const RENDER_AS = ['table' => 'tableau'];
    private const DEFAULT_DISPLAY = 'card';

    /** What the toolbar's sort offers, as the `field` and `order` the entry list takes. */
    public const SORTS = [
        'title:asc' => ['title', 'asc'],
        'title:desc' => ['title', 'desc'],
        'updated_at:desc' => ['updated_at', 'desc'],
        'updated_at:asc' => ['updated_at', 'asc'],
        'created_at:desc' => ['created_at', 'desc'],
        'created_at:asc' => ['created_at', 'asc'],
    ];

    protected CsrfTokenChecker $csrfTokenChecker;
    protected FormManager $formManager;
    protected HibernationService $hibernationService;
    protected ActivityPubService $activityPubService;
    protected WebfingerService $webfingerService;

    public function __construct(FormManager $formManager, HibernationService $hibernationService, CsrfTokenChecker $csrfTokenChecker, ActivityPubService $activityPubService, WebfingerService $webfingerService)
    {
        $this->csrfTokenChecker = $csrfTokenChecker;
        $this->formManager = $formManager;
        $this->hibernationService = $hibernationService;
        $this->activityPubService = $activityPubService;
        $this->webfingerService = $webfingerService;
    }

    /**
     * @param string|null $message
     *
     * @return string
     */
    public function displayAll($message)
    {
        return $this->render('@core/forms/forms_list.twig', [
            'message' => $message,
        ] + $this->getService(FormOverview::class)->all());
    }

    /**
     * A form's own screen, at its tag: what it holds, a way to add to it, and the bazar views over it (ticket 63).
     *
     * The bare tag lists the form's Content; `view`/`action` in the query name any other bazar view, rooted here instead of at the BazaR page.
     *
     * @param array<string, mixed> $form
     */
    public function show(array $form): string
    {
        $query = $this->getRequest()->query;
        $view = $query->get(BazarAction::URL_VIEW_PARAM);
        $action = $query->get(BazarAction::URL_ACTION_PARAM);
        $urlFormatter = $this->getService(UrlFormatter::class);

        if ($view === BazarAction::VIEW_FORMS && $action === BazarAction::ACTION_FORM_EDIT) {
            return $this->getService(Redirector::class)->redirect($urlFormatter->href('edit', '', [], false));
        }

        $catalog = $this->getService(PresentationCatalog::class);
        $fitting = $catalog->fitting($form);
        $display = $query->get('display');
        if (!is_string($display) || !in_array($display, array_map(fn (Presentation $p) => $p->name, $fitting), true)) {
            $display = self::DEFAULT_DISPLAY;
        }
        $presentation = $catalog->get($display) ?? new Presentation($display, $display, Presentation::categoryIcon('card'), 'card', [], true);

        $performer = $this->getService(Performer::class);
        $body = $this->isListView($view, $action)
            ? $performer->run('entrylist', 'action', $this->listArguments($form, $presentation))
            : $performer->run('bazar', 'action', ['showmenu' => '0', 'id' => (string)$form['id']]);

        $overview = $this->getService(FormOverview::class)->one($form);
        $message = $query->get('msg');
        $keywords = $query->get('keywords');
        $sort = $query->get('sort');

        return $this->render('@core/forms/form_screen.twig', [
            'form' => $overview,
            'key' => $form['id'],
            'message' => is_string($message) && $message !== '' ? $message : null,
            'isListView' => $this->isListView($view, $action),
            'tag' => (string)($form['tag'] ?? ''),
            'display' => $display,
            'keywords' => is_string($keywords) ? $keywords : '',
            'sort' => is_string($sort) && isset(self::SORTS[$sort]) ? $sort : '',
            'switcher' => $catalog->switcherFor($form),
            'exports' => $this->getService(ExportLinks::class)->forForm($form, [
                'keywords' => is_string($keywords) ? $keywords : '',
                'field' => is_string($sort) ? (self::SORTS[$sort][0] ?? '') : '',
                'order' => is_string($sort) ? (self::SORTS[$sort][1] ?? '') : '',
                'facet' => $this->facetParameter(),
            ]),
            'switchParams' => $this->queryPairsExcept(['display', 'keywords', 'sort', 'pageID', 'wiki', 'view', 'action', 'id', (string)($form['tag'] ?? '')]),
            'listUrl' => $urlFormatter->href('', '', [], false),
            'body' => $body,
        ]);
    }

    /** The bare tag, the search form's own submit, and where the designer sends back after saving all mean "the list". */
    private function isListView(mixed $view, mixed $action): bool
    {
        if ($view === null) {
            return true;
        }
        if ($view === BazarAction::VIEW_FORMS) {
            return $action === null;
        }

        return $view === BazarAction::VIEW_SEARCH && in_array($action, [null, BazarAction::ACTION_SEARCH], true);
    }

    /** The checked facets as the export endpoints spell them: `field=value,value|field=value`. */
    private function facetParameter(): string
    {
        $facets = $this->getRequest()->query->all('facet');
        $parts = [];
        foreach ($facets as $name => $values) {
            $values = array_filter(array_map('strval', is_array($values) ? $values : [$values]), static fn (string $v): bool => $v !== '');
            if ($values !== []) {
                $parts[] = $name . '=' . implode(',', $values);
            }
        }

        return implode('|', $parts);
    }

    /**
     * The current query as name/value pairs, minus the ones a display switch sets itself, so switching keeps the search and the facets.
     *
     * @param list<string> $except
     *
     * @return list<array{name: string, value: string}>
     */
    private function queryPairsExcept(array $except): array
    {
        $pairs = [];
        $queryString = (string)parse_url($this->getRequest()->getRequestUri(), PHP_URL_QUERY);
        foreach (explode('&', $queryString) as $part) {
            if ($part === '') {
                continue;
            }
            [$name, $value] = array_pad(explode('=', $part, 2), 2, '');
            $name = urldecode($name);
            $bare = (string)preg_replace('/\[.*$/', '', $name);
            if (in_array($bare, $except, true) || strcasecmp($bare, $except[count($except) - 1]) === 0) {
                continue;
            }
            $pairs[] = ['name' => $name, 'value' => urldecode($value)];
        }

        return $pairs;
    }

    /**
     * How a form lists what it holds: fifty rows a page, facets above the list for its enum fields, export links that follow the search, cards by default, and a table of its input fields for a built-in type led by the title when no field already names the row -- its rows are pages, accounts or files, and a page's markup is not something to run forty times in a list.
     *
     * @param array<string, mixed> $form
     *
     * @return array<string, mixed>
     */
    private function listArguments(array $form, Presentation $presentation): array
    {
        $arguments = [
            'id' => (string)$form['id'],
            'template' => self::RENDER_AS[$presentation->name] ?? $presentation->name,
            'dynamic' => $presentation->shared ? 'false' : 'true',
            'search' => 'false',
            'shownumentries' => 'true',
            'pagination' => self::ROWS_PER_PAGE,
            'filterposition' => 'top',
            'resetfiltersbutton' => 'true',
            'showexportbuttons' => 'false',
            'displayfields' => $this->displayFieldsFor($form),
        ];
        $sort = $this->getRequest()->query->get('sort');
        if (is_string($sort) && isset(self::SORTS[$sort])) {
            [$arguments['field'], $arguments['order']] = self::SORTS[$sort];
        }
        $facets = [];
        $columns = [];
        foreach ($form['prepared'] ?? [] as $field) {
            if (!$field instanceof BazarField || $field instanceof PasswordField || $field instanceof FileContentField) {
                continue;
            }
            $name = $field->getPropertyName();
            if (empty($name)) {
                continue;
            }
            if ($field instanceof EnumField) {
                $facets[] = $name;
            }
            if ($name !== PageBody::CONTENT) {
                $columns[] = $name;
            }
        }
        if ($facets !== []) {
            $arguments['groups'] = implode(',', $facets);
        }
        if ($presentation->name !== 'table') {
            return $arguments;
        }
        $arguments += [
            'displayadmincol' => 'yes',
            'displaylastchangedate' => 'yes',
            'displayowner' => 'onlyadmins',
            'displayimagesasthumbnails' => 'true',
        ];
        if (!ContentTypeSchema::isBuiltIn($form[ContentTypeSchema::CONTENT_TYPE] ?? null)) {
            return $arguments;
        }
        if (!in_array(PageBody::TITLE, $columns, true) && ContentTypeSchema::tagMirrorField($form[ContentTypeSchema::CONTENT_TYPE] ?? null) === null) {
            array_unshift($columns, PageBody::TITLE);
        }

        return $arguments + ['columnfieldsids' => implode(',', $columns)];
    }

    /**
     * What a card or a list item shows of an entry, asked of the form's field roles; a page's markup is not a description.
     *
     * @param array<string, mixed> $form
     *
     * @return array<string, string>
     */
    private function displayFieldsFor(array $form): array
    {
        $roles = $this->getService(FieldRoleResolver::class);
        $slots = [
            'visual' => $roles->propertyName($form, FieldRole::IMAGE),
            'description' => $roles->propertyName($form, FieldRole::DESCRIPTION),
            'date' => $roles->propertyName($form, FieldRole::START_DATE),
        ];
        if ($slots['description'] === PageBody::CONTENT) {
            $slots['description'] = null;
        }

        return array_filter($slots, static fn (?string $name): bool => $name !== null && $name !== '');
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
     * Massages the form-edit POST into the stored entry_* property shapes (ADR-0010): checkbox-gated nested objects become arrays-or-null, empty sub-values are compacted away, the comments toggle becomes a real boolean.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
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

    /**
     * @return string
     */
    public function create()
    {
        if ($this->getService(AclService::class)->isAdmin()) {
            $form = null;
            $post = $this->getRequest()->request;
            if ($post->has('valider')) {
                $form = $this->formManager->getFromRawData($post->all());
                if ($this->formIsValid($form)) {
                    $this->formManager->create($this->normalizeFormPropertiesPost($post->all()));

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

    /**
     * @param int|string|null $id from the query string, so possibly absent
     *
     * @return string
     */
    public function update($id)
    {
        if ($id === null) {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'NOT_FOUND'], false));
        }

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

    /**
     * @param array<string, mixed> $form
     *
     * @return bool
     */
    private function formIsValid($form)
    {
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
     * A submitted role map has to name fields the form actually has, of a type that can play the role, and no two roles may name the same field (ticket 11).
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

    /**
     * @param int|string|null $id from the query string, so possibly absent
     *
     * @return string
     */
    public function delete($id)
    {
        if ($id === null) {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'NOT_FOUND'], false));
        }

        if ($this->getService(AclService::class)->isAdmin()) {
            try {
                $this->csrfTokenChecker->checkToken('main', 'POST', 'confirmDeleteToken', false);
                $this->formManager->delete($id);

                return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_ET_FICHES_SUPPRIMES'], false));
            } catch (TokenNotFoundException $th) {
                return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => $th->getMessage()], false));
            }
        } else {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS'], false));
        }
    }

    /**
     * @param int|string|null $id from the query string, so possibly absent
     *
     * @return string
     */
    public function empty($id)
    {
        if ($id === null) {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'NOT_FOUND'], false));
        }

        if ($this->getService(AclService::class)->isAdmin()) {
            try {
                $this->csrfTokenChecker->checkToken('main', 'POST', 'confirmEmptyToken', false);
                $this->formManager->clear($id);

                return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_VIDE'], false));
            } catch (TokenNotFoundException $th) {
                return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => $th->getMessage()], false));
            }
        } else {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS'], false));
        }
    }

    /**
     * @param int|string|null $id from the query string, so possibly absent
     *
     * @return string
     */
    public function clone($id)
    {
        if ($id === null) {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'NOT_FOUND'], false));
        }

        if ($this->getService(Guard::class)->isAllowed('saisie_formulaire')) {
            $this->formManager->clone($id);

            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'BAZ_FORM_CLONED'], false));
        }

        return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'formulaire', 'msg' => 'BAZ_AUTH_NEEDED'], false));
    }

    /**
     * @param int|string|null $id from the query string, so possibly absent
     *
     * @return string
     */
    public function manageAbonnements($id)
    {
        $form = $id === null ? null : $this->formManager->getOne($id);
        if ($form === null) {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'NOT_FOUND', 'formid' => $id], false));
        }

        $post = $this->getRequest()->request;
        if ($post->has('actor_handle')) {
            if (!$this->getService(AclService::class)->isAdmin() || $this->hibernationService->isWikiHibernated()) {
                return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS', 'formid' => $id], false));
            }

            $actorHandle = (string)$post->get('actor_handle');
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

    /**
     * @param int|string|null $id       from the query string, so possibly absent
     * @param string|null     $actorUri
     *
     * @return string
     */
    public function addFollowing($id, $actorUri)
    {
        if (!$this->getService(AclService::class)->isAdmin() || $this->hibernationService->isWikiHibernated()) {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS', 'formid' => $id], false));
        }

        $form = $id === null ? null : $this->formManager->getOne($id);
        if ($form === null || $actorUri === null) {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'NOT_FOUND', 'formid' => $id], false));
        }

        $this->activityPubService->postActivity(['type' => 'Follow', 'object' => $actorUri, 'to' => $actorUri], $form);

        return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_FOLLOWING_ADDED', 'formid' => $id], false));
    }

    /**
     * @param int|string|null $id       from the query string, so possibly absent
     * @param string|null     $actorUri
     *
     * @return string
     */
    public function removeFollowing($id, $actorUri)
    {
        if (!$this->getService(AclService::class)->isAdmin() || $this->hibernationService->isWikiHibernated()) {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS', 'formid' => $id], false));
        }

        $form = $id === null ? null : $this->formManager->getOne($id);
        if ($form === null || $actorUri === null) {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'NOT_FOUND', 'formid' => $id], false));
        }

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

    /**
     * @param int|string|null $id       from the query string, so possibly absent
     * @param string|null     $actorUri
     *
     * @return string
     */
    public function syncActorPosts($id, $actorUri)
    {
        if (!$this->getService(AclService::class)->isAdmin() || $this->hibernationService->isWikiHibernated()) {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS', 'formid' => $id], false));
        }

        $form = $id === null ? null : $this->formManager->getOne($id);
        if ($form === null || $actorUri === null) {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'NOT_FOUND', 'formid' => $id], false));
        }

        $stats = $this->activityPubService->syncActorPosts($actorUri, $form);

        Flash::success(sprintf(
            _t('BAZ_SYNC_COMPLETE'),
            $stats['created'],
            $stats['updated'],
            $stats['deleted'],
        ));

        return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'formid' => $id], false));
    }

    /**
     * @param int|string|null $id       from the query string, so possibly absent
     * @param string|null     $actorUri
     *
     * @return string
     */
    public function removeFollower($id, $actorUri)
    {
        if (!$this->getService(AclService::class)->isAdmin() || $this->hibernationService->isWikiHibernated()) {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS', 'formid' => $id], false));
        }

        $form = $id === null ? null : $this->formManager->getOne($id);
        if ($form === null || $actorUri === null) {
            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', ['view' => 'abonnements', 'action' => 'list', 'msg' => 'NOT_FOUND', 'formid' => $id], false));
        }

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

    /**
     * @return string[]|null the names of every group, null when the reader is not an admin
     */
    private function getGroupsListIfEnabled(): ?array
    {
        return $this->getService(AclService::class)->isAdmin()
            ? $this->getService(GroupOperationsService::class)->getAll()
            : null;
    }
}
