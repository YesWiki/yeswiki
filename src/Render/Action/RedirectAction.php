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

/** `{{redirect}}` -- converted from the procedural actions/redirect.php by ticket 06. */
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
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        $redirPageName = $this->getService(PerformableArguments::class)->get('page');

        if (!$redirPageName) {
            echo '<div class="alert alert-danger"><strong>' . _t('ERROR_ACTION_REDIRECT') . '</strong> : ' . _t('MISSING_PAGE_PARAMETER') . '.</div>' . "\n";
        } else {
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
