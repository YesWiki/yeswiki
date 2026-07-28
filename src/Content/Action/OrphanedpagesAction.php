<?php

namespace YesWiki\Content\Action;

use YesWiki\Search\Service\TagsManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

/**
 * `{{orphanedpages}}` -- converted from the procedural actions/orphanedpages.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class OrphanedpagesAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'orphanedpages';
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
        List all orphaned pages BUT bazar records
        */


        $tagsManager = $this->wiki->services->get(TagsManager::class);

        if ($pages = $tagsManager->getPagesByTags('', 'wiki', '', '')) {
            foreach ($pages as $page) {
                if ($this->wiki->IsOrphanedPage($page['tag'])) {
                    echo $this->wiki->ComposeLinkToPage($page['tag'], '', '', 0), "<br />\n";
                }
            }
        } else {
            echo '<i>' . _t('NO_ORPHAN_PAGES') . '</i>';
        }
    }
}
