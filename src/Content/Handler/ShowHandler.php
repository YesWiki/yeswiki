<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Service\CommentService;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\SemanticTransformer;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\InclusionStack;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\LinkRenderer;
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
        $entryManager = $this->wiki->services->get(EntryManager::class);

        if ($entryManager->isEntry($this->wiki->GetPageTag()) && $this->wiki->HasAccess('read')) {
            if (isset($_SERVER['HTTP_ACCEPT']) && (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false || strpos($_SERVER['HTTP_ACCEPT'], 'application/ld+json') !== false)) {
                $semantic = strpos($_SERVER['HTTP_ACCEPT'], 'application/ld+json') !== false;
                $contentType = $semantic ? 'application/ld+json' : 'application/json';

                header("Content-type: $contentType; charset=UTF-8");
                header('Access-Control-Allow-Origin: *');

                $fiche = $entryManager->getOne($this->wiki->GetPageTag());

                if ($semantic) {
                    $form = $this->wiki->services->get(FormManager::class)->getOne($fiche['form_id']);
                    $semanticFiche = $this->wiki->services->get(SemanticTransformer::class)->convertToSemanticData($form, $fiche);
                    $this->wiki->exit(json_encode($semanticFiche));
                } else {
                    $this->wiki->exit(json_encode($fiche));
                }
            } else {
                $this->wiki->AddJavascriptFile('javascripts/bazar.js', true, true);
            }
        }

        // Verification de securite
        $this->wiki->addJavascriptFile('javascripts/tag.js');

        // Page translation (formerly tools/lang's __show before-callback): keep only the
        // {{lang="xx"}} section matching the visitor's language, if the page uses markers
        require_once YESWIKI_SOURCE_DIR . '/src/lang.functions.php';
        if (!empty($this->wiki->page['body'])) {
            $this->wiki->page['body'] = filterBodyByLanguage(
                $this->wiki->page['body'],
                $GLOBALS['prefered_language'],
                $this->wiki->config['default_language']
            );
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
        $entryManager = $this->wiki->services->get(EntryManager::class);

        if ($entryManager->isEntry($this->wiki->GetPageTag())) {
            $this->wiki->AddJavascriptFile('javascripts/bazar.js', true, true);

            $fiche = $entryManager->getOne($this->wiki->GetPageTag());

            $replace = '<input type="hidden" name="body" value="' . htmlspecialchars(json_encode($fiche), ENT_COMPAT, YW_CHARSET) . '" />';
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

        if (!$this->wiki->HasAccess('read')) {
            if ($contenu = $this->wiki->LoadPage('PageLogin')) {
                $output = '<body class="login-body">' . "\n"
                    . '<div class="container">' . "\n"
                    . '<div class="yeswiki-page-widget page-widget page" ' . $this->wiki->Format('{{doubleclic iframe="1"}}') . '>' . "\n";
                $output .= '<div class="yw-alert yw-alert--danger">' .
                    _t('LOGIN_NOT_AUTORIZED') . ', ' . _t('LOGIN_PLEASE_REGISTER') . '.' .
                    '</div>' . "\n";
                $output .= $this->wiki->Format('{{include page="PageLogin"}}');
                $output .= '</div><!-- end .page-widget -->' . "\n";
                $output .= '</div><!-- end .container -->' . "\n";
                $output = $this->wiki->Header() . $output;
                $output .= $this->wiki->Footer();
            } else {
                // sinon on affiche le formulaire d'identification minimal
                $output = str_replace(
                    '<i>' . _t('LOGIN_NOT_AUTORIZED') . '</i>', // to sync with /handlers/page/show.php
                    '<div class="alert alert-danger alert-error">' .
                        _t('LOGIN_NOT_AUTORIZED') . ', ' . _t('LOGIN_PLEASE_REGISTER') . '.' .
                        '</div>' . "\n" .
                        $this->wiki->Format('{{login context="login-page" signupurl="0"}}'),
                    $plugin_output_new
                );
            }
            $this->wiki->exit($output);
        }

        // merged from handlers/ShowHandler__.php (ticket 06: core does not hook itself)
        // get services
        $aclService = $this->getService(AclService::class);
        $entryManager = $this->getService(EntryManager::class);
        $tagsManager = $this->getService(TagsManager::class);

        // display tags if needed
        $tag = $this->wiki->getPageTag();
        if (!$this->params->get('hide_keywords') && (bool)$this->wiki->page && !empty($tag) && $aclService->hasAccess('read', $tag) && !$entryManager->isEntry($tag)) {
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
        echo (($user = $this->wiki->GetUser()) && ($user['doubleclickedit'] == 'N') || !$this->wiki->HasAccess('write')) ? '' : ' ondblclick="doubleClickEdit(event);"';
        echo '>' . "\n";
        if (!empty($_SESSION['redirects'])) {
            $trace = $_SESSION['redirects'];
            $tag = $trace[count($trace) - 1];
            $prevpage = $this->wiki->LoadPage($tag);
            echo '<div class="redirectfrom"><em>(' . str_replace('{linkFrom}', $this->getService(LinkRenderer::class)->link($prevpage['tag'], 'edit'), _t('REDIRECTED_FROM')) . ")</em></div>\n";
            unset($_SESSION['redirects'][count($trace) - 1]);
        }

        if ($HasAccessRead = $this->wiki->HasAccess('read')) {
            if (!$this->wiki->page) {
                echo str_replace(
                    ['{beginLink}', '{endLink}'],
                    ["<a href=\"{$this->getService(UrlFormatter::class)->href('edit')}\">", '</a>'],
                    _t('NOT_FOUND_PAGE')
                );
            } else {
                // comment header?
                if ($this->wiki->page['comment_on']) {
                    echo '<div class="commentinfo">' . str_replace(
                        ['{tag}', '{user}', '{time}'],
                        [$this->getService(LinkRenderer::class)->linkToPage($this->wiki->page['comment_on'], '', '', 0), $this->wiki->Format($this->wiki->page['user']), $this->wiki->page['time']],
                        _t('COMMENT_INFO')
                    ) . '</div>';
                }

                if ($this->wiki->page['latest'] == 'N') {
                    echo '<div class="alert alert-info">' . "\n";
                    echo str_replace(['{link}', '{time}'], ["<a href=\"{$this->getService(UrlFormatter::class)->href()}\">{$this->wiki->GetPageTag()}</a>", $this->wiki->page['time']], _t('REVISION_IS_ARCHIVE_OF_TAG_ON_TIME'));
                    // if this is an old revision, display some buttons
                    if ($this->wiki->HasAccess('write')) {
                        $latest = $this->wiki->LoadPage($this->wiki->tag); ?>
                        <?php
                        $time = isset($_GET['time']) ? $_GET['time'] : '';
                        echo $this->wiki->FormOpen(testUrlInIframe() ? 'editiframe' : 'edit', '', 'get'); ?>
                        <input type="hidden" name="time" value="<?php echo htmlspecialchars($time, ENT_QUOTES, YW_CHARSET); ?>" />
                        <input class="btn btn-primary" type="submit" value="<?php echo _t('EDIT_ARCHIVED_REVISION'); ?>" />
                        <?php echo $this->wiki->FormClose(); ?>
        <?php
                    }

                    echo '</div>' . "\n";
                }

                // display page
                $this->getService(InclusionStack::class)->register($this->wiki->GetPageTag());
                $entryManager = $this->wiki->services->get(EntryManager::class);
                if ($entryManager->isEntry($this->wiki->page['tag'])) {
                    $entryController = $this->wiki->services->get(EntryController::class);
                    echo $entryController->view($this->wiki->GetPageTag(), $this->wiki->page['time'] ?? null);
                } else {
                    echo $this->wiki->Format($this->wiki->page['body'], 'wakka', $this->wiki->GetPageTag());
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
        echo $this->wiki->services->get(CommentService::class)->renderCommentsForPage($this->wiki->getPageTag());

        // get the content buffer and display the page
        $content = ob_get_clean();
        echo $this->wiki->Header();
        echo $content;
        echo $this->wiki->Footer();
    }
}
