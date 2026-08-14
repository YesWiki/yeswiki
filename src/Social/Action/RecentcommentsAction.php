<?php

namespace YesWiki\Social\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Social\Service\CommentService;

/**
 * `{{recentcomments}}` -- converted from the procedural actions/recentcomments.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class RecentcommentsAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'recentcomments';
    }

    public function components(): array
    {
        return [
            Component::for('recentcomments')
                ->category(Category::Lists)
                ->label(_t('AB_advanced_action_recentcomments_label'))
                ->icon('messages')
                ->previewHeight('200px')
                ->settings(
                    Setting::number('max')
                        ->label(_t('AB_advanced_action_recentcomments_max_label'))
                        ->default('')
                        ->min(1),
                ),
        ];
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
        if ($max = $this->getService(PerformableArguments::class)->get('max')) {
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
                echo '&nbsp;&nbsp;&nbsp;(',$comment['time'],') <a href="',$this->getService(UrlFormatter::class)->href('', $comment['parent'], 'show_comments=1'),'#',$comment['tag'],'">',$comment['parent'],'</a> . . . . ',$this->getService(MarkdownFormatterService::class)->format($comment['user']),"<br />\n";
            }
        } else {
            echo '<i>' . _t('NO_RECENT_COMMENTS') . '.</i>';
        }
    }
}
