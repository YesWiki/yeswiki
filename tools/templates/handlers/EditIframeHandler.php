<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\YesWikiHandler;

class EditIframeHandler extends YesWikiHandler
{
    public function run()
    {
        $entryManager = $this->getService(EntryManager::class);
        $entryController = $this->getService(EntryController::class);

        $GLOBALS['inIframe'] = true;

        if ($this->wiki->HasAccess('write')) {
            if ($entryManager->isEntry($this->wiki->GetPageTag())) {
                $buffer = $entryController->update($this->wiki->GetPageTag());
            } else {
                ob_start();
                echo $this->wiki->Run($this->wiki->getPageTag(), 'edit');
                $buffer = ob_get_contents();
                ob_end_clean();
            }

            $output = '';
            // on recupere les entetes html mais pas ce qu'il y a dans le body
            $header = explode('<body', $this->wiki->Header());
            $output .= $header[0] . '<body class="iframe-body">' . "\n"
                . '<div class="yeswiki-page-widget page">' . "\n";

            // on replace la méthode d'edition classique pour mettre celle de l'édition en iframe
            $buffer = str_replace(
                'action="' . $this->wiki->href('edit', $this->wiki->getPageTag()) . '"',
                'action="' . $this->wiki->href('editiframe', $this->wiki->getPageTag()) . '"',
                $buffer
            );
            $buffer = str_replace(
                'onclick="location.href=\'' . $this->wiki->href('', $this->wiki->getPageTag()),
                'onclick="location.href=\'' . $this->wiki->href('iframe', $this->wiki->getPageTag()),
                $buffer
            );
            $output .= str_replace(
                'value="' . $this->wiki->getPageTag() . '/edit"',
                'value="' . $this->wiki->getPageTag() . '/editiframe"',
                $buffer
            );
            $output .= '</div><!-- end div.page-widget -->' . "\n";
        } else {
            $output = '';
            // on recupere les entetes html mais pas ce qu'il y a dans le body
            $header = explode('<body', $this->wiki->Header());
            $output .= $header[0] . '<body class="login-body">' . "\n"
                . '<div class="yeswiki-page-widget page-widget page">' . "\n"
                . '<div class="alert alert-danger alert-error">'
                . _t('LOGIN_NOT_AUTORIZED_EDIT') . '. ' . _t('LOGIN_PLEASE_REGISTER') . '.'
                . '</div><!-- end .alert -->' . "\n"
                . $this->wiki->Format('{{login signupurl="0"}}' . "\n\n")
                . '</div><!-- end .page -->' . "\n";
        }

        $this->wiki->addJavascriptFile('tools/bazar/libs/bazar.js');
        $this->wiki->addJavascriptFile('tools/templates/libs/vendor/iframeResizer.contentWindow.min.js');
        // on recupere juste les javascripts et la fin des balises body et html
        $output .= preg_replace('/^.+<script/Us', '<script', $this->wiki->Footer());
        echo $output;

        unset($GLOBALS['inIframe']);
    }
}
