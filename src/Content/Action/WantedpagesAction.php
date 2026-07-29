<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Render\Service\LinkRenderer;

/**
 * `{{wantedpages}}` -- converted from the procedural actions/wantedpages.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class WantedpagesAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'wantedpages';
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
        if ($pages = $this->getService(PageManager::class)->getWanted()) {
            echo "<ul>\n";
            foreach ($pages as $page) {
                echo '	<li>', $page['tag'];
                echo $this->getService(LinkRenderer::class)->linkToPage($page['tag'], 'edit', '?', false);
                echo ' (';
                echo $this->getService(LinkRenderer::class)->linkToPage($page['tag'], 'backlinks', $page['count'], false);
                echo ")</li>\n";
            }
            echo "</ul>\n";
        } else {
            echo '<i>' . _t('NO_PAGE_TO_CREATE') . '.</i>';
        }
    }
}
