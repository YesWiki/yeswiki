<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Controller\FormController;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Entity\Translations;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Service\ContentTypeResolver;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\PageTemplates;
use YesWiki\Content\Service\TranslatableContent;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\BotGuard;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Identity\Service\PasswordForEditingService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\FlashMessageService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\InclusionStack;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Kernel\Service\WikiUrls;
use YesWiki\Render\Service\HibernationNotice;
use YesWiki\Render\Service\LanguageSwitch;
use YesWiki\Render\Service\LayoutService;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\PageLayout;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Render\Service\ThemeSelectorRenderer;
use YesWiki\Search\Service\TagsManager;

/** `/PageName/edit` -- converted from the procedural handlers/page/edit.php by ticket 06. */
class EditHandler extends YesWikiHandler implements RegisteredHandler
{
    public static function performableName(): string
    {
        return 'edit';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emitBefore();
            $this->emit();
        } catch (\Throwable $t) {
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return $this->emitAfter((string)ob_get_clean());
    }

    /** Ran as a before-callback until ticket 06 merged it in. */
    private function emitBefore(): void
    {
        if ($this->getService(AclService::class)->hasAccess('write') && $this->getService(AclService::class)->hasAccess('read')) {
            list($state, $message) = $this->getService(PasswordForEditingService::class)->isGrantedPasswordForEditing();
            if (!$state) {
                echo $this->getService(TemplateEngine::class)->renderPage((string)$message);
                $this->getService(Redirector::class)->terminate();
            }

            if ($this->getService(RuntimeConfig::class)['use_alerte']) {
                $js = "var showPopup = false;

                document.addEventListener('input', function(e) {
                    if (e.target && e.target.id === 'body') {
                        showPopup = true;
                    }
                });

                document.addEventListener('submit', function(e) {
                    if (e.target && (e.target.id === 'ACEditor' || e.target.classList.contains('bazar-form'))) {
                        showPopup = false;
                    }
                }, true);

                window.addEventListener('beforeunload', function(e) {
                    if (showPopup) {
                        e.preventDefault();
                        e.returnValue = '';
                    }
                });

                document.addEventListener('htmx:confirm', function(e) {
                    if (!showPopup || e.detail.question) return;
                    if (e.detail.elt && e.detail.elt.closest && e.detail.elt.closest('[data-yw-unsaved-ignore]')) return;
                    e.preventDefault();
                    window.ywConfirm(_t('EDIT_LEAVE_WITHOUT_SAVING'), { destructive: true }).then(function(confirmed) {
                        if (!confirmed) return;
                        showPopup = false;
                        e.detail.issueRequest(true);
                    });
                });";

                $this->getService(AssetRegistry::class)->addJs($js);
            }
        }

        if (isset($_GET['body']) && !isset($_POST['body'])) {
            $_POST['body'] = '# ' . $_GET['body'];
        }

        $this->getService(AssetRegistry::class)->addJsFile('javascripts/change-theme.js');
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/template-edit.js');

        $entryManager = $this->getService(EntryManager::class);
        $entryController = $this->getService(EntryController::class);

        $tag = $this->getService(PageContext::class)->getTag();
        if ($this->getService(PageManager::class)->typeOf($tag) === PageType::FORM) {
            $form = $this->getService(FormManager::class)->getOne($tag);
            $designer = $this->isWikiHibernated() || $form === null
                ? $this->getMessageWhenHibernated()
                : $this->getService(FormController::class)->update($form['id']);

            $this->getService(Redirector::class)->terminate(
                $this->getService(TemplateEngine::class)->renderPage($designer)
            );
        }

        if ($this->getService(AclService::class)->hasAccess('write') && $entryManager->isEntry($this->getService(PageContext::class)->getTag())) {
            $plugin_output_new = '';
            ob_start();
            $plugin_output_new .= $this->isWikiHibernated()
                ? $this->getMessageWhenHibernated()
                : $entryController->update($this->getService(PageContext::class)->getTag());
            $plugin_output_new .= ob_get_contents();
            ob_end_clean();

            $plugin_output_new = $this->getService(TemplateEngine::class)->renderPage($plugin_output_new);

            $this->getService(Redirector::class)->terminate($plugin_output_new);
        }
    }

