<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Service\ContentTypeResolver;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Controller\CaptchaController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\HashCashService;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Identity\Service\PasswordForEditingService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\FlashMessageService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\InclusionStack;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Kernel\Service\WikiUrls;
use YesWiki\Render\Service\HibernationNotice;
use YesWiki\Render\Service\LayoutService;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Render\Service\ThemeManager;
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

            if (
                $this->getService(RuntimeConfig::class)['use_hashcash']
                && isset($_POST['submit']) && $_POST['submit'] == InputFilter::EDIT_PAGE_SUBMIT_VALUE
                && !$this->getService(HashCashService::class)->checkHashcash()
            ) {
                $error = '<div class="alert alert-danger"><a href="#" data-dismiss="alert" class="close">&times;</a>' . _t('HASHCASH_ERROR_PAGE_UNSAVED') . '</div>';
                $_POST['submit'] = '';
            }

            list($state, $error) = $this->getService(CaptchaController::class)->checkCaptchaBeforeSave();

            if ($state) {
                unset($error);
            }

            if ($this->getService(RuntimeConfig::class)['use_alerte']) {
                $js = "// par défaut, pas de popup d'alerte pour quitter la page
                var showPopup = false;

                // Delegated on document, NOT bound to the elements directly: this script is
                // an declared asset and so runs well before the form it guards exists in the
                // DOM (script ~line 121, <form id=\"ACEditor\"> ~line 353). getElementById()
                // returned null, the listener was silently never attached, and saving a page
                // therefore always raised the \"leave without saving?\" dialog it was meant to
                // suppress. Delegation also survives an htmx body swap, the way ticket 16
                // had to relearn for every other initialiser.

                // on demande a faire apparaitre la popup si la page a été modifiée
                // (the ACeditor sets showPopup itself; this covers a plain textarea)
                document.addEventListener('input', function(e) {
                    if (e.target && e.target.id === 'body') {
                        showPopup = true;
                    }
                });

                // on annule la popup si l'on sauve la page
                document.addEventListener('submit', function(e) {
                    if (e.target && (e.target.id === 'ACEditor' || e.target.id === 'formulaire')) {
                        showPopup = false;
                    }
                }, true);

                // si l'on quitte la page, on affiche la popup si besoin
                window.addEventListener('beforeunload', function(e) {
                    if (showPopup) {
                        e.preventDefault();
                        e.returnValue = '';
                    }
                });

                // Ticket 16: internal links load through htmx, and an htmx navigation never
                // fires beforeunload -- so without this a click while editing would discard
                // the edit silently. htmx:confirm is fired before every request and can be
                // cancelled, which is what makes this the same guard rather than a second one.
                document.addEventListener('htmx:confirm', function(e) {
                    if (!showPopup) return;
                    e.preventDefault();
                    if (window.confirm(_t('EDIT_LEAVE_WITHOUT_SAVING'))) {
                        showPopup = false;
                        e.detail.issueRequest(true);
                    }
                });";

                $this->getService(AssetRegistry::class)->addJs($js);
            }
        }

        if (isset($_GET['body']) && !isset($_POST['body'])) {
            $_POST['body'] = '======' . $_GET['body'] . '======';
        }

        $this->getService(AssetRegistry::class)->addJsFile('javascripts/change-theme.js');
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/template-edit.js');

        $entryManager = $this->getService(EntryManager::class);
        $entryController = $this->getService(EntryController::class);

        if ($this->getService(AclService::class)->hasAccess('write') && $entryManager->isEntry($this->getService(PageContext::class)->getTag())) {
            $plugin_output_new = '<div class="page">';
            ob_start();
            $plugin_output_new .= $this->isWikiHibernated()
                ? $this->getMessageWhenHibernated()
                : $entryController->update($this->getService(PageContext::class)->getTag());
            $plugin_output_new .= ob_get_contents();
            ob_end_clean();
            $plugin_output_new .= '</div>';

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

    /**
     * The fields of the form describing **this row's own Content type**, split around the one holding the markup.
     *
     * @return array{before: list<BazarField>, after: list<BazarField>, hasContent: bool}
     */
    private function contentFormFields(): array
    {
        $split = ['before' => [], 'after' => [], 'hasContent' => false];

        $form = $this->getService(ContentTypeResolver::class)
            ->formForEditing($this->getService(PageContext::class)->getTag());
        if ($form === null) {
            $split['hasContent'] = true;

            return $split;
        }

        $side = 'before';
        foreach ($form['prepared'] ?? [] as $field) {
            if (!$field instanceof BazarField) {
                continue;
            }
            if ($field->getPropertyName() === PageBody::CONTENT) {
                $split['hasContent'] = true;
                $side = 'after';
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
     * The rendered inputs of those fields, filled from the page's current body.
     *
     * @param list<BazarField>     $fields
     * @param array<string, mixed> $body
     *
     * @return list<string>
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
     * Merge the posted values of the Page form's fields into the body being saved.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
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

        if ($this->getService(AclService::class)->hasAccess('write') && $this->getService(AclService::class)->hasAccess('read')) {
            if (!isset($_POST['submit']) || $_POST['submit'] != InputFilter::EDIT_PAGE_SUBMIT_VALUE) {
                if ($this->getService(RuntimeConfig::class)['use_hashcash']) {
                    $hashCash = $this->getService(HashCashService::class);
                    $hashCashCode = $hashCash->getJavascriptCode();
                    $plugin_output_new = preg_replace(
                        '/\<hr class=\"hr_clear\" \/\>/',
                        $hashCashCode . '<hr class="hr_clear" />',
                        $plugin_output_new
                    );
                }
                $plugin_output_new = (string)$plugin_output_new;
                $this->getService(CaptchaController::class)->renderCaptcha($plugin_output_new);
            }
        }

        $plugin_output_new = preg_replace(
            '/(\\{\\{template)(.*?)(\\}\\})/is',
            '',
            $plugin_output_new
        ) ?? $plugin_output_new;

        $themeManager = $this->getService(ThemeManager::class);

        // SEUL_ADMIN_ET_PROPRIO_CHANGENT_THEME used to gate this on admin-or-owner; it is
        // defined false in src/constants.php and nothing ever flips it, so the gate was
        // write and read access all along -- said here instead of hidden behind a dead constant
        if (empty($this->getService(RuntimeConfig::class)['hide_action_template'])
            && $this->getService(AclService::class)->hasAccess('write')
            && $this->getService(AclService::class)->hasAccess('read')
        ) {
            $selecteur = '
        <div id="graphical_options" class="yw-modal">' . "\n" .
                '  <div class="yw-modal__dialog">' . "\n" .
                '    <div class="yw-modal__content">' . "\n" .
                '      <div class="yw-modal__header">' . "\n" .
                '        <a class="yw-close" data-yw-dismiss="modal">&times;</a>' . "\n" .
                '        <h3 class="yw-modal__title">' . _t('TEMPLATE_CUSTOM_GRAPHICS') . ' ' . $this->getService(PageContext::class)->getTag() . '</h3>' . "\n" .
                '      </div>' . "\n" .
                '      <div class="yw-modal__body">' . "\n";
            $selecteur .= $this->getService(ThemeSelectorRenderer::class)->showFormThemeSelector('edit');
            $selecteur .= '
              </div>' . "\n" .
                '      <div class="yw-modal__footer">' . "\n" .
                '        <a href="#" class="yw-btn button_cancel" data-yw-dismiss="modal">' . _t('TEMPLATE_CANCEL') . '</a>' . "\n" .
                '        <a href="#" class="yw-btn yw-btn--primary button_save" data-yw-dismiss="modal">' . _t('TEMPLATE_APPLY') . '</a>' . "\n" .
                '      </div>' . "\n" .
                '    </div>' . "\n" .
                '  </div>' . "\n" .
                '</div> <!-- /#graphical_options -->' . "\n";

            $selecteur .= '<input id="hiddentheme" type="hidden" name="theme" value="' . $themeManager->getFavoriteTheme() . '" />' . "\n";
            $selecteur .= '<input id="hiddensquelette" type="hidden" name="squelette" value="' . $themeManager->getFavoriteSquelette() . '" />' . "\n";
            $selecteur .= '<input id="hiddenstyle" type="hidden" name="style" value="' . $themeManager->getFavoriteStyle() . '" />' . "\n";
            $selecteur .= '<input id="hiddenbgimg" type="hidden" name="bgimg" value="' . $themeManager->getFavoriteBackgroundImage() . '" />' . "\n";

            $plugin_output_new = preg_replace('/<\/body>/', $selecteur . "\n" . '</body>', $plugin_output_new) ?? $plugin_output_new;
            $changetheme = true;
        } else {
            $changetheme = false;
        }

        $hidden = '';

        if (isset($_SERVER['HTTP_REFERER'])) {
            $pagetag = str_replace($this->getService(RuntimeConfig::class)['base_url'], '', $_SERVER['HTTP_REFERER']);
            if ($this->getService(UrlFormatter::class)->isWikiName($pagetag) && in_array(
                $pagetag,
                LayoutService::PAGES
            )) {
                $hidden = '<input type="hidden" name="returnto" value="' . $this->getService(UrlFormatter::class)->href('', $pagetag) . '" />' . "\n";
            }
        }

        $html = $hidden;
        $target = '<span class="theme-container">';
        if ($changetheme) {
            $html .= '<a class="yw-btn" data-yw-modal-target="#graphical_options">' . _t('TEMPLATE_THEME') . '</a>';
        }
        $plugin_output_new = str_replace($target, $target . $html, $plugin_output_new);

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

            $body = (string)($request->request->get('body') ?: (isset($this->getService(PageContext::class)->getPage()['body']) ? PageBody::content($this->getService(PageContext::class)->getPage()['body']) : null));

            $cancelUrl = $this->getService(UrlFormatter::class)->href(WikiUrls::iframeSuffixFor());

            $pageFields = $this->contentFormFields();
            $previousBody = $this->getService(PageContext::class)->getPage()['body'] ?? [];
            $editedBody = $submit === false
                ? $previousBody
                : $this->applyPostedPageFields($previousBody);

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
                    'saveValue' => InputFilter::EDIT_PAGE_SUBMIT_VALUE,
                    'deleteUrl' => $this->deleteUrl(),
                    'hasContent' => $pageFields['hasContent'],
                    'fieldsBeforeContent' => $this->renderContentFields($pageFields['before'], $editedBody),
                    'fieldsAfterContent' => $this->renderContentFields($pageFields['after'], $editedBody),
                ]);
                $this->getService(InclusionStack::class)->replace($temp);
            } else {
                if ($submit == InputFilter::EDIT_PAGE_SUBMIT_VALUE && $this->getService(PageContext::class)->getPage() && $this->getService(PageContext::class)->getPage()['id'] != $request->request->get('previous')) {
                    $error = _t('EDIT_ALERT_ALREADY_SAVED_BY_ANOTHER_USER');
                    $submit = false;
                }

                if ($submit == InputFilter::EDIT_PAGE_SUBMIT_VALUE) {
                    $body = rtrim(str_replace("\r", '', $body));

                    $newBody = $editedBody;
                    if ($pageFields['hasContent']) {
                        $newBody[PageBody::CONTENT] = $body;
                    }

                    $unchanged = !empty($previousBody) && PageBody::equals(
                        array_merge($previousBody, $pageFields['hasContent']
                            ? [PageBody::CONTENT => rtrim(PageBody::content($previousBody))]
                            : []),
                        $newBody
                    );

                    $after = $this->incomingUrl() ?? $this->getService(UrlFormatter::class)->href(WikiUrls::iframeSuffixFor());

                    if ($unchanged) {
                        $this->getService(FlashMessageService::class)->setMessage(_t('EDIT_NO_CHANGE_MSG'));
                        $this->getService(Redirector::class)->redirect($after);
                    } else {
                        $this->getService(PageManager::class)->save($this->getService(PageContext::class)->getTag(), $newBody, !empty($this->getService(PageContext::class)->getPage()['parent']) ? $this->getService(PageContext::class)->getPage()['parent'] : '');

                        $this->getService(TagsManager::class)->reindex(
                            $this->getService(PageContext::class)->getTag(),
                            TagsManager::keywordsOf(['body' => $newBody])
                        );

                        if (($this->getService(PageContext::class)->getPage() ?? [])['parent']) {
                            $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href(WikiUrls::iframeSuffixFor(), ($this->getService(PageContext::class)->getPage() ?? [])['parent']) . '#' . $this->getService(PageContext::class)->getTag());
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
                        'previous' => $previous,
                        'handler' => WikiUrls::iframeSuffixFor() ? 'editiframe' : 'edit',
                        'passwordForEditing' => $passwordForEditing,
                        'cancelUrl' => $cancelUrl,
                        'body' => empty($body) ? '' : htmlspecialchars($body, ENT_COMPAT, YW_CHARSET),
                        'saveValue' => InputFilter::EDIT_PAGE_SUBMIT_VALUE,
                        'deleteUrl' => $this->deleteUrl(),
                        'preview' => false,
                        'hasContent' => $pageFields['hasContent'],
                        'fieldsBeforeContent' => $this->renderContentFields($pageFields['before'], $editedBody),
                        'fieldsAfterContent' => $this->renderContentFields($pageFields['after'], $editedBody),
                    ]);
                }
            }
        } else {
            $output .= '<i>' . _t('EDIT_NO_WRITE_ACCESS') . "</i>\n";
            if ($isWikiHibernated) {
                $output .= $this->getService(HibernationNotice::class)->getMessageWhenHibernated();
            }
        }

        $output = '<div class="page">' . "\n" . $output . "\n" . '<hr class="hr_clear" />' . "\n" . '</div>' . "\n";

        if (!WikiUrls::iframeSuffixFor()) {
            echo $this->getService(TemplateEngine::class)->renderPage($output);
        } else {
            echo $output;
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
