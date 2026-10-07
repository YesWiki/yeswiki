<?php

namespace YesWiki\Content\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\FieldRole;
use YesWiki\Content\Entity\Translations;
use YesWiki\Content\Exception\EntryValidationException;
use YesWiki\Content\Exception\TagAlreadyUsedException;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Field\ConditionsCheckingField;
use YesWiki\Content\Field\LinkedEntryField;
use YesWiki\Content\Service\ConditionsChecker;
use YesWiki\Content\Service\ContentCreator;
use YesWiki\Content\Service\ContentTypeResolver;
use YesWiki\Content\Service\EntryLimit;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FavoritesManager;
use YesWiki\Content\Service\FieldRoleResolver;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\FormPropertiesService;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\SemanticTransformer;
use YesWiki\Content\Service\TranslatableContent;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Exception\UserFieldException;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\BotGuard;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Kernel\Service\WikiUrls;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\LanguageSwitch;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Search\Service\SearchManager;

class EntryController extends YesWikiController
{
    protected AclService $aclService;
    protected AuthenticationService $authenticationService;

    /** @var array<string, mixed> every configuration parameter, as ParameterBagInterface::all() gives them */
    protected $config;

    protected EntryManager $entryManager;
    protected EventDispatcher $eventDispatcher;
    protected FavoritesManager $favoritesManager;
    protected FormManager $formManager;
    protected HibernationService $hibernationService;
    protected PageManager $pageManager;
    protected SemanticTransformer $semanticTransformer;
    protected TripleStore $tripleStore;

    /** @var list<string> the entries view() is inside of, innermost first -- guards against an entry embedding itself */
    private $parentsEntries;

    public function __construct(
        AclService $aclService,
        AuthenticationService $authenticationService,
        EntryManager $entryManager,
        EventDispatcher $eventDispatcher,
        FavoritesManager $favoritesManager,
        FormManager $formManager,
        HibernationService $hibernationService,
        PageManager $pageManager,
        ParameterBagInterface $config,
        SemanticTransformer $semanticTransformer,
        TripleStore $tripleStore,
    ) {
        $this->aclService = $aclService;
        $this->authenticationService = $authenticationService;
        $this->config = $config->all();
        $this->entryManager = $entryManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->favoritesManager = $favoritesManager;
        $this->formManager = $formManager;
        $this->hibernationService = $hibernationService;
        $this->pageManager = $pageManager;
        $this->parentsEntries = [];
        $this->semanticTransformer = $semanticTransformer;
        $this->tripleStore = $tripleStore;
    }

    /**
     * @param array<int|string> $formsIds (empty = all)
     *
     * @return string
     */
    public function selectForm(array $formsIds = [])
    {
        $formsIds = array_filter($formsIds, function ($formId) {
            return strval($formId) === strval(intval($formId));
        });
        if (empty($formsIds)) {
            $forms = $this->formManager->getAll();
        } else {
            $forms = $this->formManager->getMany($formsIds);
        }

        return $this->render('@core/entries/select_form.twig', ['forms' => $forms]);
    }

