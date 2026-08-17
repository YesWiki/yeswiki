<?php

namespace YesWiki\Social\Action;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\LinkRenderer;
use YesWiki\Social\Service\CommentService;

/**
 * `{{recentcommentsrss}}` -- converted from the procedural actions/recentcommentsrss.php by ticket 06.
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
            $link = $this->getService(RuntimeConfig::class)->getValue('root_page');
        }

        $title = _t('LATEST_COMMENTS_ON') . ' ' . $this->getService(RuntimeConfig::class)->getValue('yeswiki_name');
        $rssLink = $this->getService(UrlFormatter::class)->href('', $link);
        $rssDescription = _t('LATEST_COMMENTS_ON') . ' '
            . $this->getService(RuntimeConfig::class)->getValue('yeswiki_name');

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
                $output .= '<title>' . htmlspecialchars($comment['parent'] . ' -- ' . $comment['user'], ENT_COMPAT, YW_CHARSET) . "</title>\n";
                $output .= '<dc:creator>' . htmlspecialchars($comment['user'], ENT_COMPAT, YW_CHARSET) . "</dc:creator>\n";
                $output .= '<pubDate>' . gmdate('D, d M Y H:i:s \G\M\T', strtotime($comment['time'])) . "</pubDate>\n";
                $output .= '<description>' . htmlspecialchars('<h3>Commentaire sur ' . $this->getService(LinkRenderer::class)->linkToPage($comment['parent'], ENT_COMPAT, YW_CHARSET) . '</h3>');

                $output .= '<pre>' . htmlspecialchars(PageBody::content(PageBody::decode($comment['body'])), ENT_COMPAT, YW_CHARSET) . "</pre> </description>\n";

                $itemurl = $this->getService(UrlFormatter::class)->href('', $comment['parent'], 'show_comments=1') . '#' . htmlspecialchars(rawurlencode($comment['tag']), ENT_COMPAT, YW_CHARSET);
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
