<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;

/**
 * `{{pagetitle}}` -- converted from the procedural actions/titrepage.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class PageTitleAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'pagetitle';
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
        $title = htmlspecialchars($this->getService(\YesWiki\Render\Service\TemplateHelperService::class)->getTitleFromBody($this->getService(PageContext::class)->getPage() ?? []), ENT_COMPAT | ENT_HTML5);
        if ($title) {
            echo $title;
        } else {
            echo $this->getService(PageContext::class)->getTag();
        }
    }
}