    /**
     * @param string|array<string, mixed> $entryId              the tag, or the whole entry when the caller
     *                                                          already has it. Declared `string` until
     *                                                          ticket 40, which made the array branch below
     *                                                          unreachable as far as the analyser could see
     * @param string|null                 $time                 choose only the entry's revision corresponding to time, null = latest revision
     * @param bool                        $showFooter
     * @param string|null                 $userNameForRendering userName used to render the entry, if empty uses the connected user
     * @param array<string, mixed>|null   $pLocalForm           the entry's own form, when the caller already has it
     * @param array<string, mixed>|null   $pExternalForm        the form of the entry's `external-data`, when the caller already has it
     *
     * @return string the rendered entry, or an alert when there is none to render
     */
    public function view($entryId, $time = '', $showFooter = true, ?string $userNameForRendering = null, $pLocalForm = null, $pExternalForm = null)
    {
        if (is_array($entryId)) {
            if (empty($entryId) || !isset($entryId['tag'])) {
                return '<div class="alert alert-danger">' . _t('BAZ_PAS_D_ID_DE_FICHE_INDIQUEE') . '</div>';
            }
            $entry = $entryId;
            $entryId = (string)$entry['tag'];
        } elseif ($entryId) {
            $entry = $this->entryManager->getOne($entryId, false, $time, empty($userNameForRendering), false, $userNameForRendering)

                ?? $this->builtInContentAsEntry((string)$entryId, $time === '' ? null : $time, $userNameForRendering);
            if (!$entry) {
                return '<div class="alert alert-danger">' . _t('BAZ_PAS_DE_FICHE_AVEC_CET_ID') . ' : ' . $entryId . '</div>';
            }
        } else {
            return '<div class="alert alert-danger">' . _t('BAZ_PAS_D_ID_DE_FICHE_INDIQUEE') . '</div>';
        }

        if (empty($pLocalForm)) {
            $pLocalForm = $this->formManager->getOne($entry['form_id']);
        }

        $vExternalData = $entry['external-data'] ?? null;

        if (!empty($vExternalData)) {
            $pExternalForm = $this->formManager->getOne($entry['external-data']['formIDKey']);
        }

        $oldPageTag = $this->getService(PageContext::class)->getTag();
        $this->getService(PageContext::class)->setTag($entryId);
        $renderedEntry = null;

        $isUpdatingEntry = ($this->getRequest()->query->get('view') === 'consulter');
        if ($isUpdatingEntry) {
            unset($_GET['view']);
        }

        array_unshift($this->parentsEntries, $entryId);
        if (
            count(array_filter($this->parentsEntries, function ($value) use ($entryId) {
                return $value === $entryId;
            })) < 3
        ) {
            $customTemplatePath = $this->getCustomTemplatePath($entry);
            $customTemplateValues = null;

            if ($customTemplatePath === null && !empty($pLocalForm['sem_type']) && !empty($pLocalForm['sem_template'])) {
                $customTemplateValues = $this->getValuesForCustomTemplate($entry, $pLocalForm, $userNameForRendering);
                $semanticTemplatePath = $this->getCustomSemanticTemplatePath($customTemplateValues['html']['semantic'] ?? null);
                if ($semanticTemplatePath !== null) {
                    $customTemplatePath = "@core/$semanticTemplatePath";
                }
            }

            if ($customTemplatePath !== null) {
                $customTemplateValues ??= $this->getValuesForCustomTemplate($entry, $pLocalForm, $userNameForRendering);
                $renderedEntry = $this->render($customTemplatePath, $customTemplateValues);
            }

            if (is_null($renderedEntry)) {
                if (!empty($pLocalForm)) {
                    $states = $this->getService(ConditionsChecker::class)->states($pLocalForm, $entry);
                    foreach (array_values($pLocalForm['prepared']) as $index => $field) {
                        if ($field instanceof BazarField) {
                            if ($field instanceof ConditionsCheckingField || !($states[$index]['visible'] ?? true)) {
                                continue;
                            }

                            if (!in_array($field->getPropertyName(), $this->fieldsToExclude())) {
                                $renderedEntry .= $field->renderStaticIfPermitted($entry, $userNameForRendering);
                            }
                        }
                    }
                } else {
                    $renderedEntry = $this->render(
                        '@core/alert-message.twig',
                        [
                            'type' => 'info',
                            'message' => str_replace('{{nb}}', $entry['form_id'], _t('BAZ_PAS_DE_FORM_AVEC_ID_DE_CETTE_FICHE')),
                        ],
                    );
                }
            }
        }

        $this->getService(PageContext::class)->setTag($oldPageTag);

        array_shift($this->parentsEntries);

        $owner = $this->getService(PageManager::class)->getOwner($entryId) ?? $this->getService(AuthenticationService::class)->getLoggedUserName();
        $isOwnerIpAddress = preg_replace('/([0-9]|\.)/', '', $owner) == '';
        if ($isOwnerIpAddress || !$owner) {
            $owner = _t('BAZ_UNKNOWN_USER');
        }
        if (!empty($this->config['sso_config']) && isset($this->config['sso_config']['bazar_user_entry_id']) && $this->pageManager->getOne($owner)) {
            $owner = $this->getService(MarkdownFormatterService::class)->format('[[' . $this->getService(PageManager::class)->getOwner($entryId) . ' ' . $this->getService(PageManager::class)->getOwner($entryId) . ']]');
        }

        if ($isUpdatingEntry) {
            $_GET['view'] = 'consulter';
        }

        $user = $this->authenticationService->getLoggedUser();
        if (!empty($user) && $this->favoritesManager->areFavoritesActivated() && (WikiUrls::iframeSuffixFor() == 'iframe')) {
            $currentuser = $user['name'];
            $isUserFavorite = $this->favoritesManager->isUserFavorite($currentuser, $entryId);
        }

        $sourceUrl = $this->tripleStore->getOne($entryId, TripleStore::SOURCE_URL_URI, '', '');

        return $this->render('@core/entries/view.twig', [
            'form' => $pLocalForm,
            'externalForm' => $pExternalForm,
            'entry' => $entry,
            'entryId' => $entryId,
            'owner' => $owner,
            'showFooter' => $showFooter,
            'currentuser' => $currentuser ?? null,
            'isUserFavorite' => $isUserFavorite ?? false,
            'canShow' => $this->getService(PageContext::class)->getTag() != $entry['tag'],
            'canEdit' => !$this->hibernationService->isWikiHibernated() && $this->aclService->hasAccess('write', $entryId) && !isset($entry['read-only']),
            'canDelete' => !$this->hibernationService->isWikiHibernated() && ($this->getService(AclService::class)->isAdmin($userNameForRendering) || $this->getService(AclService::class)->isOwner($entryId)) && !isset($entry['read-only']),
            'canDuplicate' => $this->getService(AclService::class)->isAdmin($userNameForRendering) && !isset($entry['read-only']),
            'isAdmin' => $this->getService(AclService::class)->isAdmin($userNameForRendering),
            'renderedEntry' => $renderedEntry,
            'sourceUrl' => $sourceUrl,
            'incomingUrl' => $this->getRequest()->query->get('incomingurl', WikiUrls::absoluteUrl()),
            'editContextUrl' => $this->getEditContextUrl($entryId),
        ]);
    }

