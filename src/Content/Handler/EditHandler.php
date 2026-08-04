<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Service\ContentTypeResolver;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\LinkTracker;
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
use YesWiki\Kernel\Service\FlashMessageService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\InclusionStack;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\HibernationNotice;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Render\Service\ThemeManager;
use YesWiki\Render\Service\ThemeSelectorRenderer;
use YesWiki\Search\Service\TagsManager;

/**
 * `/PageName/edit` -- converted from the procedural handlers/page/edit.php by ticket 06.
 */
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
            // handlers commonly end in exit()/redirect, which throw; keep what was already
            // printed and close the buffer either way (see ticket 06)
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return $this->emitAfter((string)ob_get_clean());
    }

    /**
     * Ran as a before-callback until ticket 06 merged it in.
     */
    private function emitBefore(): void
    {
        // merged from handlers/page/__edit.php (ticket 06: core does not hook itself)
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
                // error used in edit.php
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

        // Si une valeur de body est passee en paramétre GET (et pas POST) on l'ajoute en titre dans la nouvelle page vierge
        if (isset($_GET['body']) && !isset($_POST['body'])) {
            $_POST['body'] = '======' . $_GET['body'] . '======';
        }

        $this->getService(AssetRegistry::class)->addJsFile('javascripts/change-theme.js');
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/template-edit.js');

        // merged from handlers/__EditHandler.php (ticket 06: core does not hook itself)
        // relocated from tools/bazar/handlers/__EditHandler.php (ticket 24): if the page
        // being edited is a bazar entry, show the entry-edit form instead of the default
        // wiki-page edit form, and stop there -- runs before the tag-saving logic below,
        // matching this callback's actual pre-relocation execution order.
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

            // we use die so that the script stop there and the default handler of wiki isn't called
            $this->getService(Redirector::class)->terminate($plugin_output_new);
        }
    }

    /**
     * Where "delete this page" goes, for whoever may -- or null.
     *
     * The editor carries it because the editor is where the actions are while you are
     * editing: `{{editbar}}` steps aside there (offering "edit this page" to someone
     * already editing it is an offer to go where they are), and deleting what you are
     * looking at is the one thing you might want instead of saving it. Same rule the edit
     * bar applies in read mode: the owner, an admin, and not while the wiki is hibernated.
     */
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
     * The fields of the form describing **this row's own Content type**, split around the
     * one holding the markup.
     *
     * Every Content is a form since ticket 10, so the editor asks which one rather than
     * assuming Page: an account row edited here is the User form, and it has no `content`
     * field, so no markup editor is offered and none is written. Reaching for the Page
     * form regardless was how a user or a file ended up with a page-shaped body.
     *
     * `content` is the one field the editor renders itself, so the fields declared before
     * it bracket the ACeditor above and those after it below -- the designer lays a page
     * out the same way it lays an entry out.
     *
     * @return array{before: list<BazarField>, after: list<BazarField>, hasContent: bool}
     */
    private function contentFormFields(): array
    {
        $split = ['before' => [], 'after' => [], 'hasContent' => false];

        $form = $this->getService(ContentTypeResolver::class)
            ->formFor($this->getService(PageContext::class)->getTag());
        if ($form === null) {
            // a row this concept does not describe (a form, a list) keeps the plain
            // markup editor it has always had
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
            // a field that restates the tag is not typed into: it *is* the row's identity
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
        // fields are written for bazar entries, which carry their own tag and form_id in
        // the body; a page, an account or a file gets them from its row and its type
        $form = $this->getService(ContentTypeResolver::class)
            ->formFor($this->getService(PageContext::class)->getTag());
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
     * Keywords are the one field that does not simply land in the body as posted: ticket
     * 09 made `body.keywords` a list of keywords, with the tag triples a derived index
     * rebuilt from it -- see the reindex() call at the save site.
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
                // A file field answers with the keys to DROP as well as the ones to write
                // -- `oldimage_x`, the hidden input carrying the previous filename. That is
                // an instruction about the submission, not body content: written through,
                // it became a `fields-to-remove` key in the saved body (EntryManager acts
                // on it and unsets it; nothing here did)
                if ($key === 'fields-to-remove') {
                    continue;
                }
                // An empty value for a key the body does not have yet writes nothing:
                // the form posts every field on every save, so a page seeded without a
                // `title` would otherwise gain `title => ''` the first time it is saved.
                // That is a difference, so PageBody::equals() below never matched and
                // "saved with no change" could not be detected -- every resubmit created
                // a revision instead of warning. Clearing a key that DOES exist still
                // records the empty string, which is a real edit. Same rule keywords
                // already follow above.
                if ($value === '' && !array_key_exists($key, $body)) {
                    continue;
                }
                $body[$key] = $value;
            }
        }

        return $body;
    }

    /**
     * Ran as an after-callback until ticket 06 merged it in. Receives the rendered output
     * as $plugin_output_new -- the name the hooks already used -- because several rewrite
     * it rather than appending.
     */
    private function emitAfter(string $plugin_output_new): string
    {
        ob_start();

        if ($this->getService(AclService::class)->hasAccess('write') && $this->getService(AclService::class)->hasAccess('read')) {
            // Edition
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

        // remove the {{template ...}} action from the page body
        $plugin_output_new = preg_replace(
            '/(\\{\\{template)(.*?)(\\}\\})/is',
            '',
            $plugin_output_new
        );

        $themeManager = $this->getService(ThemeManager::class);

        // personnalisation graphique que dans le cas ou on est autorise
        if ((!isset($this->getService(RuntimeConfig::class)['hide_action_template']) or (isset($this->getService(RuntimeConfig::class)['hide_action_template']) && !$this->getService(RuntimeConfig::class)['hide_action_template']))
            && ($this->getService(AclService::class)->hasAccess('write') && $this->getService(AclService::class)->hasAccess('read') && (!SEUL_ADMIN_ET_PROPRIO_CHANGENT_THEME || (SEUL_ADMIN_ET_PROPRIO_CHANGENT_THEME && ($this->getService(AclService::class)->isAdmin() || $this->getService(AclService::class)->isOwner()))))
        ) {
            // graphical options : theme and background image
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

            // quand le changement des valeurs du template est cache, il faut stocker les valeurs deja entrees pour ne pas retourner au template par defaut
            $selecteur .= '<input id="hiddentheme" type="hidden" name="theme" value="' . $themeManager->getFavoriteTheme() . '" />' . "\n";
            $selecteur .= '<input id="hiddensquelette" type="hidden" name="squelette" value="' . $themeManager->getFavoriteSquelette() . '" />' . "\n";
            $selecteur .= '<input id="hiddenstyle" type="hidden" name="style" value="' . $themeManager->getFavoriteStyle() . '" />' . "\n";
            $selecteur .= '<input id="hiddenbgimg" type="hidden" name="bgimg" value="' . $themeManager->getFavoriteBackgroundImage() . '" />' . "\n";

            // on rajoute la personnalisation graphique
            $plugin_output_new = preg_replace('/<\/body>/', $selecteur . "\n" . '</body>', $plugin_output_new);
            $changetheme = true;
        } else {
            $changetheme = false;
        }

        $hidden = '';
        // cas des pages speciales
        if (isset($_SERVER['HTTP_REFERER'])) {
            $pagetag = str_replace($this->getService(RuntimeConfig::class)['base_url'], '', $_SERVER['HTTP_REFERER']);
            if ($this->getService(UrlFormatter::class)->isWikiName($pagetag) && in_array(
                $pagetag,
                ['PageFooter', 'PageHeader', 'PageTitre', 'PageRapideHaut', 'PageMenuHaut', 'PageMenu']
            )) {
                $hidden = '<input type="hidden" name="returnto" value="' . $this->getService(UrlFormatter::class)->href('', $pagetag) . '" />' . "\n";
            }
        }

        $html = $hidden;
        $target = '<span class="theme-container">';
        if ($changetheme) {
            // Adds change theme button
            $html .= '<a class="yw-btn" data-yw-modal-target="#graphical_options">' . _t('TEMPLATE_THEME') . '</a>';
        }
        $plugin_output_new = str_replace($target, $target . $html, $plugin_output_new);

        if (!$this->getService(AclService::class)->hasAccess('write')) {
            // built before the head is rendered, so the login form's own assets are in it
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
        // on initialise la sortie:
        $output = '';

        $isWikiHibernated = $this->getService(HibernationService::class)->isWikiHibernated();
        // bare-script handler: $this->services is the container, which exposes the current
        // request as a public property, not via a getRequest() helper (that's only on
        // YesWikiPerformable-derived actions/handlers)
        $request = $this->getService(\YesWiki\Kernel\Service\CurrentRequest::class)->get();

        if ($this->getService(AclService::class)->hasAccess('write') && $this->getService(AclService::class)->hasAccess('read') && !$isWikiHibernated) {
            $submit = $request->request->get('submit') ?: false;

            // fetch fields
            $previous = $request->request->get('previous') ?: (isset($this->getService(PageContext::class)->getPage()['id']) ? $this->getService(PageContext::class)->getPage()['id'] : null);
            // the editor edits prose: the posted field is markup, the fallback is the
            // `content` attribute of the loaded page's body (ticket 09)
            $body = (string)($request->request->get('body') ?: (isset($this->getService(PageContext::class)->getPage()['body']) ? PageBody::content($this->getService(PageContext::class)->getPage()['body']) : null));

            // no addslashes: this is an href now, not a string inside an onclick. Cancel
            // used to be an <a> with no href at all, which is not focusable -- and a link
            // the keyboard cannot reach is no way out of an editor.
            $cancelUrl = $this->getService(UrlFormatter::class)->href(testUrlInIframe());

            // the Page form's own fields (ticket 10). Once the form has been sent back
            // they take the posted values, so a preview or an edit conflict re-renders
            // what was typed rather than what is stored; before that, the stored body is
            // all there is -- reading the posted values of a form nobody submitted would
            // overwrite every field with its default.
            $pageFields = $this->contentFormFields();
            $previousBody = $this->getService(PageContext::class)->getPage()['body'] ?? [];
            $editedBody = $submit === false
                ? $previousBody
                : $this->applyPostedPageFields($previousBody);

            // PREVIEW
            if ($submit == 'preview') {
                $temp = $this->getService(InclusionStack::class)->replace(); // a priori, ça ne sert à rien, mais on ne sait jamais...
                $this->getService(InclusionStack::class)->register($this->getService(PageContext::class)->getTag()); // on simule totalement un affichage normal
                $output .= $this->getService(TemplateEngine::class)->renderSafely('@core/handlers/edit.twig', [
                    'previous' => $previous,
                    'handler' => testUrlInIframe() ? 'editiframe' : 'edit',
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
                    // SAVE AND REDIRECT
                    $body = rtrim(str_replace("\r", '', $body));

                    // the editor edits prose, which is one attribute of a page's body
                    // (ticket 09); the form's other fields -- title, keywords, and
                    // whatever the webmaster added -- come from their own inputs.
                    // A Content type with no `content` field has no prose at all: an
                    // account and a file are not wiki markup, and writing an empty
                    // `content` onto them would give them a page-shaped body they
                    // should never have.
                    $newBody = $editedBody;
                    if ($pageFields['hasContent']) {
                        $newBody[PageBody::CONTENT] = $body;
                    }

                    // "nothing changed" now means the whole body, not just the markup:
                    // retitling a page without touching its prose is a real change
                    $unchanged = !empty($previousBody) && PageBody::equals(
                        array_merge($previousBody, $pageFields['hasContent']
                            ? [PageBody::CONTENT => rtrim(PageBody::content($previousBody))]
                            : []),
                        $newBody
                    );

                    if ($unchanged) {
                        $this->getService(FlashMessageService::class)->setMessage(_t('EDIT_NO_CHANGE_MSG'));
                        $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href(testUrlInIframe()));
                    } else {
                        // add page (revisions)
                        $this->getService(PageManager::class)->save($this->getService(PageContext::class)->getTag(), $newBody, !empty($this->getService(PageContext::class)->getPage()['comment_on']) ? $this->getService(PageContext::class)->getPage()['comment_on'] : '');

                        // the keyword index is derived from the body it was just saved
                        // with, never the other way round (ticket 09)
                        $this->getService(TagsManager::class)->reindex(
                            $this->getService(PageContext::class)->getTag(),
                            TagsManager::keywordsOf(['body' => $newBody])
                        );

                        // now we render it internally so we can write the updated link table.
                        $page = $this->getService(PageManager::class)->getOne($this->getService(PageContext::class)->getTag());
                        if (is_array($page)) {
                            $this->getService(LinkTracker::class)->registerLinks($page, false, false);
                        }

                        // forward
                        if (($this->getService(PageContext::class)->getPage() ?? [])['comment_on']) {
                            $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href(testUrlInIframe(), ($this->getService(PageContext::class)->getPage() ?? [])['comment_on']) . '#' . $this->getService(PageContext::class)->getTag());
                        } else {
                            $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href(testUrlInIframe()));
                        }
                    }
                // (unreachable: redirect() above always throws)
                } else {
                    // RENDER FORM

                    // append a comment?
                    if ($request->query->has('appendcomment') || $request->request->has('appendcomment')) {
                        $body = trim($body) . "\n\n----\n\n-- " . $this->getService(AuthenticationService::class)->getLoggedUserName() . ' (' . date('c') . ')';
                    }

                    $passwordForEditing = !empty($this->getService(RuntimeConfig::class)['password_for_editing']) && $request->request->has('password_for_editing');

                    $output .= $this->getService(TemplateEngine::class)->renderSafely('@core/handlers/edit.twig', [
                        'error' => $error ?? null,
                        'previous' => $previous,
                        'handler' => testUrlInIframe() ? 'editiframe' : 'edit',
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

        // Main Page
        $output = '<div class="page">' . "\n" . $output . "\n" . '<hr class="hr_clear" />' . "\n" . '</div>' . "\n";

        // Header - // Footer
        if (!testUrlInIframe()) {
            echo $this->getService(TemplateEngine::class)->renderPage($output);
        } else {
            echo $output;
        }
    }
}
