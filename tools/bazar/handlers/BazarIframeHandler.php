<?php

use YesWiki\Core\YesWikiHandler;

class BazarIframeHandler extends YesWikiHandler
{
    public function run()
    {
        $output = '';

        if ($this->wiki->HasAccess('read')) {
            if (empty($_GET['id'])) {
                $output .= '<div class="alert alert-danger">' . _t('BAZ_PAS_D_ID_DE_FORM_INDIQUE') . '</div>';
            } else {
                // affichage à l'écran de la liste bazar
                $this->arguments['shownumentries'] = false;
                $bazaroutput = $this->callAction('bazarliste', $this->arguments);

                if (isset($_GET['iframelinks']) && $_GET['iframelinks'] === '0') {
                    // pas de modification des urls
                    $output .= $bazaroutput;
                } else {
                    $output .= replaceLinksWithIframe($bazaroutput);
                }
            }
        } else {
            // on recupere les entetes html mais pas ce qu'il y a dans le body
            $output .= '<body class="yeswiki-iframe-body login-body"><div class="container"><div class="yeswiki-page-widget page-widget page" ' . $this->wiki->Format('{{doubleclic iframe="1"}}') . '>';

            if ($contenu = $this->wiki->LoadPage('PageLogin')) {
                // si une page PageLogin existe, on l'affiche
                $output .= $this->wiki->Format($contenu['body']);
            } else {
                // sinon on affiche le formulaire d'identification minimal
                $output .= '<div class="vertical-center white-bg"><div class="alert alert-danger alert-error">'
                    . _t('LOGIN_NOT_AUTORIZED') . '. ' . _t('LOGIN_PLEASE_REGISTER') . '.'
                    . '</div>' . $this->wiki->Format('{{login signupurl="0"}}') . '</div>';
            }
        }

        $output .= '</div>';
        // on affiche la barre de modification, si on ajoute &edit=1 à l'url de l'iframe
        if (isset($_GET['edit']) && $_GET['edit'] == '1') {
            $output .= $this->wiki->Format('{{barreredaction}}');
        }
        $output .= '</div>';

        $this->wiki->AddJavascriptFile('javascripts/vendor/iframe-resizer/iframeResizer.contentWindow.min.js');

        // on recupere les entetes html mais pas ce qu'il y a dans le body
        $header = explode('<body', $this->wiki->Header());
        $output = $header[0] . $output;
        // on recupere juste les javascripts et la fin des balises body et html
        $output .= preg_replace('/^.+<script/Us', '<script', $this->wiki->Footer());

        return $output;
    }
}