    /** Shows the saved-entry message on the next page, with a link to carry on. */
    private function flashSavedEntry(string $message, string $link, string $linkLabel): void
    {
        Flash::success($this->render('@core/entries/saved-message.twig', [
            'message' => $message,
            'link' => $link,
            'linkLabel' => $linkLabel,
        ]));
    }

    /**
     * @return list<string> the property names the `excludeFields` query parameter asks to skip
     */
    private function fieldsToExclude()
    {
        $excludeFields = $this->getRequest()->query->get('excludeFields');

        return $excludeFields ? explode(',', $excludeFields) : [];
    }

    /**
     * Entry moderation, which no longer has anywhere to store its answer.
     *
     * @param string $entryId
     * @param bool   $accepted
     *
     * @return string
     *
     * @throws \Exception always
     */
    public function publish($entryId, $accepted)
    {
        throw new \Exception("Entry moderation is no longer supported: nothing records whether entry '{$entryId}' is published");
    }

    /**
     * @param string $formId
     *
     * @return string the creation form, or an alert when there is none to show
     */
    public function create($formId, ?string $redirectUrl = null)
    {
        if (empty($formId)) {
            return '<div class="alert alert-danger">' . _t('BAZ_PAS_D_ID_DE_FORM_INDIQUE') . '</div>';
        }

        $_SESSION['current_form_id'] = $formId;
        $form = $this->formManager->getOne($formId);
        if (!$form) {
            return '<div class="alert alert-danger">' . _t('BAZ_PAS_DE_FORM_AVEC_CET_ID') . ' : \'' . $formId . '\'</div>';
        }

        $results = $this->checkIfOnlyOneEntry($form);
        $incomingUrl = $this->getIncomingUrl();
        $entryLimit = $this->getService(EntryLimit::class);
        $refusal = $entryLimit->refusal($form);
        if ($refusal !== null && empty($results['output'])) {
            return $this->render('@core/alert-message.twig', ['type' => 'warning', 'message' => htmlspecialchars($refusal)]);
        }

        $post = $this->getRequest()->request;
        if (!empty($results['output'])) {
            return $results['output'];
        } elseif (empty($results['error'])) {
            $error = $post->has('valider') ? $this->botGuardRefusal() : null;
            if ($error !== null) {
                $refusedData = $this->getService(BotGuard::class)->withoutFields($post->all());
            }
            try {
                if ($error === null && $post->has('valider')) {
                    $postedData = $this->getService(BotGuard::class)->withoutFields($post->all());
                    unset($postedData['tag']);
                    $entry = $this->getService(ContentCreator::class)->create($formId, $postedData);

                    if (!ContentTypeSchema::isBuiltIn($form[ContentTypeSchema::CONTENT_TYPE] ?? null)) {
                        $this->flashSavedEntry(
                            _t('BAZ_FICHE_ENREGISTREE'),
                            $this->getService(UrlFormatter::class)->href(WikiUrls::iframeSuffixFor(), '', ['view' => 'saisir', 'id' => $formId], false),
                            _t('BAZ_ADD_NEW_ENTRY'),
                        );
                    }
                    $redirectUrl = !empty($incomingUrl)
                        ? $incomingUrl
                        : (
                            !empty($redirectUrl)
                            ? $redirectUrl
                            : $this->createdContentUrl($form, $entry['tag'])
                        );
                    header('Location: ' . $redirectUrl);
                    $this->getService(Redirector::class)->terminate();
                }
            } catch (UserFieldException|EntryValidationException|TagAlreadyUsedException $e) {
                $error .= $this->render('@core/alert-message.twig', [
                    'type' => 'warning',
                    'message' => $e->getMessage(),
                ]);
                $refusedData = $post->all();
            }
        } else {
            $error = $results['error'];
        }

        $renderedInputs = $this->getRenderedInputs($form, $refusedData ?? null);
        $counter = $entryLimit->counter($form);
        $counterAlert = $counter === null ? '' : $this->render('@core/alert-message.twig', ['type' => 'info', 'message' => htmlspecialchars($counter)]);

        return $counterAlert . $this->render('@core/entries/form.twig', [
            'form' => $form,
            'formAction' => $this->getRequest()->getRequestUri(),
            'renderedInputs' => $renderedInputs,
            'passwordForEditing' => isset($this->config['password_for_editing']) && !empty($this->config['password_for_editing']) && $post->has('password_for_editing') ? $post->get('password_for_editing') : '',
            'incomingUrl' => $incomingUrl,
            'cancelUrl' => $this->getCancelUrl($incomingUrl),
            'error' => $error,
            'botGuardFields' => $this->getService(BotGuard::class)->fields(),
            'imageSmallWidth' => $this->config['image-small-width'],
            'imageSmallHeight' => $this->config['image-small-height'],
            'imageMediumWidth' => $this->config['image-medium-width'],
            'imageMediumHeight' => $this->config['image-medium-height'],
            'imageBigWidth' => $this->config['image-big-width'],
            'imageBigHeight' => $this->config['image-big-height'],
        ]);
    }

