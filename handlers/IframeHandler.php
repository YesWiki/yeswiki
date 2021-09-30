<?php

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\AssetsManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiHandler;

class IframeHandler extends YesWikiHandler
{
    function run()
    {
        $this->getService(AssetsManager::class)->AddJavascriptFile('tools/templates/libs/vendor/iframeResizer.contentWindow.min.js');
        
        if ($this->getService(AclService::class)->hasAccess("read")) {
            $entryManager = $this->wiki->services->get(EntryManager::class);
            if ($entryManager->isEntry($this->wiki->GetPageTag())) {
                $pageContent = $this->renderBazarEntry();
            } else {
                $pageContent = $this->renderWikiPage();
            }
        } else {
            // if no read access to the page, display login screen
            if ($loginPage = $this->getService(PageManager::class)->getOne("PageLogin")) {
                $pageContent = $this->wiki->Format($loginPage["body"]);
            } else {
                $pageContent = <<<"HTML"
                    <div class="vertical-center white-bg">
                        <div class="alert alert-danger alert-error">
                            {_t('LOGIN_NOT_AUTORIZED')} {_t('LOGIN_PLEASE_REGISTER')}
                        </div>  
                        {$this->wiki->Format('{{login signupurl="0"}}')}
                    </div>                    
                HTML;
            }
        }

        $actionBar = "";
        // Adds actionbar if required
        if (isset($_GET['edit']) && $_GET['edit'] == '1') {
            $actionBar = $this->wiki->Format('{{barreredaction}}');
        }

        return <<<"HTML"
            <body class="yeswiki-iframe-body login-body">
                {$this->wiki->Format('{{linkstyle}}')}
                <div class="container">
                    <div class="yeswiki-page-widget page-widget page" {$this->wiki->Format('{{doubleclic iframe="1"}}')}>
                        $pageContent
                    </div>
                    $actionBar
                </div>
                {$this->wiki->Format('{{linkjavascript}}')}
            </body>
        HTML;
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
            // affichage de la page formatee
            if (isset($_GET['iframelinks']) && $_GET['iframelinks'] == '0') {
                // pas de modification des urls
                $output .= $entry;
            } else {
                $output .= replaceLinksWithIframe($entry);
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
        $output = '';
        // on ajoute un bouton de partage, si &share=1 est présent dans l'url
        if (isset($_GET['share']) && $_GET['share'] == '1') {
            $output .= '<a class="btn btn-sm btn-default link-share modalbox pull-right" href="'
                . $this->wiki->href('share') . '" title="' . _t('TEMPLATE_SEE_SHARING_OPTIONS') . ' '
                . $this->wiki->GetPageTag() . '"><i class="fa fa-share-alt"></i>' . _t('TEMPLATE_SHARE')
                . '</a>';
        }

        // affichage de la page formatée
        if (isset($_GET['iframelinks']) && $_GET['iframelinks'] == '0') {
            // pas de modification des urls
            $output .= $this->wiki->Format($this->wiki->page["body"], 'wakka', $this->wiki->GetPageTag());
        } else {
            $body = $this->wiki->Format($this->wiki->page["body"], 'wakka', $this->wiki->GetPageTag());
            $output .= replaceLinksWithIframe($body);
        }
        return $output;
    }
}