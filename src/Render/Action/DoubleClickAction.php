<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * `{{doubleclic}}` -- converted from the procedural actions/doubleclic.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class DoubleClickAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'doubleclic';
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
        $page = $this->getService(PerformableArguments::class)->get('page');
        $isIframe = $this->getService(PerformableArguments::class)->get('iframe') && (!isset($_GET['iframelinks']) or $_GET['iframelinks'] != '0');
        if ($this->getService(PageContext::class)->getMethod() == 'show' && $this->getService(AclService::class)->hasAccess('write', $page)) {
            $method = $isIframe ? 'editiframe' : 'edit';
            // javascript du double clic (on peut passer en parametre une page wiki au editer en doublecliquant)
            if (!empty($page)) {
                echo 'ondblclick="document.location=\'' . $this->getService(UrlFormatter::class)->href($method, $page) . '\';" ';
            } else {
                echo 'ondblclick="document.location=\'' . $this->getService(UrlFormatter::class)->href($method) . '\';" ';
            }
        }
    }
}