    /** The alert when BotGuard refuses the submission, else null. */
    private function botGuardRefusal(): ?string
    {
        $botGuard = $this->getService(BotGuard::class);
        $reason = $botGuard->check($this->getRequest());

        return $reason === null ? null : $this->render('@core/alert-message.twig', [
            'type' => 'danger',
            'message' => $botGuard->message($reason),
        ]);
    }

    /**
     * Where a visitor lands after creating a Content.
     *
     * @param array<string, mixed> $form
     */
    private function createdContentUrl(array $form, string $tag): string
    {
        $method = ContentTypeSchema::isBuiltIn($form[ContentTypeSchema::CONTENT_TYPE] ?? null) ? '' : WikiUrls::iframeSuffixFor();

        return $this->getService(UrlFormatter::class)->href($method, $tag, [], false);
    }

    /**
     * @param string $entryId
     *
     * @return string the edit form
     */
    public function update($entryId)
    {
        $entry = $this->entryManager->getUntranslated($entryId);
        if (empty($entry)) {
            return '<div class="alert alert-danger">' . _t('BAZ_PAS_DE_FICHE_AVEC_CET_ID') . ' : ' . $entryId . '</div>';
        }
        $form = $this->formManager->getOne($entry['form_id']);
        if (empty($form)) {
            return '<div class="alert alert-danger">' . str_replace('{{nb}}', $entry['form_id'], _t('BAZ_PAS_DE_FORM_AVEC_ID_DE_CETTE_FICHE')) . '</div>';
        }

        $translatable = $this->getService(TranslatableContent::class);
        $source = $translatable->sourceLanguageOf($form);
        $editing = $translatable->editingLanguage($this->getRequest()->query->get('editlang'), $source);
        $translating = $editing !== $source;

        $incomingUrl = $this->getIncomingUrl();
        $post = $this->getRequest()->request;
        $posted = $this->getService(BotGuard::class)->withoutFields($post->all());
        $error = $post->has('valider') ? $this->botGuardRefusal() : null;
        if ($error !== null) {
            $entry = array_merge($entry, $posted);
        }
        try {
            if ($error === null && $post->has('valider')) {
                if ($translating) {
                    $this->entryManager->saveTranslations($entryId, $editing, $translatable->sanitize(
                        $posted,
                        $translatable->entryPaths($form)
                    ));
                    $entry = $this->entryManager->getUntranslated($entryId) ?? $entry;
                } else {
                    $entry = $this->entryManager->update($entryId, $posted);
                }

                $urlFormatter = $this->getService(UrlFormatter::class);
                $this->flashSavedEntry(
                    _t('BAZ_FICHE_MODIFIEE'),
                    $urlFormatter->href(WikiUrls::iframeSuffixFor() === 'iframe' ? 'editiframe' : 'edit', $entry['tag'], $translating ? ['editlang' => $editing] : [], false),
                    _t('BAZ_MODIFY_ENTRY_AGAIN'),
                );
                $redirectUrl = !empty($incomingUrl)
                    ? $incomingUrl
                    : $urlFormatter->href(WikiUrls::iframeSuffixFor(), $entry['tag'], [], false);
                header('Location: ' . $redirectUrl);
                $this->getService(Redirector::class)->terminate();
            }
        } catch (UserFieldException|EntryValidationException $e) {
            $error .= $this->render('@core/alert-message.twig', [
                'type' => 'warning',
                'message' => $e->getMessage(),
            ]);

            $entry = array_merge($entry, $posted);
        }

        $this->getService(LanguageSwitch::class)->writing(
            $translatable->editingLanguages($source, $editing, $entry, $translatable->entryPaths($form))
        );

        $shown = $this->entryForEditing($entry, $form, $editing, $source);
        $renderedInputs = $this->getRenderedInputs($form, $shown, $translating);

        return $this->render('@core/entries/form.twig', [
            'form' => $form,
            'formAction' => $this->getRequest()->getRequestUri(),
            'entryId' => $entryId,
            'editLanguage' => $editing,
            'editingTranslation' => $translating,
            'renderedInputs' => $renderedInputs,
            'passwordForEditing' => isset($this->config['password_for_editing']) && !empty($this->config['password_for_editing']) && $post->has('password_for_editing') ? $post->get('password_for_editing') : '',
            'incomingUrl' => $incomingUrl,
            'cancelUrl' => $this->getCancelUrl($incomingUrl, $entryId),
            'error' => $error,
            'botGuardFields' => $this->getService(BotGuard::class)->fields(),
            'imageSmallWidth' => $this->config['image-small-width'],
            'imageSmallHeight' => $this->config['image-small-height'],
            'imageMediumWidth' => $this->config['image-medium-width'],
            'imageMediumHeight' => $this->config['image-medium-height'],
            'imageBigWidth' => $this->config['image-big-width'],
            'imageBigHeight' => $this->config['image-big-height'],
        ]);
    }

