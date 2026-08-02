<?php

namespace YesWiki\Render\Handler;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FavoritesManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;

class IframeHandler extends YesWikiHandler implements RegisteredHandler
{
    /** `/PageName/iframe` -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'iframe';
    }

    protected AssetRegistry $assetRegistry;
    protected $authenticationService;
    protected $entryController;
    protected $favoritesManager;

    public function run()
    {
        $this->assetRegistry = $this->getService(AssetRegistry::class);
        $this->authenticationService = $this->getService(AuthenticationService::class);
        $this->entryController = $this->getService(EntryController::class);
        $this->favoritesManager = $this->getService(FavoritesManager::class);
        $output = '';
        if (!$this->getService(PageContext::class)->getPage()) {
            echo str_replace(
                ['{beginLink}', '{endLink}'],
                ["<a href=\"{$this->getService(UrlFormatter::class)->href('editiframe')}\">", '</a>'],
                _t('NOT_FOUND_PAGE')
            );
        } elseif ($this->getService(AclService::class)->hasAccess('read')) {
            $entryManager = $this->getService(EntryManager::class);

            $output .= '<body class="yeswiki-iframe-body">' . "\n"
                . '<div class="container">' . "\n"
                . '<div class="yeswiki-page-widget page-widget page" ' . $this->getService(MarkdownFormatterService::class)->format('{{doubleclick iframe="1"}}')
                . '>' . "\n";

            if ($entryManager->isEntry($this->getService(PageContext::class)->getTag())) {
                $output .= $this->renderBazarEntry();
            } else {
                $output .= $this->renderWikiPage();
            }
        } else {
            // if no read access to the page

            // on recupere les entetes html mais pas ce qu'il y a dans le body
            $output .= '<body class="yeswiki-iframe-body login-body">' . "\n"
                . '<div class="container">' . "\n"
                . '<div class="yeswiki-page-widget page-widget page" ' . $this->getService(MarkdownFormatterService::class)->format('{{doubleclick iframe="1"}}')
                . '>' . "\n";

            if ($contenu = $this->getService(PageManager::class)->getOne('PageLogin')) {
                // si une page PageLogin existe, on l'affiche
                $output .= $this->replaceLinksWithIframeIfNeeded($this->getService(MarkdownFormatterService::class)->format(PageBody::content($contenu['body'])));
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
            $output .= $this->getService(MarkdownFormatterService::class)->format('{{editbar}}');
        }
        $output .= '</div><!-- end .container -->' . "\n";
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/vendor/iframe-resizer/iframeResizer.contentWindow.min.js');

        // An iframe wants the wiki's <head> and none of the theme's chrome. It used to get
        // there by splitting the rendered header on '<body' and regexing the footer for its
        // first '<script'; ticket 15 made that unnecessary -- renderHead() is exactly the
        // first half, and every script it needs is now declared into it rather than flushed
        // at the end of the body. Called after $output is built, so the assets that content
        // declared are included.
        return $this->getService(TemplateEngine::class)->renderHead()
            . "<body>\n" . $output . "\n</body>\n</html>";
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
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/bazar.js', true, true);
        $tab_valeurs = ($this->getService(PageContext::class)->getPage() ?? [])['body'];
        if (YW_CHARSET != 'UTF-8') {
            $tab_valeurs = array_map(function ($value) {
                return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
            }, $tab_valeurs);
        }
        $entry = $this->entryController->view($this->getService(PageContext::class)->getTag(), 0, true);
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
            $tag = $this->getService(PageContext::class)->getTag();
            $isUserFavorite = $this->favoritesManager->isUserFavorite($currentuser, $tag);
            // TODO use twig (with other part of this handler also)
            $this->assetRegistry->addJsFile('javascripts/favorites.js');
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
                        <svg class="yw-icon $iconClass" aria-hidden="true"><use href="src/assets/icons.svg#star"/></svg>
                </a>
            HTML;
        }
        // on ajoute un bouton de partage, si &share=1 est présent dans l'url
        if ($this->getRequest()->query->get('share') == '1') {
            $output .= '<a class="btn btn-sm btn-default link-share modalbox pull-right" href="'
                . $this->getService(UrlFormatter::class)->href('share') . '" title="' . _t('TEMPLATE_SEE_SHARING_OPTIONS') . ' '
                . $this->getService(PageContext::class)->getTag() . '"><svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#share"/></svg>' . _t('TEMPLATE_SHARE')
                . '</a>';
        }

        // affichage de la page formatée
        $output .= $this->replaceLinksWithIframeIfNeeded($this->getService(MarkdownFormatterService::class)->format(PageBody::content(($this->getService(PageContext::class)->getPage() ?? [])['body'])));

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
