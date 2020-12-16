<?php

use YesWiki\Core\YesWikiHandler;
use YesWiki\Bazar\Service\FicheManager;

class IframeHandler extends YesWikiHandler
{
    function run()
    {
        // on recupere les entetes html mais pas ce qu'il y a dans le body
        $header = explode('<body', $this->wiki->Header());
        $output = $header[0];

        if ($this->wiki->HasAccess("read")) {
            if (!$this->wiki->page) {
                return;
            } else {
                $output .= '<body class="yeswiki-iframe-body">' . "\n"
                    . '<div class="container">' . "\n";
                $ficheManager = $this->wiki->services->get(FicheManager::class);

                if ($ficheManager->isFiche($this->wiki->GetPageTag())) {
                    $output .= $this->renderBazarEntry();
                } else {
                    $output .= $this->renderWikiPage();
                }
            }
        } else { // if no access to the page

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

        return $output;
    }

    /**
     * Render the bazar entry as an iframe
     * @return string the generated output
     */
    private function renderBazarEntry(): string
    {
        $output = '';
        // si la page est une fiche bazar, alors on affiche la fiche plutot que de formater en wiki
        $this->wiki->AddJavascriptFile('tools/bazar/libs/bazar.js');
        $valjson = $this->wiki->page["body"];
        $tab_valeurs = json_decode($valjson, true);
        if (YW_CHARSET != 'UTF-8') {
            $tab_valeurs = array_map('utf8_decode', $tab_valeurs);
        }
        $entry = baz_voir_fiche(true, $tab_valeurs);
        if (!empty($entry)) {
            $output .= '<div class="yeswiki-page-widget page-widget page">' . "\n";

            // affichage de la page formatee
            if (isset($_GET['iframelinks']) && $_GET['iframelinks'] == '0') {
                // pas de modification des urls
                $output .= $entry;
            } else {
                $output .= $this->replaceLinksWithIframe($entry);
            }
        }
        return $output;
    }

    /**
     * Render the wiki page as an iframe
     * @return string the generated output
     */
    private function renderWikiPage(): string
    {
        // on recupere les entetes html mais pas ce qu'il y a dans le body
        $output = '<div class="yeswiki-page-widget page-widget page" ' . $this->wiki->Format('{{doubleclic iframe="1"}}')
            . '>' . "\n";

        // on ajoute un bouton de partage, si &share=1 est présent dans l'url
        if (isset($_GET['share']) && $_GET['share'] == '1') {
            $output .= '<a class="btn btn-small btn-default link-share modalbox pull-right" href="'
                . $this->wiki->href('share') . '" title="' . _t('TEMPLATE_SEE_SHARING_OPTIONS') . ' '
                . $this->wiki->GetPageTag() . '"><i class="fa fa-share-alt"></i>&nbsp;' . _t('TEMPLATE_SHARE')
                . '</a>';
        }

        // affichage de la page formatee
        if (isset($_GET['iframelinks']) && $_GET['iframelinks'] == '0') {
            // pas de modification des urls
            $output .= $this->wiki->Format($this->wiki->page["body"], 'wakka', $this->wiki->GetPageTag());
        } else {
            $body = $this->wiki->Format($this->wiki->page["body"], 'wakka', $this->wiki->GetPageTag());
            $output .= $this->replaceLinksWithIframe($body);
        }
        return $output;
    }

    /**
     * Replace links with the /iframe handler when not opened in a new window
     * @param string $body the body page as source
     * @return string the body page with the link replacements
     */
    private function replaceLinksWithIframe(string $body): string
    {
        // pattern qui rajoute le /iframe pour les liens au bon endroit, merci raphael@tela-botanica.org
        $pattern = '~(<a.*?href.*?)' . preg_quote($this->wiki->config['base_url']) . '([\w\-_]+)([&#?].*?)?(["\'])([^>]*?>)~i';
        $pagebody = preg_replace_callback(
            $pattern,
            function ($matches) {
                // on vérifie si le lien ne s'ouvre pas dans une nouvelle fenêtre, c'est à dire s'il n'y a pas d'attribut
                // target="_blank" ou class="new window" avant ou après le href
                // et si le lien ne s'ouvre dans une autre fenêtre, on insère /iframe à l'url
                $NEW_WINDOW_PATTERN = "~^(.*target=[\"']\s*_blank\s*[\"'].*)|(.*class=[\"'].*?new-window.*?[\"'].*)$~i";
                if (preg_match($NEW_WINDOW_PATTERN, $matches[1]) || preg_match($NEW_WINDOW_PATTERN,
                        $matches[5])) {
                    return $matches[1] . $this->wiki->config['base_url'] . $matches[2] . $matches[3] . $matches[4] .
                        $matches[5];
                } else {
                    return $matches[1] . $this->wiki->config['base_url'] . $matches[2] . '/iframe' . $matches[3] .
                        $matches[4] . $matches[5];
                }
            },
            $body
        );

        // pattern qui rajoute le /editiframe pour les liens au bon endroit
        $pattern = '~(<a.*?href.*?)' . preg_quote($this->wiki->config['base_url']) . '([\w\-_]+)\/edit([&#?].*?)?(["\'])([^>]*?>)~i';
        $pagebody = preg_replace_callback(
            $pattern,
            function ($matches) {
                // on vérifie si le lien ne s'ouvre pas dans une nouvelle fenêtre, c'est à dire s'il n'y a pas d'attribut
                // target="_blank" ou class="new window" avant ou après le href
                // et si le lien ne s'ouvre dans une autre fenêtre, on insère /editiframe à l'url
                $NEW_WINDOW_PATTERN = "~^(.*target=[\"']\s*_blank\s*[\"'].*)|(.*class=[\"'].*?new-window.*?[\"'].*)$~i";
                if (preg_match($NEW_WINDOW_PATTERN, $matches[1]) || preg_match($NEW_WINDOW_PATTERN,
                        $matches[5])) {
                    return $matches[1] . $this->wiki->config['base_url'] . $matches[2] . '/edit' . $matches[3]
                        . $matches[4] . $matches[5];
                } else {
                    return $matches[1] . $this->wiki->config['base_url'] . $matches[2] . '/editiframe' . $matches[3]
                        . $matches[4] . $matches[5];
                }
            },
            $pagebody
        );
        return $pagebody;
    }
}