    /** Where "delete this page" goes, for whoever may -- or null. */
    private function deleteUrl(): ?string
    {
        $tag = $this->getService(PageContext::class)->getTag();
        $aclService = $this->getService(AclService::class);
        if ($this->isWikiHibernated() || !($aclService->isOwner($tag) || $aclService->isAdmin())) {
            return null;
        }

        return $this->getService(UrlFormatter::class)->href('deletepage', $tag);
    }

    /** @return array{0: string, 1: string} the language this screen writes and the one the body is written in; a page nobody wrote yet is written, not translated */
    private function editingAndSourceLanguages(): array
    {
        $translatable = $this->getService(TranslatableContent::class);
        $source = $translatable->sourceLanguageOfPage(
            $this->getService(PageManager::class)->getMetadata($this->getService(PageContext::class)->getTag())
        );
        if (empty($this->getService(PageContext::class)->getPage())) {
            return [$source, $source];
        }
        $editing = $translatable->editingLanguage(
            $this->getRequest()->query->get('editlang'),
            $source
        );

        return [$editing, $source];
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed> what the editor shows for that language: its translation, blank where it has none
     */
    private function bodyForEditing(array $stored, string $language, string $source): array
    {
        if ($language === $source) {
            return $stored;
        }

        $shown = Translations::strip($stored);
        foreach ($this->contentFormFields()['translatable'] as $name) {
            $shown[$name] = '';
        }
        $this->showWhatIsBeingTranslated($stored);

        return array_merge($shown, Translations::of($stored, $language));
    }

    /** @param array<string, mixed> $stored shows the source wording beside each field a translator fills, leaving the input empty so readers fall back to the source */
    private function showWhatIsBeingTranslated(array $stored): void
    {
        $source = Translations::strip($stored);
        $fields = $this->contentFormFields();
        foreach ([...$fields['before'], ...$fields['after']] as $field) {
            if (!$field->translatesValue()) {
                continue;
            }
            $name = (string)$field->getPropertyName();
            if ($name !== '' && is_scalar($source[$name] ?? null)) {
                $field->showTranslationSource((string)$source[$name]);
            }
        }
    }

    /** @return list<array{path: string, label: string, multiline: bool}> what this page has to translate, as the language switch counts it */
    private function translatablePaths(): array
    {
        $form = $this->getService(ContentTypeResolver::class)->formForEditing($this->getService(PageContext::class)->getTag());

        return $form === null ? [] : $this->getService(TranslatableContent::class)->entryPaths($form);
    }

    /** @return array{before: list<BazarField>, after: list<BazarField>, hasContent: bool, translatable: list<string>} the Page form's fields, split around the one holding the markup */
    private function contentFormFields(): array
    {
        $split = ['before' => [], 'after' => [], 'hasContent' => false, 'translatable' => []];

        $form = $this->getService(ContentTypeResolver::class)
            ->formForEditing($this->getService(PageContext::class)->getTag());
        if ($form === null) {
            $split['hasContent'] = true;

            return $split;
        }

        [$editing, $source] = $this->editingAndSourceLanguages();
        $translating = $editing !== $source;

        $side = 'before';
        foreach ($form['prepared'] ?? [] as $field) {
            if (!$field instanceof BazarField) {
                continue;
            }
            if ($field->translatesValue() && (string)$field->getPropertyName() !== '') {
                $split['translatable'][] = (string)$field->getPropertyName();
            }
            if ($field->getPropertyName() === PageBody::CONTENT) {
                $split['hasContent'] = $field->translatesValue() || !$translating;
                $side = 'after';
                continue;
            }
            if ($translating && !$field->translatesValue()) {
                continue;
            }
            if ($field->getPropertyName() === PageBody::KEYWORDS && $this->params->get('hide_keywords')) {
                continue;
            }

            if ($field->getPropertyName() === ContentTypeSchema::tagMirrorField(
                $form[ContentTypeSchema::CONTENT_TYPE] ?? null
            )) {
                continue;
            }
            $split[$side][] = $field;
        }

        return $split;
    }

    /**
     * @param list<BazarField>     $fields
     * @param array<string, mixed> $body
     *
     * @return list<string> the inputs of those fields, filled from that body
     */
    private function renderContentFields(array $fields, array $body): array
    {
        $form = $this->getService(ContentTypeResolver::class)
            ->formForEditing($this->getService(PageContext::class)->getTag());
        $entry = array_merge($body, [
            'tag' => $this->getService(PageContext::class)->getTag(),
            'form_id' => $body['form_id'] ?? ($form['id'] ?? null),
        ]);

        return array_values(array_filter(array_map(
            fn (BazarField $field) => (string)$field->renderInputIfPermitted($entry),
            $fields
        )));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed> that body with the posted values of the Page form's fields
     */
    private function applyPostedPageFields(array $body): array
    {
        $posted = $this->getRequest()->request->all();
        $posted['tag'] = $this->getService(PageContext::class)->getTag();

        $fields = $this->contentFormFields();
        foreach (array_merge($fields['before'], $fields['after']) as $field) {
            if ($field->getPropertyName() === PageBody::KEYWORDS) {
                $keywords = TagsManager::parseList((string)($posted[PageBody::KEYWORDS] ?? ''));
                if (empty($keywords)) {
                    unset($body[PageBody::KEYWORDS]);
                } else {
                    $body[PageBody::KEYWORDS] = $keywords;
                }
                continue;
            }
            foreach ($field->formatValuesBeforeSaveIfEditable($posted) as $key => $value) {
                if ($key === 'fields-to-remove') {
                    continue;
                }

                if ($value === '' && !array_key_exists($key, $body)) {
                    continue;
                }
                $body[$key] = $value;
            }
        }

        return $body;
    }

    /** Ran as an after-callback until ticket 06 merged it in. */
    private function emitAfter(string $plugin_output_new): string
    {
        ob_start();

        $plugin_output_new = preg_replace(
            '/(\\{\\{template)(.*?)(\\}\\})/is',
            '',
            $plugin_output_new
        ) ?? $plugin_output_new;

        if (isset($_SERVER['HTTP_REFERER'])) {
            $pagetag = str_replace($this->getService(RuntimeConfig::class)['base_url'], '', $_SERVER['HTTP_REFERER']);
            if ($this->getService(UrlFormatter::class)->isWikiName($pagetag) && in_array(
                $pagetag,
                LayoutService::PAGES
            )) {
                $hidden = '<input type="hidden" name="returnto" value="' . $this->getService(UrlFormatter::class)->href('', $pagetag) . '" />' . "\n";
                $plugin_output_new = str_replace('<span class="theme-container">', '<span class="theme-container">' . $hidden, $plugin_output_new);
            }
        }

        if (!$this->getService(AclService::class)->hasAccess('write')) {
            $body = '<div class="yeswiki-page-widget page-widget page">' . "\n"
                . '<div class="yw-alert yw-alert--danger">'
                . _t('LOGIN_NOT_AUTORIZED_EDIT') . '. ' . _t('LOGIN_PLEASE_REGISTER') . '.'
                . '</div><!-- end .alert -->' . "\n"
                . $this->getService(MarkdownFormatterService::class)->format('{{login template="login-form.twig" signupurl="0"}}' . "\n\n")
                . '</div><!-- end .page -->' . "\n";
            $output = $this->getService(TemplateEngine::class)->renderHead()
                . '<body class="login-body">' . "\n" . $body . "\n</body>\n</html>";
            $this->getService(Redirector::class)->terminate($output);
        }

        return $plugin_output_new . (string)ob_get_clean();
    }

    private function emit(): void
    {
        $output = '';

        $isWikiHibernated = $this->getService(HibernationService::class)->isWikiHibernated();

        $request = $this->getService(CurrentRequest::class)->get();

        if ($this->getService(AclService::class)->hasAccess('write') && $this->getService(AclService::class)->hasAccess('read') && !$isWikiHibernated) {
            $submit = $request->request->get('submit') ?: false;

            $previous = $request->request->get('previous') ?: (isset($this->getService(PageContext::class)->getPage()['id']) ? $this->getService(PageContext::class)->getPage()['id'] : null);

            [$editing, $source] = $this->editingAndSourceLanguages();
            $translating = $editing !== $source;
            $isNewPage = empty($this->getService(PageContext::class)->getPage());
            if ($isNewPage && $submit === false && $this->showsTemplateChooser()) {
                return;
            }
            $fromTemplate = $isNewPage ? $this->templateStartedFrom() : '';
            $storedBody = $this->getService(PageContext::class)->getPage()['body'] ?? [];
            $this->getService(LanguageSwitch::class)->writing(
                $this->getService(TranslatableContent::class)->editingLanguages(
                    $source,
                    $editing,
                    $storedBody,
                    $this->translatablePaths()
                )
            );
            $shownBody = $fromTemplate !== ''
                ? ($this->getService(PageTemplates::class)->bodyOf($fromTemplate) ?? [])
                : $this->bodyForEditing($storedBody, $editing, $source);

            $body = (string)($request->request->get('body') ?: PageBody::content($shownBody));

            $cancelUrl = $this->incomingUrl()
                ?? $this->getService(UrlFormatter::class)->href(WikiUrls::iframeSuffixFor());

            $pageFields = $this->contentFormFields();
            $previousBody = $storedBody;
            $editedBody = $submit === false
                ? $shownBody
                : $this->applyPostedPageFields($shownBody);

            if ($submit == 'preview') {
                $temp = $this->getService(InclusionStack::class)->replace();
                $this->getService(InclusionStack::class)->register($this->getService(PageContext::class)->getTag());
                $output .= $this->getService(TemplateEngine::class)->renderSafely('@core/handlers/edit.twig', [
                    'previous' => $previous,
                    'handler' => WikiUrls::iframeSuffixFor() ? 'editiframe' : 'edit',
                    'cancelUrl' => $cancelUrl,
                    'body' => empty($body) ? '' : htmlspecialchars($body, ENT_COMPAT, YW_CHARSET),
                    'preview' => true,
                    'bodyPreview' => $this->getService(MarkdownFormatterService::class)->format($body),
                    'botGuardFields' => $this->getService(BotGuard::class)->fields(),
                    'saveValue' => InputFilter::EDIT_PAGE_SUBMIT_VALUE,
                    'deleteUrl' => $this->deleteUrl(),
                    'hasContent' => $pageFields['hasContent'],
                    'sourceBody' => $translating ? PageBody::content(Translations::strip($storedBody)) : '',
                    'editLanguage' => $editing,
                    'editingTranslation' => $translating,
                    'fieldsBeforeContent' => $this->renderContentFields($pageFields['before'], $editedBody),
                    'fieldsAfterContent' => $this->renderContentFields($pageFields['after'], $editedBody),
                    'fromTemplate' => $fromTemplate,
                    'templateMark' => $this->templateMarkState($isNewPage),
                    'layoutPanel' => $this->layoutPanel($isNewPage, false),
                ]);
                $this->getService(InclusionStack::class)->replace($temp);
            } else {
                if ($submit == InputFilter::EDIT_PAGE_SUBMIT_VALUE && $this->getService(PageContext::class)->getPage() && $this->getService(PageContext::class)->getPage()['id'] != $request->request->get('previous')) {
                    $error = _t('EDIT_ALERT_ALREADY_SAVED_BY_ANOTHER_USER');
                    $submit = false;
                }

                if ($submit == InputFilter::EDIT_PAGE_SUBMIT_VALUE && ($refusal = $this->getService(BotGuard::class)->check($request)) !== null) {
                    $error = $this->getService(BotGuard::class)->message($refusal);
                    $submit = false;
                }

                $layoutChanges = [];
                if ($submit == InputFilter::EDIT_PAGE_SUBMIT_VALUE) {
                    try {
                        $layoutChanges = PageLayout::changes(
                            $this->getService(PageManager::class)->getMetadata((string)$this->getService(PageContext::class)->getTag()) ?? [],
                            $this->getService(PageLayout::class)->fromForm($request->request->all(), (string)$this->getService(PageContext::class)->getTag())
                        );
                    } catch (\InvalidArgumentException $invalid) {
                        $error = $invalid->getMessage();
                        $submit = false;
                    }
                }

                if ($submit == InputFilter::EDIT_PAGE_SUBMIT_VALUE) {
                    $body = rtrim(str_replace("\r", '', $body));

                    $newBody = $editedBody;
                    if ($pageFields['hasContent']) {
                        $newBody[PageBody::CONTENT] = $body;
                    }

                    if ($translating) {
                        $newBody = Translations::with(
                            $previousBody,
                            $editing,
                            array_intersect_key($newBody, array_flip($pageFields['translatable']))
                        );
                    }

                    $unchanged = $layoutChanges === [] && !empty($previousBody) && PageBody::equals(
                        array_merge($previousBody, $pageFields['hasContent']
                            ? [PageBody::CONTENT => rtrim(PageBody::content($previousBody))]
                            : []),
                        $newBody
                    );

                    $after = $this->incomingUrl() ?? $this->getService(UrlFormatter::class)->href(WikiUrls::iframeSuffixFor());

                    if ($unchanged) {
                        $this->applyTemplateMark();
                        $this->getService(FlashMessageService::class)->setMessage(_t('EDIT_NO_CHANGE_MSG'));
                        $this->getService(Redirector::class)->redirect($after);
                    } else {
                        $parent = (string)($this->getService(PageContext::class)->getPage()['parent'] ?? '');
                        $this->getService(PageManager::class)->save(
                            $this->getService(PageContext::class)->getTag(),
                            $newBody,
                            $parent,
                            false,
                            null,
                            null,
                            null,
                            $layoutChanges === [] ? null : $layoutChanges
                        );
                        if ($fromTemplate !== '') {
                            $this->getService(PageTemplates::class)->copyFiles($fromTemplate, (string)$this->getService(PageContext::class)->getTag());
                        }
                        $this->applyTemplateMark();

                        if ($parent !== '') {
                            $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href(WikiUrls::iframeSuffixFor(), $parent) . '#' . $this->getService(PageContext::class)->getTag());
                        } else {
                            $this->getService(Redirector::class)->redirect($after);
                        }
                    }
                } else {
                    if ($request->query->has('appendcomment') || $request->request->has('appendcomment')) {
                        $body = trim($body) . "\n\n----\n\n-- " . $this->getService(AuthenticationService::class)->getLoggedUserName() . ' (' . date('c') . ')';
                    }

                    $passwordForEditing = !empty($this->getService(RuntimeConfig::class)['password_for_editing']) && $request->request->has('password_for_editing');

                    $output .= $this->getService(TemplateEngine::class)->renderSafely('@core/handlers/edit.twig', [
                        'error' => $error ?? null,
                        'botGuardFields' => $this->getService(BotGuard::class)->fields(),
                        'previous' => $previous,
                        'handler' => WikiUrls::iframeSuffixFor() ? 'editiframe' : 'edit',
                        'passwordForEditing' => $passwordForEditing,
                        'cancelUrl' => $cancelUrl,
                        'body' => empty($body) ? '' : htmlspecialchars($body, ENT_COMPAT, YW_CHARSET),
                        'saveValue' => InputFilter::EDIT_PAGE_SUBMIT_VALUE,
                        'deleteUrl' => $this->deleteUrl(),
                        'preview' => false,
                        'hasContent' => $pageFields['hasContent'],
                        'sourceBody' => $translating ? PageBody::content(Translations::strip($storedBody)) : '',
                        'editLanguage' => $editing,
                        'editingTranslation' => $translating,
                        'fieldsBeforeContent' => $this->renderContentFields($pageFields['before'], $editedBody),
                        'fieldsAfterContent' => $this->renderContentFields($pageFields['after'], $editedBody),
                        'fromTemplate' => $fromTemplate,
                        'templateMark' => $this->templateMarkState($isNewPage),
                        'layoutPanel' => $this->layoutPanel($isNewPage, isset($error)),
                    ]);
                }
            }
        } else {
            $output .= '<i>' . _t('EDIT_NO_WRITE_ACCESS') . "</i>\n";
            if ($isWikiHibernated) {
                $output .= $this->getService(HibernationNotice::class)->getMessageWhenHibernated();
            }
        }

        $output = $output . "\n" . '<hr class="hr_clear" />' . "\n";

        if (!WikiUrls::iframeSuffixFor()) {
            echo $this->getService(TemplateEngine::class)->renderPage($output);
        } else {
            echo $output;
        }
    }

    /** The page layout options under the editor, filled from what was just posted, the link that opened a new page, or the page's Metadata. */
    private function layoutPanel(bool $isNewPage, bool $open): string
    {
        $request = $this->getService(CurrentRequest::class)->get();
        $tag = (string)$this->getService(PageContext::class)->getTag();
        $pageLayout = $this->getService(PageLayout::class);
        $values = $isNewPage
            ? array_intersect_key($request->query->all(), array_flip(PageLayout::KEYS))
            : ($this->getService(PageManager::class)->getMetadata($tag) ?? []);
        if ($request->request->get('layout_shown') === '1') {
            try {
                $values = array_merge($values, $pageLayout->fromForm($request->request->all(), $tag));
            } catch (\InvalidArgumentException) {
                foreach ([PageLayout::CUSTOM_CSS, PageLayout::OG_TITLE, PageLayout::OG_DESCRIPTION, PageLayout::OG_IMAGE] as $key) {
                    $values[$key] = (string)$request->request->get($key, '');
                }
            }
        }
        $value = fn (string $key): string => is_string($values[$key] ?? null) ? $values[$key] : '';

        $roles = [];
        foreach (LayoutService::PAGES as $role) {
            $roles[$role] = [
                'mode' => match ($value($role)) {
                    '' => '',
                    PageLayout::NONE => PageLayout::NONE,
                    default => 'page',
                },
                'page' => $value($role) === PageLayout::NONE ? '' : $value($role),
            ];
        }
        $languages = $this->getService(LanguageService::class);
        $showsTheme = empty($this->getService(RuntimeConfig::class)['hide_action_template']);
        $selector = $this->getService(ThemeSelectorRenderer::class);

        return $selector->renderWithThemeSelector('@core/handlers/edit-layout.twig', [
            'open' => $open,
            'navbar' => $value(PageLayout::NAVBAR),
            'navbarPositions' => PageLayout::NAVBAR_POSITIONS,
            'wikiNavbar' => $this->getService(LayoutService::class)->wikiNavbarPosition(),
            'roles' => $roles,
            'pages' => $this->getService(PageManager::class)->tagsOfType(PageType::DEFAULT),
            'showsTheme' => $showsTheme,
            'ownTheme' => $value('theme') !== '' && $value('style') !== '' && $value('squelette') !== '',
            'bgimg' => $value('bgimg'),
            'bgselector' => $showsTheme ? $selector->backgroundSelector() : '',
            'lang' => $value(PageLayout::LANG),
            'availableLanguages' => $languages->availableLanguages(),
            'languagesList' => $languages->languagesList(),
            'mayEditCss' => $pageLayout->mayEditCss($tag),
            'css' => $value(PageLayout::CUSTOM_CSS),
            'ogTitle' => $value(PageLayout::OG_TITLE),
            'ogDescription' => $value(PageLayout::OG_DESCRIPTION),
            'ogImage' => $value(PageLayout::OG_IMAGE),
            'ogTitleMax' => PageLayout::OG_TITLE_MAX_LENGTH,
            'ogDescriptionMax' => PageLayout::OG_DESCRIPTION_MAX_LENGTH,
        ]);
    }

    /** Shows the choice between a blank page and the templates, before a new page's editor, when there are templates and none was chosen yet. */
    private function showsTemplateChooser(): bool
    {
        $request = $this->getService(CurrentRequest::class)->get();
        if ($request->query->has('template') || $request->request->has('body') || $request->query->has('body')) {
            return false;
        }
        $templates = $this->getService(PageTemplates::class)->available();
        if ($templates === []) {
            return false;
        }

        $tag = (string)$this->getService(PageContext::class)->getTag();
        $handler = WikiUrls::iframeSuffixFor() ? 'editiframe' : 'edit';
        $query = array_filter(
            $request->query->all(),
            static fn ($value, $key): bool => is_string($value) && $key !== 'wiki' && !str_contains((string)$key, '/'),
            ARRAY_FILTER_USE_BOTH
        );
        $urls = $this->getService(UrlFormatter::class);
        $choices = array_map(fn (array $template): array => $template + [
            'url' => $urls->href($handler, $tag, $query + $this->getService(PageTemplates::class)->lookOf($template['tag']) + ['newpage' => '1', 'template' => $template['tag']], false),
        ], $templates);

        $output = $this->getService(TemplateEngine::class)->render('@core/handlers/edit-choose-template.twig', [
            'tag' => $tag,
            'templates' => $choices,
            'blankUrl' => $urls->href($handler, $tag, $query + ['template' => ''], false),
            'cancelUrl' => $this->incomingUrl() ?? $urls->href(WikiUrls::iframeSuffixFor(), $tag, null, false),
        ]);
        echo WikiUrls::iframeSuffixFor() ? $output : $this->getService(TemplateEngine::class)->renderPage($output);

        return true;
    }

    /** The template a new page is being written from, once the user may use it. */
    private function templateStartedFrom(): string
    {
        $request = $this->getService(CurrentRequest::class)->get();
        $tag = $request->request->get('from_template') ?? $request->query->get('template');
        if (!is_string($tag) || $tag === '') {
            return '';
        }

        return $this->getService(PageTemplates::class)->bodyOf($tag) === null ? '' : $tag;
    }

    /** Whether the page is a template, for an admin editing an ordinary page; null for anyone else. */
    private function templateMarkState(bool $isNewPage): ?bool
    {
        $tag = (string)$this->getService(PageContext::class)->getTag();
        $page = $this->getService(PageContext::class)->getPage();
        if (!$this->getService(AclService::class)->isAdmin() || ($page['parent'] ?? '') !== ''
            || ($isNewPage ? false : $this->getService(PageManager::class)->typeOf($tag) !== PageType::DEFAULT)) {
            return null;
        }
        $request = $this->getService(CurrentRequest::class)->get();
        if ($request->request->has('page_template_shown')) {
            return $request->request->get('page_template') === '1';
        }

        return !$isNewPage && $this->getService(PageTemplates::class)->isTemplate($tag);
    }

    /** Marks or unmarks the page as a template, as the admin's checkbox says. */
    private function applyTemplateMark(): void
    {
        $request = $this->getService(CurrentRequest::class)->get();
        if ($request->request->has('page_template_shown')) {
            $this->getService(PageTemplates::class)->mark(
                (string)$this->getService(PageContext::class)->getTag(),
                $request->request->get('page_template') === '1'
            );
        }
    }

    /** Where the link that opened this editor asked to return to, if it may be trusted. */
    private function incomingUrl(): ?string
    {
        $request = $this->getService(CurrentRequest::class)->get();
        $incoming = $request->request->get('incomingurl') ?? $request->query->get('incomingurl');

        if (!is_string($incoming) || trim($incoming) === '') {
            return null;
        }

        return $this->getService(UrlFormatter::class)->isInternal($incoming) ? trim($incoming) : null;
    }
}