    /**
     * @param string $entryId
     */
    public function delete($entryId, bool $redirectAfter = false): bool
    {
        if ($this->entryManager->isEntry($entryId)) {
            try {
                $entry = $this->entryManager->getOne($entryId);
                $this->entryManager->delete($entryId);
                if (!$this->entryManager->isEntry($entryId)) {
                    if ($redirectAfter) {
                        Flash::success(_t('BAZ_FICHE_SUPPRIMEE') . " ($entryId)");
                        $this->getService(Redirector::class)->redirect($this->afterDeletionUrl($entry));
                    }

                    return true;
                }
            } catch (\Throwable $th) {
                if ($redirectAfter) {
                    Flash::error(_t('DELETEPAGE_NOT_DELETED') . " ($entryId) : {$th->getMessage()}");
                    $this->getService(Redirector::class)->redirect($this->afterDeletionUrl($entry ?? null));
                }
                throw new \Exception($th->getMessage(), $th->getCode(), $th);
            }

            return false;
        }
        throw new \Exception("Not deleted because not entry ({$entryId})");
    }

    /**
     * A page, an account or an uploaded file in the shape this controller renders.
     *
     * @return array<string, mixed>|null null for a row no form describes -- a form, a list
     */
    private function builtInContentAsEntry(string $tag, ?string $time, ?string $userNameForRendering): ?array
    {
        $page = $this->pageManager->getOne($tag, $time ?: null, true, false, $userNameForRendering);
        if (empty($page)) {
            return null;
        }

        $translatable = $this->getService(TranslatableContent::class);
        $page['body'] = $translatable->forReader(
            is_array($page['body'] ?? null) ? $page['body'] : [],
            $translatable->sourceLanguageOfPage($this->pageManager->getMetadata($tag))
        );

        return $this->getService(ContentTypeResolver::class)->asEntry($page, null, false);
    }

    /**
     * @param array<string, mixed>      $form
     * @param array<string, mixed>|null $entry
     *
     * @return list<string> one rendered input per field, plus the form's own extra inputs
     */
    private function getRenderedInputs($form, $entry = null, bool $translatableOnly = false)
    {
        $renderedFields = [];
        foreach ($form['prepared'] as $field) {
            if (!$field instanceof BazarField) {
                continue;
            }
            if ($translatableOnly && !$field->translatesValue()) {
                continue;
            }
            $renderedFields[] = $field->renderInputIfPermitted($entry);
        }

        if ($translatableOnly) {
            return $renderedFields;
        }

        $formProperties = $this->getService(FormPropertiesService::class);
        $renderedFields[] = $formProperties->renderUserCreationInputs($form, $entry);
        $renderedFields[] = $formProperties->renderCommentsToggle($form, $entry);

        return $renderedFields;
    }

    /**
     * What the entry editor should show for $language: its translation, blank where it has none.
     *
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $form
     *
     * @return array<string, mixed>
     */
    private function entryForEditing(array $stored, array $form, string $language, string $source): array
    {
        if ($language === $source) {
            return $stored;
        }

        $shown = Translations::strip($stored);
        foreach ($this->getService(TranslatableContent::class)->entryPaths($form) as $path) {
            $shown[$path['path']] = '';
        }
        $this->showWhatIsBeingTranslated($form, $stored);

        return array_merge($shown, Translations::of($stored, $language));
    }

