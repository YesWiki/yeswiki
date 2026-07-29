<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\CommentService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\MarkdownFormatterService;

/**
 * `{{recentlycommented}}` -- converted from the procedural actions/recentlycommented.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class RecentlycommentedAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'recentlycommented';
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
        // Which is the max number of pages to be shown ?
        if ($max = $this->getService(PerformableArguments::class)->get('max')) {
            if ($max == 'last') {
                $max = 50;
            } else {
                $last = (int)$max;
            }
        } else {
            $max = 50;
        }

        // Show recently commented pages
        if ($pages = $this->getService(CommentService::class)->getRecentlyCommented($max)) {
            if ($this->getService(PerformableArguments::class)->get('max')) {
                foreach ($pages as $page) {
                    // echo entry
                    echo '(',$page['comment_time'],') <a href="',$this->getService(UrlFormatter::class)->href('', $page['tag'], 'show_comments=1'),'#',$page['comment_tag'],'">',$page['tag'],'</a> . . . . ' . _t('LAST_COMMENT') . ' ' . _t('BY') . ' ',$this->getService(MarkdownFormatterService::class)->format($page['comment_user']),"<br />\n";
                }
            } else {
                $curday = '';
                foreach ($pages as $page) {
                    // day header
                    list($day, $time) = explode(' ', $page['comment_time']);
                    if ($day != $curday) {
                        if ($curday) {
                            echo "<br />\n";
                        }
                        echo "<b>$day&nbsp;:</b><br />\n";
                        $curday = $day;
                    }

                    // echo entry
                    echo '&nbsp;&nbsp;&nbsp;(',$time,') <a href="',$this->getService(UrlFormatter::class)->href('', $page['tag'], 'show_comments=1'),'#',$page['comment_tag'],'">',$page['tag'],'</a> . . . . ' . _t('LAST_COMMENT') . ' ' . _t('BY') . ' ',$this->getService(MarkdownFormatterService::class)->format($page['comment_user']),"<br />\n";
                }
            }
        } else {
            echo '<i>' . _t('NO_RECENT_COMMENTS_ON_PAGES') . '.</i>';
        }
    }
}
