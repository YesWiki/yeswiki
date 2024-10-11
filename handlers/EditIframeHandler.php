<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\Performer;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Security\Controller\SecurityController;

class EditIframeHandler extends YesWikiHandler
{
    public function run()
    {
        $output = '';

        if ($this->wiki->HasAccess('write') && $this->wiki->HasAccess('read')) {
            $securityController = $this->getService(SecurityController::class);
            if ($this->isWikiHibernated()) {
                $buffer = $this->getMessageWhenHibernated();
            } else {
                list($state, $message) = $securityController->isGrantedPasswordForEditing();
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

            $this->wiki->AddJavascriptFile('tools/bazar/presentation/javascripts/bazar.js');
            $output .= '<body class="yeswiki-iframe-body">' . "\n"
                . '<div class="container">' . "\n"
                . '<div class="yeswiki-page-widget page-widget page">' . "\n";

            $output .= replaceLinksWithIframe($buffer);
        } else {
            // if no write access to the page

            $output = '<body class="yeswiki-iframe-body login-body">' . "\n"
                . '<div class="container">' . "\n"
                . '<div class="yeswiki-page-widget page-widget page" ' . $this->wiki->Format('{{doubleclic iframe="1"}}')
                . '>' . "\n";

            if ($contenu = $this->wiki->LoadPage('PageLogin')) {
                // si une page PageLogin existe, on l'affiche
                $output .= replaceLinksWithIframe($this->wiki->Format($contenu['body']));
            } else {
                // sinon on affiche le formulaire d'identification minimal
                $output .= '<div class="vertical-center white-bg">' . "\n"
                    . '<div class="alert alert-danger alert-error">' . "\n"
                    . _t('LOGIN_NOT_AUTORIZED') . '. ' . _t('LOGIN_PLEASE_REGISTER') . '.' . "\n"
                    . '</div>' . "\n"
                    . replaceLinksWithIframe($this->wiki->Format('{{login signupurl="0"}}' . "\n\n"))
                    . '</div><!-- end .vertical-center -->' . "\n";
            }
        }

        // common footer for all iframe page
        $output .= '</div><!-- end .page-widget -->' . "\n";

        // on affiche la barre de modification, si on ajoute &edit=1 à l'url de l'iframe
        if (isset($_GET['edit']) && $_GET['edit'] == '1') {
            $output .= $this->wiki->Format('{{barreredaction}}');
        }
        $output .= '</div><!-- end .container -->' . "\n";
        $this->wiki->AddJavascriptFile('javascripts/vendor/iframe-resizer/iframeResizer.contentWindow.min.js');

        // on recupere les entetes html mais pas ce qu'il y a dans le body
        $header = explode('<body', $this->wiki->Header());
        $output = $header[0] . $output;
        // on recupere juste les javascripts et la fin des balises body et html
        $output .= preg_replace('/^.+<script/Us', '<script', $this->wiki->Footer());

        return $output;
    }
}
