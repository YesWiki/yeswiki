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
    protected AuthenticationService $authenticationService;
    protected EntryController $entryController;
    protected FavoritesManager $favoritesManager;

    /**
     * @return string a full HTML document for the iframe
     */
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
            $output .= '<body class="yeswiki-iframe-body login-body">' . "\n"
                . '<div class="container">' . "\n"
                . '<div class="yeswiki-page-widget page-widget page" ' . $this->getService(MarkdownFormatterService::class)->format('{{doubleclick iframe="1"}}')
                . '>' . "\n";

            if ($contenu = $this->getService(PageManager::class)->getOne('PageLogin')) {
                $output .= $this->replaceLinksWithIframeIfNeeded($this->getService(MarkdownFormatterService::class)->format(PageBody::content($contenu['body'])));
            } else {
                $output .= '<div class="vertical-center white-bg">' . "\n"
                    . '<div class="alert alert-danger alert-error">' . "\n"
                    . _t('LOGIN_NOT_AUTORIZED') . '. ' . _t('LOGIN_PLEASE_REGISTER') . '.' . "\n"
                    . '</div>' . "\n"
                    . $this->replaceLinksWithIframeIfNeeded($this->getService(MarkdownFormatterService::class)->format('{{login template="login-form.twig" signupurl="0"}}' . "\n\n"))
                    . '</div><!-- end .vertical-center -->' . "\n";
            }
        }

        $output .= '</div><!-- end .page-widget -->' . "\n";

        if ($this->getRequest()->query->get('edit') == '1') {
            $output .= $this->getService(MarkdownFormatterService::class)->format('{{editbar}}');
        }
        $output .= '</div><!-- end .container -->' . "\n";
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/vendor/iframe-resizer/iframeResizer.contentWindow.min.js');

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

        $this->getService(AssetRegistry::class)->addJsFile('javascripts/bazar.js', true, true);
        $tab_valeurs = ($this->getService(PageContext::class)->getPage() ?? [])['body'];
        if (YW_CHARSET != 'UTF-8') {
            $tab_valeurs = array_map(function ($value) {
                return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
            }, $tab_valeurs);
        }
        $entry = $this->entryController->view($this->getService(PageContext::class)->getTag(), '', true);
        if (!empty($entry)) {
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

        $user = $this->authenticationService->getLoggedUser();
        if (!empty($user) && $this->favoritesManager->areFavoritesActivated()) {
            $currentuser = $user['name'];
            $tag = $this->getService(PageContext::class)->getTag();
            $isUserFavorite = $this->favoritesManager->isUserFavorite($currentuser, $tag);

            $this->assetRegistry->addJsFile('javascripts/favorites.js');
            $extraClass = $isUserFavorite ? ' user-favorite' : '';
            $iconClass = $isUserFavorite ? 'fas' : 'far';
            $title = ($isUserFavorite) ? _t('FAVORITES_REMOVE') : _t('FAVORITES_ADD');

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

        if ($this->getRequest()->query->get('share') == '1') {
            $output .= '<a class="btn btn-sm btn-default link-share modalbox pull-right" href="'
                . $this->getService(UrlFormatter::class)->href('share') . '" title="' . _t('TEMPLATE_SEE_SHARING_OPTIONS') . ' '
                . $this->getService(PageContext::class)->getTag() . '"><svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#share"/></svg>' . _t('TEMPLATE_SHARE')
                . '</a>';
        }

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
            return $input;
        }

        return $this->getService(UrlFormatter::class)->throughIframeHandler($input);
    }
}
