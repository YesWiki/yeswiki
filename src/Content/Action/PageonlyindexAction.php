<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Render\Service\LinkRenderer;

/**
 * `{{pageonlyindex}}` -- converted from the procedural actions/pageonlyindex.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class PageonlyindexAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'pageonlyindex';
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
        pageonlyindex.php
        lists all the pages of the wiki BUT bazar records.

        @licence: AGPL
        */
        if ($pages = $this->getService(DbService::class)->loadAll('SELECT tag FROM ' . $this->wiki->config['table_prefix'] . 'pages WHERE latest = \'Y\' AND comment_on=\'\' AND body not LIKE \'{"%\' ORDER BY tag')) {
            foreach ($pages as $page) {
                // XXX: strtoupper is locale dependent
                $firstChar = strtoupper($page['tag'][0]);
                if (!preg_match('/' . WN_UPPER . '/', $firstChar)) {
                    $firstChar = '#';
                }

                if (empty($curChar) || $firstChar != $curChar) {
                    if (!empty($curChar)) {
                        echo "<br />\n";
                    }
                    echo "<b>$firstChar</b><br />\n";
                    $curChar = $firstChar;
                }

                echo $this->getService(LinkRenderer::class)->linkToPage($page['tag'], '', '', false),"<br />\n";
            }
        } else {
            echo '<i>' . _t('NO_PAGE_FOUND') . '.</i>';
        }
    }
}
