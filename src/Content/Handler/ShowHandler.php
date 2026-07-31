<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\CommentService;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\SemanticTransformer;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\AssetsManager;
use YesWiki\Kernel\Service\InclusionStack;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\LinkRenderer;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Search\Service\TagsManager;

/**
 * `/PageName/show` -- converted from the procedural handlers/page/show.php by ticket 06.
 */
class ShowHandler extends YesWikiHandler implements RegisteredHandler
{
    public static function performableName(): string
    {
        return 'show';
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
        // merged from handlers/page/__show.php (ticket 06: core does not hook itself)
        // relocated from tools/bazar/handlers/page/__show.php (ticket 24): if the page is a bazar
        // entry and was requested with an Accept header asking for JSON/JSON-LD, respond with the
        // entry's data directly instead of rendering the page as HTML.
        $entryManager = $this->getService(EntryManager::class);

        if ($entryManager->isEntry($this->getService(PageContext::class)->getTag()) && $this->getService(AclService::class)->hasAccess('read')) {
            if (isset($_SERVER['HTTP_ACCEPT']) && (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false || strpos($_SERVER['HTTP_ACCEPT'], 'application/ld+json') !== false)) {
                $semantic = strpos($_SERVER['HTTP_ACCEPT'], 'application/ld+json') !== false;
                $contentType = $semantic ? 'application/ld+json' : 'application/json';

                header("Content-type: $contentType; charset=UTF-8");
                header('Access-Control-Allow-Origin: *');

                $entry = $entryManager->getOne($this->getService(PageContext::class)->getTag());

                if ($semantic) {
                    $form = $this->getService(FormManager::class)->getOne($entry['form_id'] ?? null);
                    $semanticFiche = $this->getService(SemanticTransformer::class)->convertToSemanticData($form, $entry);
                    $this->getService(Redirector::class)->terminate((string)json_encode($semanticFiche));
                } else {
                    $this->getService(Redirector::class)->terminate((string)json_encode($entry));
                }
            } else {
                $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/bazar.js', true, true);
            }
        }

        // Verification de securite
        $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/tag.js');

        // Page translation (formerly tools/lang's __show before-callback): keep only the
        // {{lang="xx"}} section matching the visitor's language, if the page uses markers
        require_once YESWIKI_SOURCE_DIR . '/src/Kernel/lang.functions.php';
        $pageContext = $this->getService(PageContext::class);
        $body = ($pageContext->getPage() ?? [])['body'] ?? [];
        if (!empty(PageBody::content($body))) {
            $body[PageBody::CONTENT] = filterBodyByLanguage(
                PageBody::content($body),
                $GLOBALS['prefered_language'],
                $this->getService(RuntimeConfig::class)['default_language']
            );
            $pageContext->setPageField('body', $body);
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

        // merged from handlers/page/show__.php (ticket 06: core does not hook itself)
        // relocated from tools/bazar/handlers/page/show__.php (ticket 24): if the page is a bazar
        // entry, replace the hidden aceditor "body" field with the entry's own JSON data so edits
        // go through the entry form instead of the raw wikitext editor.
        $entryManager = $this->getService(EntryManager::class);

        if ($entryManager->isEntry($this->getService(PageContext::class)->getTag())) {
            $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/bazar.js', true, true);

            $entry = $entryManager->getOne($this->getService(PageContext::class)->getTag());

            $replace = '<input type="hidden" name="body" value="' . htmlspecialchars(json_encode($entry), ENT_COMPAT, YW_CHARSET) . '" />';
            if (isset($_GET['time'])) {
                $replace = '<input type="hidden" name="time" value="' . htmlspecialchars($_GET['time'], ENT_COMPAT, YW_CHARSET) . '">' . "\n" . $replace;
            }
            $plugin_output_new = preg_replace(
                '/\<input type=\"hidden\" name=\"body\" value=\".*\" \/\>/Uis',
                $replace,
                $plugin_output_new
            );
        }

        // on efface des événements javascript issus de wikini
        $plugin_output_new = str_replace('ondblclick="doubleClickEdit(event);"', '', $plugin_output_new);

        // on efface aussi le message sur la non-modification d'une page, car contradictoire avec le changement de theme, et inéfficace pour l'expérience utilisateur
        // TODO check if the following line is really usefull
        $plugin_output_new = str_replace('onload="alert(\'' . _t('EDIT_NO_CHANGE_MSG') . '\');"', '', $plugin_output_new);

        if (isset($GLOBALS['template-error']) && $GLOBALS['template-error']['type'] == 'theme-not-found') {
            // on affiche le message d'erreur des templates inexistants
            $plugin_output_new = str_replace(
                '<div class="page" >',
                '<div class="page">' . "\n" . '<div class="yw-alert yw-alert--danger"><a href="#" data-yw-dismiss="alert" class="yw-close">&times;</a><strong>' . _t('TEMPLATE_NO_THEME_FILES') . ' :</strong><br />themes/' . $GLOBALS['template-error']['theme'] . '/squelettes/' . $GLOBALS['template-error']['squelette'] . '<br />themes/' . $GLOBALS['template-error']['theme'] . '/styles/' . $GLOBALS['template-error']['style'] . '<br><strong>' . _t('TEMPLATE_DEFAULT_THEME_USED') . '</strong>.</div>',
                $plugin_output_new
            );
            $GLOBALS['template-error'] = '';
        }

        if (!$this->getService(AclService::class)->hasAccess('read')) {
            if ($contenu = $this->getService(PageManager::class)->getOne('PageLogin')) {
                $output = '<body class="login-body">' . "\n"
                    . '<div class="container">' . "\n"
                    . '<div class="yeswiki-page-widget page-widget page" ' . $this->getService(MarkdownFormatterService::class)->format('{{doubleclic iframe="1"}}') . '>' . "\n";
                $output .= '<div class="yw-alert yw-alert--danger">' .
                    _t('LOGIN_NOT_AUTORIZED') . ', ' . _t('LOGIN_PLEASE_REGISTER') . '.' .
                    '</div>' . "\n";
                $output .= $this->getService(MarkdownFormatterService::class)->format('{{include page="PageLogin"}}');
                $output .= '</div><!-- end .page-widget -->' . "\n";
                $output .= '</div><!-- end .container -->' . "\n";
                $output = $this->getService(TemplateEngine::class)->header() . $output;
                $output .= $this->getService(TemplateEngine::class)->footer();
            } else {
                // sinon on affiche le formulaire d'identification minimal
                $output = str_replace(
                    '<i>' . _t('LOGIN_NOT_AUTORIZED') . '</i>', // to sync with /handlers/page/show.php
                    '<div class="alert alert-danger alert-error">' .
                        _t('LOGIN_NOT_AUTORIZED') . ', ' . _t('LOGIN_PLEASE_REGISTER') . '.' .
                        '</div>' . "\n" .
                        $this->getService(MarkdownFormatterService::class)->format('{{login context="login-page" signupurl="0"}}'),
                    $plugin_output_new
                );
            }
            $this->getService(Redirector::class)->terminate($output);
        }

        // merged from handlers/ShowHandler__.php (ticket 06: core does not hook itself)
        // get services
        $aclService = $this->getService(AclService::class);
        $entryManager = $this->getService(EntryManager::class);
        $tagsManager = $this->getService(TagsManager::class);

        // display tags if needed
        $tag = $this->getService(PageContext::class)->getTag();
        if (!$this->params->get('hide_keywords') && (bool)$this->getService(PageContext::class)->getPage() && !empty($tag) && $aclService->hasAccess('read', $tag) && !$entryManager->isEntry($tag)) {
            $tags = array_column($tagsManager->getAll($tag), 'value');
            if (!empty($tags)) {
                $output = $this->render('@core/tags-at-page-bottom.twig', [
                    'pageTag' => $tag,
                    'tags' => $tags,
                ]);
                $replaced = preg_replace('/\<hr class=\"hr_clear\" \/\>/', "$output\n<hr class=\"hr_clear\" />", $plugin_output_new);
                if (!empty($replaced)) {
                    $plugin_output_new = $replaced;
                }
            }
        }

        return $plugin_output_new . (string)ob_get_clean();
    }

    private function emit(): void
    {
        // V?rification de s?curit?

        // Generate page before displaying the header, so that it might interract with the header
        ob_start();

        echo '<div class="page"';
        echo (($user = $this->getService(AuthenticationService::class)->getLoggedUser()) && ($user['doubleclickedit'] == 'N') || !$this->getService(AclService::class)->hasAccess('write')) ? '' : ' ondblclick="doubleClickEdit(event);"';
        echo '>' . "\n";
        if (!empty($_SESSION['redirects'])) {
            $trace = $_SESSION['redirects'];
            $tag = $trace[count($trace) - 1];
            $prevpage = $this->getService(PageManager::class)->getOne($tag);
            echo '<div class="redirectfrom"><em>(' . str_replace('{linkFrom}', $this->getService(LinkRenderer::class)->link($prevpage['tag'] ?? '', 'edit'), _t('REDIRECTED_FROM')) . ")</em></div>\n";
            unset($_SESSION['redirects'][count($trace) - 1]);
        }

        if ($HasAccessRead = $this->getService(AclService::class)->hasAccess('read')) {
            if (!$this->getService(PageContext::class)->getPage()) {
                echo str_replace(
                    ['{beginLink}', '{endLink}'],
                    ["<a href=\"{$this->getService(UrlFormatter::class)->href('edit')}\">", '</a>'],
                    _t('NOT_FOUND_PAGE')
                );
            } else {
                // comment header?
                if ($this->getService(PageContext::class)->getPage()['comment_on']) {
                    echo '<div class="commentinfo">' . str_replace(
                        ['{tag}', '{user}', '{time}'],
                        [$this->getService(LinkRenderer::class)->linkToPage($this->getService(PageContext::class)->getPage()['comment_on'], '', '', 0), $this->getService(MarkdownFormatterService::class)->format($this->getService(PageContext::class)->getPage()['user']), $this->getService(PageContext::class)->getPage()['time']],
                        _t('COMMENT_INFO')
                    ) . '</div>';
                }

                if ($this->getService(PageContext::class)->getPage()['latest'] == 'N') {
                    echo '<div class="alert alert-info">' . "\n";
                    echo str_replace(['{link}', '{time}'], ["<a href=\"{$this->getService(UrlFormatter::class)->href()}\">{$this->getService(PageContext::class)->getTag()}</a>", $this->getService(PageContext::class)->getPage()['time']], _t('REVISION_IS_ARCHIVE_OF_TAG_ON_TIME'));
                    // if this is an old revision, display some buttons
                    if ($this->getService(AclService::class)->hasAccess('write')) {
                        $latest = $this->getService(PageManager::class)->getOne($this->getService(PageContext::class)->getTag()); ?>
                        <?php
                        $time = isset($_GET['time']) ? $_GET['time'] : '';
                        echo $this->getService(TemplateEngine::class)->formOpen(testUrlInIframe() ? 'editiframe' : 'edit', '', 'get'); ?>
                        <input type="hidden" name="time" value="<?php echo htmlspecialchars($time, ENT_QUOTES, YW_CHARSET); ?>" />
                        <input class="btn btn-primary" type="submit" value="<?php echo _t('EDIT_ARCHIVED_REVISION'); ?>" />
                        <?php echo $this->getService(TemplateEngine::class)->formClose(); ?>
        <?php
                    }

                    echo '</div>' . "\n";
                }

                // display page
                $this->getService(InclusionStack::class)->register($this->getService(PageContext::class)->getTag());
                $entryManager = $this->getService(EntryManager::class);
                if ($entryManager->isEntry($this->getService(PageContext::class)->getPage()['tag'])) {
                    $entryController = $this->getService(EntryController::class);
                    echo $entryController->view($this->getService(PageContext::class)->getTag(), $this->getService(PageContext::class)->getPage()['time'] ?? null);
                } else {
                    echo $this->getService(MarkdownFormatterService::class)->format(PageBody::content($this->getService(PageContext::class)->getPage()['body']));
                }
                $this->getService(InclusionStack::class)->unregisterLast();
            }
        } else {
            echo '<i>' . _t('LOGIN_NOT_AUTORIZED') . '</i>'; // to sync with /handlers/page/show__.php
        }
        ?>
        <hr class="hr_clear" />
        </div>


        <?php
        // render the comments if needed
        echo $this->getService(CommentService::class)->renderCommentsForPage($this->getService(PageContext::class)->getTag());

        // get the content buffer and display the page
        $content = ob_get_clean();
        echo $this->getService(TemplateEngine::class)->header();
        echo $content;
        echo $this->getService(TemplateEngine::class)->footer();
    }
}
