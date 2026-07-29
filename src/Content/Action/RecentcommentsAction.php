<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\CommentService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

/**
 * `{{recentcomments}}` -- converted from the procedural actions/recentcomments.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class RecentcommentsAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'recentcomments';
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
        // Which is the max number of comments to be shown ?
        if ($max = $this->wiki->GetParameter('max')) {
            if ($max == 'last') {
                $max = 50;
            } else {
                $last = (int)$max;
            }
        } else {
            $max = 50;
        }

        // Show recent comments
        if ($comments = $this->getService(CommentService::class)->getRecentComments($max)) {
            $curday = '';
            foreach ($comments as $comment) {
                // day header
                list($day, $time) = explode(' ', $comment['time']);
                if ($day != $curday) {
                    if ($curday) {
                        echo "<br />\n";
                    }
                    echo "<b>$day:</b><br />\n";
                    $curday = $day;
                }

                // echo entry
                echo '&nbsp;&nbsp;&nbsp;(',$comment['time'],') <a href="',$this->wiki->href('', $comment['comment_on'], 'show_comments=1'),'#',$comment['tag'],'">',$comment['comment_on'],'</a> . . . . ',$this->wiki->Format($comment['user']),"<br />\n";
            }
        } else {
            echo '<i>' . _t('NO_RECENT_COMMENTS') . '.</i>';
        }
    }
}
