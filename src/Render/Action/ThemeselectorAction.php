<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\ThemeSelectorRenderer;

/**
 * `{{themeselector}}` -- converted from the procedural actions/themeselector.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class ThemeselectorAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'themeselector';
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
        $class = $this->getService(PerformableArguments::class)->get('class');
        if (
            $this->getService(AclService::class)->isAdmin()
            && isset($_POST['action']) && ($_POST['action'] === 'setTemplate')
        ) {
            $this->getService(ActionRunner::class)->action('setwikidefaulttheme');
            // if not redirected by setwikidefaulttheme : redirect
            $this->wiki->Redirect($this->getService(UrlFormatter::class)->href('', $this->getService(PageContext::class)->getTag()));
        } else {
            echo $this->wiki->services->get(ThemeSelectorRenderer::class)->showFormThemeSelector('selector', $class);
        }
    }
}
