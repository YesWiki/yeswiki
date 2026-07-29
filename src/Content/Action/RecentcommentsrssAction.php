<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\CommentService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\LinkRenderer;

/**
 * `{{recentcommentsrss}}` -- converted from the procedural actions/recentcommentsrss.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class RecentcommentsrssAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'recentcommentsrss';
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
        if ($this->getService(PageContext::class)->getMethod() != 'xml') {
            echo _t('TO_OBTAIN_COMMENTS_RSS_FEED_TO_GO_THIS_ADDRESS') . ' : ';
            echo $this->getService(LinkRenderer::class)->link($this->getService(UrlFormatter::class)->href('xml'));

            return;
        }

        $max = 50;
        if ($user = $this->getService(AuthenticationService::class)->getLoggedUser()) {
            $max = $user['changescount'];
        }

        if (!($link = $this->getService(PerformableArguments::class)->get('link'))) {
            $link = $this->wiki->GetConfigValue('root_page');
        }

        $title = _t('LATEST_COMMENTS_ON') . ' ' . $this->wiki->GetConfigValue('yeswiki_name');
        $rssLink = $this->getService(UrlFormatter::class)->href('', $link);
        $rssDescription = _t('LATEST_COMMENTS_ON') . ' '
            . $this->wiki->GetConfigValue('yeswiki_name');

        $output = "<rss version=\"2.0\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\">
            <channel>
            <title>$title</title>
            <link>$rssLink</link>
            <description>$rssDescription</description>
            <language>fr</language>
            <generator>YesWiki " . YESWIKI_VERSION . '</generator>
        ';

        if ($comments = $this->getService(CommentService::class)->getRecentComments($max)) {
            foreach ($comments as $comment) {
                $output .= "<item>\n";
                $output .= '<title>' . htmlspecialchars($comment['comment_on'] . ' -- ' . $comment['user'], ENT_COMPAT, YW_CHARSET) . "</title>\n";
                $output .= '<dc:creator>' . htmlspecialchars($comment['user'], ENT_COMPAT, YW_CHARSET) . "</dc:creator>\n";
                $output .= '<pubDate>' . gmdate('D, d M Y H:i:s \G\M\T', strtotime($comment['time'])) . "</pubDate>\n";
                $output .= '<description>' . htmlspecialchars('<h3>Commentaire sur ' . $this->getService(LinkRenderer::class)->linkToPage($comment['comment_on'], ENT_COMPAT, YW_CHARSET) . '</h3>');
                $output .= '<pre>' . htmlspecialchars($comment['body'], ENT_COMPAT, YW_CHARSET) . "</pre> </description>\n";
                // notice for later: before introducing Format()ed comments, think to spam and recursive calls to {{recentcommentsrss}} (RegisterInclusion() etc.)
                $itemurl = $this->getService(UrlFormatter::class)->href('', $comment['comment_on'], 'show_comments=1') . '#' . htmlspecialchars(rawurlencode($comment['tag']), ENT_COMPAT, YW_CHARSET);
                $output .= '<link>' . $itemurl . "</link>\n";
                $permalink = $this->getService(UrlFormatter::class)->href(false, $comment['tag'], 'time=' . htmlspecialchars(rawurlencode($comment['time']), ENT_COMPAT, YW_CHARSET));
                $output .= '<guid>' . $permalink . "</guid>\n";
                $output .= "</item>\n";
            }
        }
        $output .= "</channel>\n";
        $output .= "</rss>\n";
        echo $output;
    }
}
