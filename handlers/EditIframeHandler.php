<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\YesWikiHandler;

class EditIframeHandler extends YesWikiHandler
{
    public function run()
    {
        // the edit handler use this variable to display the content without the header and footer
        $GLOBALS['inIframe'] = true;

        // on recupere les entetes html mais pas ce qu'il y a dans le body
        $header = explode('<body', $this->wiki->Header());
        $output = $header[0];

        if ($this->wiki->HasAccess('write')) {
            $entryManager = $this->getService(EntryManager::class);
            $entryController = $this->getService(EntryController::class);

            if ($entryManager->isEntry($this->wiki->GetPageTag())) {
                $buffer = $entryController->update($this->wiki->GetPageTag());
            } else {
                ob_start();
                echo $this->wiki->Run($this->wiki->getPageTag(), 'edit');
                $buffer = ob_get_contents();
                ob_end_clean();
            }

            $this->wiki->AddJavascriptFile('tools/bazar/libs/bazar.js');
            $output .= '<body class="yeswiki-iframe-body">' . "\n"
                . '<div class="container">' . "\n"
                . '<div class="yeswiki-page-widget page-widget page">' . "\n";

            $output .= replaceLinksWithIframe($buffer);
        } else {
            // if no write access to the page

            // on recupere les entetes html mais pas ce qu'il y a dans le body
            $header = explode('<body', $this->wiki->Header());
            $output .= $header[0] . '<body class="yeswiki-iframe-body login-body">' . "\n"
                . '<div class="container">' . "\n"
                . '<div class="yeswiki-page-widget page-widget page" ' . $this->wiki->Format('{{doubleclic iframe="1"}}')
                . '>' . "\n";

            if ($contenu = $this->wiki->LoadPage("PageLogin")) {
                // si une page PageLogin existe, on l'affiche
                $output .= $this->wiki->Format($contenu["body"]);
            } else {
                // sinon on affiche le formulaire d'identification minimal
                $output .= '<div class="vertical-center white-bg">' . "\n"
                    . '<div class="alert alert-danger alert-error">' . "\n"
                    . _t('LOGIN_NOT_AUTORIZED') . '. ' . _t('LOGIN_PLEASE_REGISTER') . '.' . "\n"
                    . '</div>' . "\n"
                    . $this->wiki->Format('{{login signupurl="0"}}' . "\n\n")
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
        $this->wiki->AddJavascriptFile('tools/templates/libs/vendor/iframeResizer.contentWindow.min.js');
        // on recupere juste les javascripts et la fin des balises body et html
        $output .= preg_replace('/^.+<script/Us', '<script', $this->wiki->Footer());

        unset($GLOBALS['inIframe']);

        return $output;
    }
}
