<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\LinkRenderer;

/**
 * `{{redirect}}` -- converted from the procedural actions/redirect.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class RedirectAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'redirect';
    }

    public function components(): array
    {
        return [
            Component::for('redirect')
                ->category(Category::Navigation)
                ->label(_t('AB_advanced_action_redirect_label'))
                ->icon('arrow-forward-up')
                ->previewHeight('200px')
                ->settings(
                    Setting::page('page')
                        ->label(_t('AB_advanced_action_backlinks_page_label'))
                        ->required(),
                ),
        ];
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            // Several of these bodies end in $this->exit(), which throws. The old
            // runFileInBuffer() accumulated output into a by-reference variable, so a throw
            // did not discard what had already been printed; keep that by flushing into the
            // shared output before rethrowing -- and close the buffer either way.
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        /*
        Permet de faire une redirection vers une autre pages Wiki du site
        Parametres : page : nom wiki de la page vers laquelle ont doit rediriger (obligatoire)
        exemple : {{redirect page="BacASable"}}
        */

        $redirPageName = $this->getService(PerformableArguments::class)->get('page');

        if (!$redirPageName) {
            echo '<div class="alert alert-danger"><strong>' . _t('ERROR_ACTION_REDIRECT') . '</strong> : ' . _t('MISSING_PAGE_PARAMETER') . '.</div>' . "\n";
        } else {
            // showing *this* page redirects; showing it as one of a hundred entries in a
            // list, or inside an {{include}}, prints the note below instead. Both swap the
            // rendered tag and put it back, so the request's own tag is the test (ticket 11
            // follow-up): one entry carrying {{redirect}} used to 302 the whole list.
            $pageContext = $this->getService(PageContext::class);
            if ($pageContext->getMethod() == 'show' && $pageContext->isRenderingRequestedPage()) {
                if (!isset($_SESSION['redirects'])) {
                    $_SESSION['redirects'] = [];
                }
                $_SESSION['redirects'][] = strtolower($this->getService(PageContext::class)->getTag());

                if (in_array(strtolower($redirPageName), $_SESSION['redirects'])) {
                    echo '<div class="alert alert-danger"><strong>' . _t('ERROR_ACTION_REDIRECT') . '</strong> : ' . _t('CIRCULAR_REDIRECTION_FROM_PAGE') . " $redirPageName ( "
                    . $this->getService(LinkRenderer::class)->linkToPage($redirPageName, 'edit', call_user_func('_t', 'CLICK_HERE')) . ')</div>' . "\n";
                } else {
                    $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', $redirPageName));
                }
            } else {
                echo '<span style="color: red; weight: bold">' . _t('PRESENCE_OF_REDIRECTION_TO') . ' "' . $this->getService(LinkRenderer::class)->link($redirPageName) . '"</span>';
            }
        }
    }
}
