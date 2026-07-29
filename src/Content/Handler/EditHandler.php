<?php

namespace YesWiki\Content\Handler;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Controller\EntryController;
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
use YesWiki\Kernel\Service\AssetsManager;
use YesWiki\Kernel\Service\FlashMessageService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\InclusionStack;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\HibernationNotice;
use YesWiki\Render\Service\MarkdownFormatterService;
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
            list($state, $message) = $this->wiki->services->get(PasswordForEditingService::class)->isGrantedPasswordForEditing();
            if (!$state) {
                echo $this->wiki->Header() .
                    $message .
                    $this->wiki->Footer();
                $this->wiki->exit();
            }

            if (
                $this->getService(RuntimeConfig::class)['use_hashcash']
                && isset($_POST['submit']) && $_POST['submit'] == InputFilter::EDIT_PAGE_SUBMIT_VALUE
                && !$this->wiki->services->get(HashCashService::class)->checkHashcash()
            ) {
                $error = '<div class="alert alert-danger"><a href="#" data-dismiss="alert" class="close">&times;</a>' . _t('HASHCASH_ERROR_PAGE_UNSAVED') . '</div>';
                $_POST['submit'] = '';
            }

            list($state, $error) = $this->wiki->services->get(CaptchaController::class)->checkCaptchaBeforeSave();

            if ($state) {
                // error used in edit.php
                unset($error);
            }

            if ($this->getService(RuntimeConfig::class)['use_alerte']) {
                $js = "// par défaut, pas de popup d'alerte pour quitter la page
                var showPopup = false;

                // on demande a faire apparaitre la popup si la page a été modifiée
                var bodyField = document.getElementById('body');
                if (bodyField) {
                    bodyField.addEventListener('input', function() {
                        showPopup = true;
                    });
                }

                // on annule la popup si l'on sauve la page
                ['ACEditor', 'formulaire'].forEach(function(id) {
                    var formEl = document.getElementById(id);
                    if (formEl) {
                        formEl.addEventListener('submit', function() {
                            showPopup = false;
                        });
                    }
                });

                // si l'on quitte la page, on affiche la popup si besoin
                window.addEventListener('beforeunload', function(e) {
                    if (showPopup) {
                        e.preventDefault();
                        e.returnValue = '';
                    }
                });";

                $this->getService(AssetsManager::class)->AddJavascript($js);
            }
        }

        // Si une valeur de body est passee en paramétre GET (et pas POST) on l'ajoute en titre dans la nouvelle page vierge
        if (isset($_GET['body']) && !isset($_POST['body'])) {
            $_POST['body'] = '======' . $_GET['body'] . '======';
        }

        $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/change-theme.js');
        $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/template-edit.js');

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

            $plugin_output_new = $this->wiki->Header() . $plugin_output_new;
            $plugin_output_new .= $this->wiki->Footer();

            // we use die so that the script stop there and the default handler of wiki isn't called
            $this->wiki->exit($plugin_output_new);
        }

        // get services
        $aclService = $this->getService(AclService::class);
        $tagsManager = $this->getService(TagsManager::class);

        if (
            !$this->params->get('hide_keywords')
            && $aclService->hasAccess('write')
        ) {
            // save new tag if authorized
            $post = $this->getRequest()->request;
            if (
                $post->get('submit') == InputFilter::EDIT_PAGE_SUBMIT_VALUE
                && $post->has('pagetags')
                && $post->get('antispam') == 1
            ) {
                $tagsManager->save($this->getService(PageContext::class)->getTag(), stripslashes($post->get('pagetags')));
            }

            // display: the live-search tag-input widget (javascripts/yw-tags-input.js)
            // queries GET /api/tags itself as the user types -- no tag list to dump here
            if ($aclService->hasAccess('read')) {
                $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/yw-tags-input.js');
            }
        }
    }

    /**
     * Ran as an after-callback until ticket 06 merged it in. Receives the rendered output
     * as $plugin_output_new -- the name the hooks already used -- because several rewrite
     * it rather than appending.
     */
    private function emitAfter(string $plugin_output_new): string
    {
        ob_start();

        // merged from handlers/page/edit__.php (ticket 06: core does not hook itself)
        $params = $this->wiki->services->get(ParameterBagInterface::class);
        if (!$params->get('hide_keywords') && $this->getService(AclService::class)->hasAccess('write') && $this->getService(AclService::class)->hasAccess('read')) {
            // on recupere les tags de la page courante
            $tagsManager = $this->wiki->services->get(TagsManager::class);
            $tabtagsexistants = $tagsManager->getAll($this->getService(PageContext::class)->getTag());
            $tagspage = array_unique(array_column($tabtagsexistants, 'value'));
            sort($tagspage);

            $chips = '';
            foreach ($tagspage as $tag) {
                $escapedTag = htmlspecialchars(stripslashes($tag), ENT_QUOTES);
                $chips .= '<span class="yw-tag-input__chip" data-yw-tag-input-chip data-tag="' . $escapedTag . '">'
                    . $escapedTag
                    . '<button type="button" class="yw-tag-input__chip-remove" data-yw-tag-input-remove aria-label="' . _t('TAGS_REMOVE_TAG') . '">&times;</button>'
                    . '</span>';
            }

            $searchUrl = $this->getService(UrlFormatter::class)->href('', 'api/tags');
            $html = '
        	<i class="fas fa-tags"></i> <strong>' . _t('TAGS_TAGS') . '</strong>
        	<div class="yw-tag-input" data-yw-tag-input>' . $chips . '
        		<input type="text" class="yw-input yw-tag-input__search" data-yw-tag-input-search
        		       name="search" autocomplete="off" placeholder="' . _t('TAGS_ADD_TAGS') . '"
        		       hx-get="' . $searchUrl . '" hx-trigger="keyup changed delay:300ms" hx-include="this" hx-vals=\'{"perpage":8}\' hx-swap="none">
        		<ul class="yw-suggestions" data-yw-tag-input-suggestions hidden></ul>
        		<input type="hidden" name="pagetags" data-yw-tag-input-value value="' . htmlspecialchars(implode(',', $tagspage), ENT_QUOTES) . '">
        	</div>
            <input type="hidden" class="antispam" name="antispam" value="0">';

            $target = '<div class="tags-container">';
            $plugin_output_new = str_replace($target, $target . $html, $plugin_output_new);
        }

        if ($this->getService(AclService::class)->hasAccess('write') && $this->getService(AclService::class)->hasAccess('read')) {
            // Edition
            if (!isset($_POST['submit']) || $_POST['submit'] != InputFilter::EDIT_PAGE_SUBMIT_VALUE) {
                if ($this->getService(RuntimeConfig::class)['use_hashcash']) {
                    $hashCash = $this->wiki->services->get(HashCashService::class);
                    $hashCashCode = $hashCash->getJavascriptCode();
                    $plugin_output_new = preg_replace(
                        '/\<hr class=\"hr_clear\" \/\>/',
                        $hashCashCode . '<hr class="hr_clear" />',
                        $plugin_output_new
                    );
                }
                $this->wiki->services->get(CaptchaController::class)->renderCaptcha($plugin_output_new);
            }
        }

        // remove the {{template ...}} action from the page body
        $plugin_output_new = preg_replace(
            '/(\\{\\{template)(.*?)(\\}\\})/is',
            '',
            $plugin_output_new
        );

        $themeManager = $this->wiki->services->get(ThemeManager::class);

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
            $selecteur .= $this->wiki->services->get(ThemeSelectorRenderer::class)->showFormThemeSelector('edit');
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
            $output = '';
            // on recupere les entetes html mais pas ce qu'il y a dans le body
            $header = explode('<body', $this->wiki->Header());
            $output .= $header[0] . '<body class="login-body">' . "\n"
                . '<div class="yeswiki-page-widget page-widget page">' . "\n";
            $output .= '<div class="yw-alert yw-alert--danger">'
                . _t('LOGIN_NOT_AUTORIZED_EDIT') . '. ' . _t('LOGIN_PLEASE_REGISTER') . '.'
                . '</div><!-- end .alert -->' . "\n"
                . $this->getService(MarkdownFormatterService::class)->format('{{login signupurl="0"}}' . "\n\n")
                . '</div><!-- end .page -->' . "\n";
            // on recupere juste les javascripts et la fin des balises body et html
            $output .= preg_replace('/^.+<script/Us', '<script', $this->wiki->Footer());
            $this->wiki->exit($output);
        }

        return $plugin_output_new . (string)ob_get_clean();
    }

    private function emit(): void
    {
        // on initialise la sortie:
        $output = '';

        $isWikiHibernated = $this->wiki->services->get(HibernationService::class)->isWikiHibernated();
        // bare-script handler: $this->wiki is the Wiki instance itself, which exposes the current
        // request as a public property, not via a getRequest() helper (that's only on
        // YesWikiPerformable-derived actions/handlers)
        $request = $this->wiki->request;

        if ($this->getService(AclService::class)->hasAccess('write') && $this->getService(AclService::class)->hasAccess('read') && !$isWikiHibernated) {
            $submit = $request->request->get('submit') ?: false;

            // fetch fields
            $previous = $request->request->get('previous') ?: (isset($this->getService(PageContext::class)->getPage()['id']) ? $this->getService(PageContext::class)->getPage()['id'] : null);
            $body = $request->request->get('body') ?: (isset($this->getService(PageContext::class)->getPage()['body']) ? $this->getService(PageContext::class)->getPage()['body'] : null);

            $cancelUrl = addslashes($this->getService(UrlFormatter::class)->href(testUrlInIframe()));

            // PREVIEW
            if ($submit == 'preview') {
                $temp = $this->getService(InclusionStack::class)->replace(); // a priori, ça ne sert à rien, mais on ne sait jamais...
                $this->getService(InclusionStack::class)->register($this->getService(PageContext::class)->getTag()); // on simule totalement un affichage normal
                $output .= $this->wiki->render('@core/handlers/edit.twig', [
                    'previous' => $previous,
                    'handler' => testUrlInIframe() ? 'editiframe' : 'edit',
                    'cancelUrl' => $cancelUrl,
                    'body' => empty($body) ? '' : htmlspecialchars($body, ENT_COMPAT, YW_CHARSET),
                    'preview' => true,
                    'bodyPreview' => $this->getService(MarkdownFormatterService::class)->format($body),
                    'saveValue' => InputFilter::EDIT_PAGE_SUBMIT_VALUE,
                ]);
                $this->getService(InclusionStack::class)->replace($temp);
            } else {
                if ($submit == InputFilter::EDIT_PAGE_SUBMIT_VALUE && $this->getService(PageContext::class)->getPage() && $this->getService(PageContext::class)->getPage()['id'] != $request->request->get('previous')) {
                    $error = _t('EDIT_ALERT_ALREADY_SAVED_BY_ANOTHER_USER');
                    $submit = false;
                }

                if ($submit == InputFilter::EDIT_PAGE_SUBMIT_VALUE) {
                    // SAVE AND REDIRECT
                    $body = str_replace("\r", '', $body);
                    // teste si la nouvelle page est differente de la précédente
                    if (isset($this->getService(PageContext::class)->getPage()['body']) && rtrim($body) == rtrim($this->getService(PageContext::class)->getPage()['body'])) {
                        $this->getService(FlashMessageService::class)->setMessage(_t('EDIT_NO_CHANGE_MSG'));
                        $this->wiki->Redirect($this->getService(UrlFormatter::class)->href(testUrlInIframe()));
                    } else {
                        // l'encodage de la base est en iso-8859-1, voir s'il faut convertir
                        $body = $body;

                        // add page (revisions)
                        $this->getService(PageManager::class)->save($this->getService(PageContext::class)->getTag(), $body, !empty($this->getService(PageContext::class)->getPage()['comment_on']) ? $this->getService(PageContext::class)->getPage()['comment_on'] : '');

                        // now we render it internally so we can write the updated link table.
                        $page = $this->wiki->services->get(PageManager::class)->getOne($this->getService(PageContext::class)->getTag());
                        $this->wiki->services->get(LinkTracker::class)->registerLinks($page, false, false);

                        // forward
                        if (($this->getService(PageContext::class)->getPage() ?? [])['comment_on']) {
                            $this->wiki->Redirect($this->getService(UrlFormatter::class)->href(testUrlInIframe(), ($this->getService(PageContext::class)->getPage() ?? [])['comment_on']) . '#' . $this->getService(PageContext::class)->getTag());
                        } else {
                            $this->wiki->Redirect($this->getService(UrlFormatter::class)->href(testUrlInIframe()));
                        }
                    }
                    $this->wiki->exit(); // we shall have been redirected, but exit for safety
                } else {
                    // RENDER FORM

                    // append a comment?
                    if ($request->query->has('appendcomment') || $request->request->has('appendcomment')) {
                        $body = trim($body) . "\n\n----\n\n-- " . $this->getService(AuthenticationService::class)->getLoggedUserName() . ' (' . date('c') . ')';
                    }

                    $passwordForEditing = !empty($this->getService(RuntimeConfig::class)['password_for_editing']) && $request->request->has('password_for_editing');

                    $output .= $this->wiki->render('@core/handlers/edit.twig', [
                        'error' => $error ?? null,
                        'previous' => $previous,
                        'handler' => testUrlInIframe() ? 'editiframe' : 'edit',
                        'passwordForEditing' => $passwordForEditing,
                        'cancelUrl' => $cancelUrl,
                        'body' => empty($body) ? '' : htmlspecialchars($body, ENT_COMPAT, YW_CHARSET),
                        'saveValue' => InputFilter::EDIT_PAGE_SUBMIT_VALUE,
                        'preview' => false,
                    ]);
                }
            }
        } else {
            $output .= '<i>' . _t('EDIT_NO_WRITE_ACCESS') . "</i>\n";
            if ($isWikiHibernated) {
                $output .= $this->wiki->services->get(HibernationNotice::class)->getMessageWhenHibernated();
            }
        }

        // Main Page
        $output = '<div class="page">' . "\n" . $output . "\n" . '<hr class="hr_clear" />' . "\n" . '</div>' . "\n";

        // Header - // Footer
        if (!testUrlInIframe()) {
            echo $this->wiki->Header() . $output . $this->wiki->Footer();
        } else {
            echo $output;
        }
    }
}
