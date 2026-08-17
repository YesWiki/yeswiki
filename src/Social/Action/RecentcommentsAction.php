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

/** `{{recentcomments}}` -- converted from the procedural actions/recentcomments.php by ticket 06. */
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
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        if ($max = $this->getService(PerformableArguments::class)->get('max')) {
            if ($max == 'last') {
                $max = 50;
            } else {
                $last = (int)$max;
            }
        } else {
            $max = 50;
        }

        if ($comments = $this->getService(CommentService::class)->getRecentComments($max)) {
            $curday = '';
            foreach ($comments as $comment) {
                list($day, $time) = explode(' ', $comment['time']);
                if ($day != $curday) {
                    if ($curday) {
                        echo "<br />\n";
                    }
                    echo "<b>$day:</b><br />\n";
                    $curday = $day;
                }

                echo '&nbsp;&nbsp;&nbsp;(',$comment['time'],') <a href="',$this->getService(UrlFormatter::class)->href('', $comment['parent'], 'show_comments=1'),'#',$comment['tag'],'">',$comment['parent'],'</a> . . . . ',$this->getService(MarkdownFormatterService::class)->format($comment['user']),"<br />\n";
            }
        } else {
            echo '<i>' . _t('NO_RECENT_COMMENTS') . '.</i>';
        }
    }
}
