<?php

namespace YesWiki\Render\Handler;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\PasswordForEditingService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\AssetsManager;
use YesWiki\Kernel\Service\Performer;
use YesWiki\Render\Service\MarkdownFormatterService;

class EditIframeHandler extends YesWikiHandler implements RegisteredHandler
{
    /** `/PageName/editiframe` -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'editiframe';
    }

    public function run()
    {
        $output = '';

        if ($this->getService(AclService::class)->hasAccess('write') && $this->getService(AclService::class)->hasAccess('read')) {
            $passwordForEditingService = $this->getService(PasswordForEditingService::class);
            if ($this->isWikiHibernated()) {
                $buffer = $this->getMessageWhenHibernated();
            } else {
                list($state, $message) = $passwordForEditingService->isGrantedPasswordForEditing();
                if (!$state) {
                    $buffer = $message;
                } else {
                    $entryManager = $this->getService(EntryManager::class);
                    $entryController = $this->getService(EntryController::class);

                    if ($entryManager->isEntry($this->wiki->GetPageTag())) {
                        $buffer = $entryController->update($this->wiki->GetPageTag());
                    } else {
                        ob_start();
                        $buffer = $this->getService(Performer::class)->run('edit', 'handler', []);
                        $buffer = ob_get_contents() . $buffer;
                        ob_end_clean();
                    }
                }
            }

            $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/bazar.js', true, true);
            $output .= '<body class="yeswiki-iframe-body">' . "\n"
                . '<div class="container">' . "\n"
                . '<div class="yeswiki-page-widget page-widget page">' . "\n";

            $output .= replaceLinksWithIframe($buffer);
        } else {
            // if no write access to the page

            $output = '<body class="yeswiki-iframe-body login-body">' . "\n"
                . '<div class="container">' . "\n"
                . '<div class="yeswiki-page-widget page-widget page" ' . $this->getService(MarkdownFormatterService::class)->format('{{doubleclic iframe="1"}}')
                . '>' . "\n";

            if ($contenu = $this->getService(PageManager::class)->getOne('PageLogin')) {
                // si une page PageLogin existe, on l'affiche
                $output .= replaceLinksWithIframe($this->getService(MarkdownFormatterService::class)->format($contenu['body']));
            } else {
                // sinon on affiche le formulaire d'identification minimal
                $output .= '<div class="vertical-center white-bg">' . "\n"
                    . '<div class="alert alert-danger alert-error">' . "\n"
                    . _t('LOGIN_NOT_AUTORIZED') . '. ' . _t('LOGIN_PLEASE_REGISTER') . '.' . "\n"
                    . '</div>' . "\n"
                    . replaceLinksWithIframe($this->getService(MarkdownFormatterService::class)->format('{{login signupurl="0"}}' . "\n\n"))
                    . '</div><!-- end .vertical-center -->' . "\n";
            }
        }

        // common footer for all iframe page
        $output .= '</div><!-- end .page-widget -->' . "\n";

        // on affiche la barre de modification, si on ajoute &edit=1 à l'url de l'iframe
        if (isset($_GET['edit']) && $_GET['edit'] == '1') {
            $output .= $this->getService(MarkdownFormatterService::class)->format('{{barreredaction}}');
        }
        $output .= '</div><!-- end .container -->' . "\n";
        $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/vendor/iframe-resizer/iframeResizer.contentWindow.min.js');

        // on recupere les entetes html mais pas ce qu'il y a dans le body
        $header = explode('<body', $this->wiki->Header());
        $output = $header[0] . $output;
        // on recupere juste les javascripts et la fin des balises body et html
        $output .= preg_replace('/^.+<script/Us', '<script', $this->wiki->Footer());

        return $output;
    }
}