    /**
     * Put the source wording on each field a translator is asked to fill in: the input itself stays
     * empty, since an empty translation is what makes a reader fall back to the source.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $stored
     */
    private function showWhatIsBeingTranslated(array $form, array $stored): void
    {
        $source = Translations::strip($stored);
        foreach ($form['prepared'] ?? [] as $field) {
            if (!$field instanceof BazarField || !$field->translatesValue()) {
                continue;
            }
            $name = (string)$field->getPropertyName();
            if ($name !== '' && is_scalar($source[$name] ?? null)) {
                $field->showTranslationSource((string)$source[$name]);
            }
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function getCustomTemplatePath($entry): ?string
    {
        $templatePaths = [
            "@core/fiche-{$entry['form_id']}.twig",
        ];
        foreach ($templatePaths as $templatePath) {
            if ($this->getService(TemplateEngine::class)->hasTemplate($templatePath)) {
                return $templatePath;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $semanticData
     */
    private function getCustomSemanticTemplatePath($semanticData): ?string
    {
        if (empty($semanticData)) {
            return null;
        }

        if (is_array($semanticData['@context'])) {
            foreach ($semanticData['@context'] as $context) {
                if (is_string($context)) {
                    break;
                }
            }
        } else {
            $context = $semanticData['@context'];
        }

        if (isset($context) && $dir_name = $this->config['baz_semantic_types_mapping'][$context]) {
            if (is_array($semanticData['@type'])) {
                foreach ($semanticData['@type'] as $type) {
                    if (is_string($type)) {
                        break;
                    }
                }
            } else {
                $type = $semanticData['@type'];
            }

            if (isset($type)) {
                $templatePath = $dir_name . '/' . strtolower($type) . '.twig';

                return $this->getService(TemplateEngine::class)->hasTemplate($templatePath) ? $templatePath : null;
            }
        }

        return null;
    }

    /** What the first element carrying $class holds, as HTML, or null when the fragment has no such element. */
    private static function innerHtmlOfClass(string $html, string $class): ?string
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return null;
        }
        $nodes = (new \DOMXPath($dom))->query('//*[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]');
        $node = $nodes === false ? null : $nodes->item(0);
        if (!$node instanceof \DOMElement) {
            return null;
        }
        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        return trim($inner);
    }

    /**
     * @param array<string, mixed>      $entry
     * @param array<string, mixed>|null $form
     * @param string|null               $userNameForRendering userName used to render the entry, if empty uses the connected user
     *
     * @return array<string, mixed> the variables a custom entry template is rendered with, [] when there is no form
     */
    private function getValuesForCustomTemplate($entry, $form, ?string $userNameForRendering = null)
    {
        $html = [];
        if ($form === null) {
            return $html;
        }

        $titleFieldName = $this->getService(FormPropertiesService::class)->titleFieldName($form);
        foreach ($form['prepared'] as $field) {
            if ($field instanceof BazarField) {
                $id = $field instanceof LinkedEntryField
                    ? $field->getType() . $field->getName()
                    : $field->getPropertyName();
                if (!empty($id) && !in_array($id, $this->fieldsToExclude())) {
                    $html[$id] = (string)$field->renderStaticIfPermitted($entry, $userNameForRendering);
                    $isTitle = $titleFieldName !== null && $field->getName() === $titleFieldName;
                    $inner = $html[$id] === '' ? null : self::innerHtmlOfClass($html[$id], $isTitle ? 'BAZ_fiche_titre' : 'BAZ_texte');
                    if ($inner !== null && $inner !== '') {
                        $html[$id] = $inner;
                    }
                }
            }
        }

        if (!empty($form['sem_type']) && !empty($form['sem_template'])) {
            $html['tag'] = $entry['tag'];
            $html['semantic'] = $this->getService(SemanticTransformer::class)->convertToSemanticData($form, $html);
        }

        $values = [];
        $values['html'] = $html;
        $values['entry'] = $entry;
        $values['form'] = $form;
        $values['renderedFields'] = $html;
        $values['formFields'] = [];
        foreach ($values['form']['prepared'] as $config) {
            if (!empty($config->getName())) {
                $values['formFields'][$config->getName()] = $config;
            }
        }

        return $values;
    }

    /**
     * format queries from GET and from $arg in order to give the right 'queries' to SearchManager->search.
     *
     * @param array<string, mixed>|string|null $arg
     * @param array<string, mixed>             $get (copy of $_GET) but pass in parameters to be more visible in primary level controllers
     *                                              NOTE : this function is kept for retrocompatibility. You should use SearchManager::aggregateQueries
     *
     * @return array<int, array<string, mixed>> one entry per query, as SearchManager::parseQuery() shapes them
     */
    public function formatQuery($arg, array $get): array
    {
        $vSearchManager = $this->getService(SearchManager::class);

        return $vSearchManager->parseQuery($vSearchManager->aggregateQueries($arg, $get));
    }

    /**
     * filter entries on date.
     *
     * @param array<array-key, array<string, mixed>> $entries
     * @param string                                 $datefilter
     *
     * @return array<array-key, array<string, mixed>> $entries
     */
    public function filterEntriesOnDate($entries, $datefilter): array
    {
        $TODAY_TEMPLATE = '/^(today|aujourdhui|aujourd\'hui|=0(D)?)$/i';
        $FUTURE_TEMPLATE = '/^(futur|future|>0(D)?)$/i';
        $PAST_TEMPLATE = '/^(past|passe|passé|<0(D)?)$/i';
        $DATE_TEMPLATE = "(\+|-)(([0-9]+)Y)?(([0-9]+)M)?(([0-9]+)D)?";
        $EQUAL_TEMPLATE = '/^=' . $DATE_TEMPLATE . '$/i';
        $AFTER_TEMPLATE = '/^>' . $DATE_TEMPLATE . '$/i';
        $BEFORE_TEMPLATE = '/^<' . $DATE_TEMPLATE . '$/i';
        $BETWEEN_TEMPLATE = '/^>' . $DATE_TEMPLATE . '&<' . $DATE_TEMPLATE . '$/i';

        if (preg_match_all($TODAY_TEMPLATE, $datefilter, $matches)) {
            $todayMidnight = new \DateTime();
            $todayMidnight->setTime(0, 0);
            $entries = array_filter($entries, function ($entry) use ($todayMidnight) {
                return $this->filterEntriesOnDateTraversing($entry, '=', $todayMidnight);
            });
        } elseif (preg_match_all($FUTURE_TEMPLATE, $datefilter, $matches)) {
            $now = new \DateTime();
            $entries = array_filter($entries, function ($entry) use ($now) {
                return $this->filterEntriesOnDateTraversing($entry, '>', $now);
            });
        } elseif (preg_match_all($PAST_TEMPLATE, $datefilter, $matches)) {
            $now = new \DateTime();
            $entries = array_filter($entries, function ($entry) use ($now) {
                return $this->filterEntriesOnDateTraversing($entry, '<', $now);
            });
        } elseif (preg_match_all($EQUAL_TEMPLATE, $datefilter, $matches)) {
            $sign = $matches[1][0];
            $nbYears = $matches[3][0];
            $nbMonth = $matches[5][0];
            $nbDays = $matches[7][0];

            $dateMidnigth = $this->extractDate($sign, $nbYears, $nbMonth, $nbDays);
            $dateMidnigth->setTime(0, 0);
            $entries = array_filter($entries, function ($entry) use ($dateMidnigth) {
                return $this->filterEntriesOnDateTraversing($entry, '=', $dateMidnigth);
            });
        } elseif (preg_match_all($AFTER_TEMPLATE, $datefilter, $matches)) {
            $sign = $matches[1][0];
            $nbYears = $matches[3][0];
            $nbMonth = $matches[5][0];
            $nbDays = $matches[7][0];

            $date = $this->extractDate($sign, $nbYears, $nbMonth, $nbDays);
            $entries = array_filter($entries, function ($entry) use ($date) {
                return $this->filterEntriesOnDateTraversing($entry, '>', $date);
            });
        } elseif (preg_match_all($BEFORE_TEMPLATE, $datefilter, $matches)) {
            $sign = $matches[1][0];
            $nbYears = $matches[3][0];
            $nbMonth = $matches[5][0];
            $nbDays = $matches[7][0];

            $date = $this->extractDate($sign, $nbYears, $nbMonth, $nbDays);
            $entries = array_filter($entries, function ($entry) use ($date) {
                return $this->filterEntriesOnDateTraversing($entry, '<', $date);
            });
        } elseif (preg_match_all($BETWEEN_TEMPLATE, $datefilter, $matches)) {
            $signMore = $matches[1][0];
            $nbYearsMore = $matches[3][0];
            $nbMonthMore = $matches[5][0];
            $nbDaysMore = $matches[7][0];
            $dateMin = $this->extractDate($signMore, $nbYearsMore, $nbMonthMore, $nbDaysMore);
            $signLower = $matches[8][0];
            $nbYearsLower = $matches[10][0];
            $nbMonthLower = $matches[12][0];
            $nbDaysLower = $matches[14][0];
            $dateMax = $this->extractDate($signLower, $nbYearsLower, $nbMonthLower, $nbDaysLower);
            if ($dateMin->diff($dateMax)->invert == 0) {
                $entries = array_filter($entries, function ($entry) use ($dateMin) {
                    return $this->filterEntriesOnDateTraversing($entry, '>', $dateMin);
                });
                $entries = array_filter($entries, function ($entry) use ($dateMax) {
                    return $this->filterEntriesOnDateTraversing($entry, '<', $dateMax);
                });
            }
        }

        return $entries;
    }

    private function extractDate(string $pSign, string $nbYears, string $nbMonth, string $nbDays): \DateTime
    {
        $vDateInterval = new \DateInterval(
            'P'
                    . (!empty($nbYears) ? $nbYears . 'Y' : '')
                    . (!empty($nbMonth) ? $nbMonth . 'M' : '')
                    . (!empty($nbDays) ? $nbDays . 'D' : (empty($nbYears) && empty($nbMonth) ? '0D' : '')),
        );
        $vDateInterval->invert = ($pSign == '-') ? 1 : 0;

        $vDate = new \DateTime();
        $vDate->add($vDateInterval);

        return $vDate;
    }

    /**
     * @param array<string, mixed>|null $entry
     */
    private function filterEntriesOnDateTraversing(?array $entry, string $mode, \DateTime $date): bool
    {
        if (empty($entry)) {
            return false;
        }

        $form = empty($entry['form_id']) ? null : $this->getService(FormManager::class)->getOne($entry['form_id']);
        $resolver = $this->getService(FieldRoleResolver::class);
        $startValue = $resolver->value($form, $entry, FieldRole::START_DATE);
        if ($startValue === null) {
            return false;
        }
        $endValue = $resolver->value($form, $entry, FieldRole::END_DATE);

        $entryStartDate = new \DateTime((string)$startValue);
        if ($endValue !== null && trim((string)$endValue) !== '') {
            $entryEndDate = new \DateTime((string)$endValue);
            if (strpos((string)$endValue, 'T') === false) {
                $entryEndDate->add(new \DateInterval('P1D'));
            }
        }
        if (empty($entryEndDate)) {
            $entryEndDate = (clone $entryStartDate)->setTime(0, 0)->add(new \DateInterval('P1D'));
        }
        $nextDay = (clone $date)->add(new \DateInterval('P1D'));
        switch ($mode) {
            case '<':
                return $date->diff($entryStartDate)->invert == 1;
            case '>':
                return
                    $date->diff($entryStartDate)->invert == 0
                    || !$this->dateIsStrictlyBefore($entryEndDate, $date);
            case '=':
            default:
                return
                    $nextDay->diff($entryStartDate)->invert == 1
                    && !$this->dateIsStrictlyBefore($entryEndDate, $date);
        }
    }

    private function dateIsStrictlyBefore(\DateTime $dateToCompare, \DateTime $referenceDate): bool
    {
        $diff = $referenceDate->diff($dateToCompare);

        return $diff->invert == 1 || (
            $diff->invert == 0
            && $diff->days == 0
            && $diff->h == 0
            && $diff->i == 0
            && $diff->s == 0
            && $diff->f == 0
        );
    }

    /**
     * @param array<array-key, array<string, mixed>> $entries
     * @param array<string, mixed>                   $params         the entrylist action's other arguments
     * @param bool                                   $showNumEntries
     *
     * @return string
     */
    public function renderBazarList($entries, $params = [], $showNumEntries = true)
    {
        $ids = [];
        foreach ($entries as $entry) {
            if (!empty($entry['tag'])) {
                $ids[] = $entry['tag'];
            }
        }
        $params['query'] = 'tag=' . implode(',', $ids);
        $params['shownumentries'] = $showNumEntries;

        if (empty($ids)) {
            return $this->render(
                '@core/alert-message.twig',
                [
                    'type' => 'info',
                    'message' => _t('BAZ_IL_Y_A') . ' 0 ' . _t('BAZ_FICHE'),
                ],
            );
        }

        return $this->getService(ActionRunner::class)->action('entrylist', $params);
    }

    /**
     * check if creation of entry is authorized for this form.
     *
     * @param array<string, mixed> $form
     *
     * @return array{error: string, output: string}
     */
    private function checkIfOnlyOneEntry(array $form): array
    {
        $results = [
            'error' => '',
            'output' => '',
        ];
        if (isset($form['only_one_entry']) && $form['only_one_entry'] === 'Y') {
            $formHasUserField = $this->getService(FormPropertiesService::class)->createsUser($form);
            $loggerUser = $this->authenticationService->getLoggedUser();
            if (!$formHasUserField && empty($loggerUser)) {
                $results['output'] = $this->render('@core/alert-message.twig', [
                    'type' => 'warning',
                    'message' => _t('BAZ_USER_SHOULD_BE_CONNECTED_TO_ACCES_THIS_FORM'),
                ]);
                $pageLogin = $this->pageManager->GetOne('PageLogin');
                $results['output'] .= $this->getService(MarkdownFormatterService::class)->format(!empty($pageLogin) ? '{{include page="PageLogin"}}' : '{{login template="login-form.twig"}}');
            } elseif (!empty($loggerUser)) {
                $userName = $loggerUser['name'];

                $vSearchManager = $this->getService(SearchManager::class);

                $entries = $vSearchManager->search([
                    'formsIds' => [$form['id']],
                    'user' => $userName,
                ]);
                if (!empty($entries)) {
                    $firstEntry = $entries[array_keys($entries)[0]];
                    $message = !empty($form['only_one_entry_message']) ? $form['only_one_entry_message'] : _t('BAZ_FORM_DEFAULT_MESSAGE_FOR_OTHER_ENTRY_IN_FORM');
                    $message = str_replace('{formName}', $form['label'], $message);
                    $results['output'] = $this->render('@core/alert-message.twig', [
                        'type' => 'info',
                        'message' => $message,
                    ]);
                    $results['output'] .= $this->view($firstEntry['tag']);

                    return $results;
                }
            }
        }

        return $results;
    }

    public function getIncomingUrl(): string
    {
        $request = $this->getRequest();
        $incomingUrl = $request->query->get('incomingurl') ?? $request->request->get('incomingurl') ?? '';
        if (!empty($incomingUrl)) {
            $incomingUrl = urldecode((string)$incomingUrl);
            $incomingUrl = filter_var($incomingUrl, FILTER_VALIDATE_URL);
        }

        return empty($incomingUrl) || !$this->isWikiUrl($incomingUrl) ? '' : $incomingUrl;
    }

    /** The page to come back to after editing $entryTag. */
    public function getEditContextUrl(string $entryTag): string
    {
        $pageContext = $this->getService(PageContext::class);
        if ($pageContext->getTag() === 'api' || !in_array($pageContext->getMethod(), ['show', 'iframe'], true)) {
            return '';
        }
        $urlFormatter = $this->getService(UrlFormatter::class);
        $currentUrl = WikiUrls::absoluteUrl();
        $plainUrls = [$urlFormatter->href('', $entryTag, null, false), $urlFormatter->href('iframe', $entryTag, null, false)];

        return in_array($currentUrl, $plainUrls, true) || !$this->isWikiUrl($currentUrl) ? '' : $currentUrl;
    }

    /** Where the form's cancel button leads. */
    private function getCancelUrl(string $incomingUrl, ?string $entryId = null): string
    {
        if ($incomingUrl !== '') {
            return $incomingUrl;
        }
        $referer = (string)$this->getRequest()->headers->get('referer', '');
        if ($referer !== '' && $this->isWikiUrl($referer) && strtok($referer, '#') !== strtok(WikiUrls::absoluteUrl(), '#')) {
            return $referer;
        }

        return $this->getService(UrlFormatter::class)->href(WikiUrls::iframeSuffixFor(), $entryId ?? '', null, false);
    }

    private function isWikiUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host === parse_url($this->getService(UrlFormatter::class)->getBaseUrl(), PHP_URL_HOST);
    }

    /**
     * Where a deletion sends back: the form's own screen, or the dashboard when the form is unknown.
     *
     * @param array<string, mixed>|null $entry
     */
    private function afterDeletionUrl(?array $entry): string
    {
        $form = empty($entry['form_id']) ? null : $this->getService(FormManager::class)->getOne((string)$entry['form_id']);
        $urlFormatter = $this->getService(UrlFormatter::class);

        return empty($form['tag'])
            ? $urlFormatter->href('', 'dashboard', ['view' => 'formulaire'], false)
            : $urlFormatter->href('', (string)$form['tag'], [], false);
    }
}
