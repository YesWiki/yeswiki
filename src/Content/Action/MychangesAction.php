<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\LinkRenderer;

/**
 * `{{mychanges}}` -- converted from the procedural actions/mychanges.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class MychangesAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'mychanges';
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
        if ($user = $this->getService(AuthenticationService::class)->getLoggedUser()) {
            $my_edits_count = 0;
            $curChar = '';
            $curday = '';
            $last_tag = '';
            $dbService = $this->getService(DbService::class);
            $userCol = $dbService->quoteIdentifier('user');

            if ($bydate = $this->getService(PerformableArguments::class)->get('bydate')) {
                echo '<b>' . _t('YOUR_MODIFIED_PAGES_ORDERED_BY_MODIFICATION_DATE') . ".</b><br /><br />\n";

                if ($pages = $this->getService(DbService::class)->loadAll('SELECT tag, time FROM ' . $this->getService(RuntimeConfig::class)['table_prefix'] . "pages WHERE $userCol = '" . $dbService->escape($this->getService(AuthenticationService::class)->getLoggedUserName()) . "' AND tag NOT LIKE 'Comment%' ORDER BY time ASC, tag ASC")) {
                    foreach ($pages as $page) {
                        $edited_pages[$page['tag']] = $page['time'];
                    }

                    arsort($edited_pages);

                    foreach ($edited_pages as $page['tag'] => $page['time']) {
                        // day header
                        list($day, $time) = explode(' ', $page['time']);
                        if ($day != $curday) {
                            if ($curday) {
                                echo "<br />\n";
                            }
                            echo "<b>$day:</b><br />\n";
                            $curday = $day;
                        }

                        // echo entry
                        echo "&nbsp;&nbsp;&nbsp;($time) (",$this->getService(LinkRenderer::class)->linkToPage($page['tag'], 'revisions', 'history', 0),') ',$this->getService(LinkRenderer::class)->linkToPage($page['tag'], '', '', 0),"<br />\n";

                        $my_edits_count++;
                    }

                    if ($my_edits_count == 0) {
                        echo '<i>' . _t('YOU_DIDNT_MODIFY_ANY_PAGE') . '.</i>';
                    }
                } else {
                    echo '<i>' . _t('NO_PAGE_FOUND') . '.</i>';
                }
            } else {
                echo '<b>' . _t('YOUR_MODIFIED_PAGES_ORDERED_BY_NAME') . ".</b><br /><br />\n";

                if ($pages = $this->getService(DbService::class)->loadAll('SELECT tag, time FROM ' . $this->getService(RuntimeConfig::class)['table_prefix'] . "pages WHERE $userCol = '" . $dbService->escape($this->getService(AuthenticationService::class)->getLoggedUserName()) . "' AND tag NOT LIKE 'Comment%' ORDER BY tag ASC, time DESC")) {
                    foreach ($pages as $page) {
                        if ($last_tag != $page['tag']) {
                            $last_tag = $page['tag'];
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

                            // echo entry
                            echo '&nbsp;&nbsp;&nbsp;(',$page['time'],') (',$this->getService(LinkRenderer::class)->linkToPage($page['tag'], 'revisions', 'history', 0),') ',$this->getService(LinkRenderer::class)->linkToPage($page['tag'], '', '', 0),"<br />\n";

                            $my_edits_count++;
                        }
                    }

                    if ($my_edits_count == 0) {
                        echo '<i>' . _t('YOU_DIDNT_MODIFY_ANY_PAGE') . '.</i>';
                    }
                } else {
                    echo '<i>' . _t('NO_PAGE_FOUND') . '.</i>';
                }
            }
        } else {
            echo '<div class="alert alert-danger">' . _t('YOU_ARENT_LOGGED_IN') . ' : ' . _t('IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES') . ".</div>\n";
        }
    }
}
