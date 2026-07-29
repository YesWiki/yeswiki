<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Render\Service\LinkRenderer;

/**
 * `{{mypages}}` -- converted from the procedural actions/mypages.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class MypagesAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'mypages';
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
        if ($user = $this->wiki->GetUser()) {
            echo '<b>' . _t('LIST_OF_PAGES_WHERE_YOU_ARE_THE_OWNER') . ".</b><br /><br />\n";

            $my_pages_count = 0;
            $curChar = '';

            if ($pages = $this->wiki->LoadAllPages()) {
                foreach ($pages as $page) {
                    if ($this->wiki->GetUserName() == $page['owner'] && !preg_match('/^Comment/', $page['tag'])) {
                        // XXX: strtoupper is locale dependent
                        $firstChar = strtoupper($page['tag'][0]);
                        if (!preg_match('/' . WN_UPPER . '/', $firstChar)) {
                            $firstChar = '#';
                        }

                        if ($firstChar != $curChar) {
                            if ($curChar) {
                                echo "<br />\n";
                            }
                            echo "<b>$firstChar</b><br />\n";
                            $curChar = $firstChar;
                        }

                        echo $this->getService(LinkRenderer::class)->linkToPage($page['tag']),"<br />\n";

                        $my_pages_count++;
                    }
                }

                if ($my_pages_count == 0) {
                    echo '<i>' . _t('YOU_DONT_OWN_ANY_PAGE') . '.</i>';
                }
            } else {
                echo '<i>' . _t('NO_PAGE_FOUND') . '.</i>';
            }
        } else {
            echo '<div class="alert alert-danger">' . _t('YOU_ARENT_LOGGED_IN') . ' : ' . _t('IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES') . ".</div>\n";
        }
    }
}
