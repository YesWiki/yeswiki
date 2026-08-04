<?php

namespace YesWiki\Render\Handler;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\PasswordForEditingService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Performer;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;

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

                    if ($entryManager->isEntry($this->getService(PageContext::class)->getTag())) {
                        $buffer = $entryController->update($this->getService(PageContext::class)->getTag());
                    } else {
                        ob_start();
                        $buffer = $this->getService(Performer::class)->run('edit', 'handler', []);
                        $buffer = ob_get_contents() . $buffer;
                        ob_end_clean();
                    }
                }
            }

            $this->getService(AssetRegistry::class)->addJsFile('javascripts/bazar.js', true, true);
            $output .= '<body class="yeswiki-iframe-body">' . "\n"
                . '<div class="container">' . "\n"
                . '<div class="yeswiki-page-widget page-widget page">' . "\n";

            $output .= replaceLinksWithIframe($buffer);
        } else {
            // if no write access to the page

            $output = '<body class="yeswiki-iframe-body login-body">' . "\n"
                . '<div class="container">' . "\n"
                . '<div class="yeswiki-page-widget page-widget page" ' . $this->getService(MarkdownFormatterService::class)->format('{{doubleclick iframe="1"}}')
                . '>' . "\n";

            if ($contenu = $this->getService(PageManager::class)->getOne('PageLogin')) {
                // si une page PageLogin existe, on l'affiche
                $output .= replaceLinksWithIframe($this->getService(MarkdownFormatterService::class)->format(PageBody::content($contenu['body'])));
            } else {
                // sinon on affiche le formulaire d'identification minimal
                $output .= '<div class="vertical-center white-bg">' . "\n"
                    . '<div class="alert alert-danger alert-error">' . "\n"
                    . _t('LOGIN_NOT_AUTORIZED') . '. ' . _t('LOGIN_PLEASE_REGISTER') . '.' . "\n"
                    . '</div>' . "\n"
                    . replaceLinksWithIframe($this->getService(MarkdownFormatterService::class)->format('{{login template="login-form.twig" signupurl="0"}}' . "\n\n"))
                    . '</div><!-- end .vertical-center -->' . "\n";
            }
        }

        // common footer for all iframe page
        $output .= '</div><!-- end .page-widget -->' . "\n";

        // on affiche la barre de modification, si on ajoute &edit=1 à l'url de l'iframe
        if (isset($_GET['edit']) && $_GET['edit'] == '1') {
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
}
