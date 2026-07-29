<?php

namespace YesWiki\Render\Handler;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FavoritesManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\AssetsManager;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\MarkdownFormatterService;

class IframeHandler extends YesWikiHandler implements RegisteredHandler
{
    /** `/PageName/iframe` -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'iframe';
    }

    protected $assetsManager;
    protected $authenticationService;
    protected $entryController;
    protected $favoritesManager;

    public function run()
    {
        $this->assetsManager = $this->getService(AssetsManager::class);
        $this->authenticationService = $this->getService(AuthenticationService::class);
        $this->entryController = $this->getService(EntryController::class);
        $this->favoritesManager = $this->getService(FavoritesManager::class);
        $output = '';
        if (!$this->wiki->page) {
            echo str_replace(
                ['{beginLink}', '{endLink}'],
                ["<a href=\"{$this->getService(UrlFormatter::class)->href('editiframe')}\">", '</a>'],
                _t('NOT_FOUND_PAGE')
            );
        } elseif ($this->getService(AclService::class)->hasAccess('read')) {
            $entryManager = $this->wiki->services->get(EntryManager::class);

            $output .= '<body class="yeswiki-iframe-body">' . "\n"
                . '<div class="container">' . "\n"
                . '<div class="yeswiki-page-widget page-widget page" ' . $this->getService(MarkdownFormatterService::class)->format('{{doubleclic iframe="1"}}')
                . '>' . "\n";

            if ($entryManager->isEntry($this->wiki->GetPageTag())) {
                $output .= $this->renderBazarEntry();
            } else {
                $output .= $this->renderWikiPage();
            }
        } else {
            // if no read access to the page

            // on recupere les entetes html mais pas ce qu'il y a dans le body
            $output .= '<body class="yeswiki-iframe-body login-body">' . "\n"
                . '<div class="container">' . "\n"
                . '<div class="yeswiki-page-widget page-widget page" ' . $this->getService(MarkdownFormatterService::class)->format('{{doubleclic iframe="1"}}')
                . '>' . "\n";

            if ($contenu = $this->getService(PageManager::class)->getOne('PageLogin')) {
                // si une page PageLogin existe, on l'affiche
                $output .= $this->replaceLinksWithIframeIfNeeded($this->getService(MarkdownFormatterService::class)->format($contenu['body']));
            } else {
                // sinon on affiche le formulaire d'identification minimal
                $output .= '<div class="vertical-center white-bg">' . "\n"
                    . '<div class="alert alert-danger alert-error">' . "\n"
                    . _t('LOGIN_NOT_AUTORIZED') . '. ' . _t('LOGIN_PLEASE_REGISTER') . '.' . "\n"
                    . '</div>' . "\n"
                    . $this->replaceLinksWithIframeIfNeeded($this->getService(MarkdownFormatterService::class)->format('{{login signupurl="0"}}' . "\n\n"))
                    . '</div><!-- end .vertical-center -->' . "\n";
            }
        }

        // common footer for all iframe page
        $output .= '</div><!-- end .page-widget -->' . "\n";

        // on affiche la barre de modification, si on ajoute &edit=1 à l'url de l'iframe
        if ($this->getRequest()->query->get('edit') == '1') {
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

    /**
     * Render the bazar entry as an iframe.
     *
     * @return string the generated output
     */
    private function renderBazarEntry(): string
    {
        $output = '';
        // si la page est une fiche bazar, alors on affiche la fiche plutot que de formater en wiki
        $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/bazar.js', true, true);
        $valjson = $this->wiki->page['body'];
        $tab_valeurs = json_decode($valjson, true);
        if (YW_CHARSET != 'UTF-8') {
            $tab_valeurs = array_map(function ($value) {
                return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
            }, $tab_valeurs);
        }
        $entry = $this->entryController->view($this->wiki->tag, 0, true);
        if (!empty($entry)) {
            // affichage de la page formatee
            $output .= $this->replaceLinksWithIframeIfNeeded($entry);
        }

        return $output;
    }

    /**
     * Render the wiki page as an iframe.
     *
     * @return string the generated output
     */
    private function renderWikiPage(): string
    {
        $output = '';
        // on ajoute le bouton pour les favoris
        $user = $this->authenticationService->getLoggedUser();
        if (!empty($user) && $this->favoritesManager->areFavoritesActivated()) {
            $currentuser = $user['name'];
            $tag = $this->wiki->GetPageTag();
            $isUserFavorite = $this->favoritesManager->isUserFavorite($currentuser, $tag);
            // TODO use twig (with other part of this handler also)
            $this->assetsManager->AddJavascriptFile('javascripts/favorites.js');
            $extraClass = $isUserFavorite ? ' user-favorite' : '';
            $iconClass = $isUserFavorite ? 'fas' : 'far';
            $title = ($isUserFavorite) ? _t('FAVORITES_REMOVE') : _t('FAVORITES_ADD');
            // HEREDOC syntax
            $output .= <<<HTML
                <a href="#"
                    title="$title"
                    data-resource="$tag"
                    data-user="$currentuser"
                    data-toggle="tooltip"
                    data-placement="left"
                    class="btn btn-icon favorites pull-right $extraClass">
                        <i class="$iconClass fa-star"></i>
                </a>
            HTML;
        }
        // on ajoute un bouton de partage, si &share=1 est présent dans l'url
        if ($this->getRequest()->query->get('share') == '1') {
            $output .= '<a class="btn btn-sm btn-default link-share modalbox pull-right" href="'
                . $this->getService(UrlFormatter::class)->href('share') . '" title="' . _t('TEMPLATE_SEE_SHARING_OPTIONS') . ' '
                . $this->wiki->GetPageTag() . '"><i class="fa fa-share-alt"></i>' . _t('TEMPLATE_SHARE')
                . '</a>';
        }

        // affichage de la page formatée
        $output .= $this->replaceLinksWithIframeIfNeeded($this->getService(MarkdownFormatterService::class)->format($this->wiki->page['body']));

        return $output;
    }

    /**
     * replace links with iframe if needed.
     *
     * @return string $output
     */
    private function replaceLinksWithIframeIfNeeded(string $input): string
    {
        if ($this->getRequest()->query->get('iframelinks') == '0') {
            // pas de modification des urls
            return $input;
        }

        return replaceLinksWithIframe($input);
    }
}
