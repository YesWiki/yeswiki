<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\LinkRenderer;
use YesWiki\Render\Service\MarkdownFormatterService;

/**
 * `{{recentchangesrssplus}}` -- converted from the procedural actions/recentchangesrssplus.php by ticket 06.
 */
class RecentchangesrssplusAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'recentchangesrssplus';
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
        if ($user = $this->getService(AuthenticationService::class)->getLoggedUser()) {
            $max = $user['changescount'];
        } else {
            $max = 20;
        }

        if ($this->getService(PageContext::class)->getMethod() != 'xml') {
            echo _t('TO_OBTAIN_RSS_FEED_TO_GO_THIS_ADDRESS') . ' : ';
            echo $this->getService(LinkRenderer::class)->link($this->getService(PageContext::class)->getTag(), 'xml', null, $this->getService(UrlFormatter::class)->href('xml'));

            return;
        }

        $dbService = $this->getService(DbService::class);
        $userCol = $dbService->quoteIdentifier('user');

        if ($pages = $this->getService(DbService::class)->loadAll("select tag, time, $userCol, owner, body from " . $this->getService(RuntimeConfig::class)['table_prefix'] . "pages where latest = 'Y' and parent = '' order by time desc limit " . $max)) {
            if (!($link = $this->getService(PerformableArguments::class)->get('link'))) {
                $link = $this->getService(RuntimeConfig::class)['root_page'];
            }

            $output = "<?xml version=\"1.0\" encoding=\"iso-8859-1\"?> \n";
            $output .= "<rss version=\"0.91\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\">\n";

            $output .= "<channel>\n";
            $output .= '<title> ' . _t('LATEST_CHANGES_ON') . ' ' . $this->getService(RuntimeConfig::class)['yeswiki_name'] . "</title>\n";
            $output .= '<link>' . $this->getService(RuntimeConfig::class)['base_url'] . $link . "</link>\n";
            $output .= '<description> ' . _t('LATEST_CHANGES_ON') . ' ' . $this->getService(RuntimeConfig::class)['yeswiki_name'] . " </description>\n";

            $items = '';
            foreach ($pages as $i => $page) {
                $readAcl = $this->getService(AclService::class)->hasAccess('read', $page['tag']);
                $tag = $readAcl ? $page['tag'] : substr($page['tag'], 0, 3) . '___';

                list($day, $time) = explode(' ', $page['time']);
                $day = preg_replace('/-/', ' ', $day);
                list($hh, $mm, $ss) = explode(':', $time);

                $markup = mb_substr(PageBody::content(PageBody::decode($page['body'])), 0, 500);
                $body = $readAcl
                    ? htmlspecialchars($this->getService(MarkdownFormatterService::class)->format($markup), ENT_COMPAT, YW_CHARSET)
                    : '<br><div><i>' . _t('RSS_HIDDEN_CONTENT') . '</i></div>';

                $items .= "<item>\n";
                $items .= '<title>' . $tag . ' --- ' . _t('BY') . ' ' . $page['user'] . ' le ' . $day . ' - ' . $hh . ':' . $mm . "</title>\n";
                $items .= '<description> ' . _t('RSS_CHANGE_OF') . ' ' . $tag . ' --- ' . _t('BY') . ' ' . $page['user'] . ' ' . _t('RSS_ON_DATE') . ' ' . $day . ' - ' . $hh . ':' . $mm . $body . "</description>\n";
                $items .= '<dc:format>text/html</dc:format>';
                $items .= '<link>' . $this->getService(RuntimeConfig::class)['base_url'] . $tag . '&amp;time=' . rawurlencode($page['time']) . "</link>\n";
                $items .= "</item>\n";
            }

            $output .= $items . "\n";
            $output .= "</channel>\n";
            $output .= "</rss>\n";

            header('Content-Type: text/xml; charset=ISO-8859-1');
            echo $output;
            $this->getService(Redirector::class)->terminate();
        }
    }
}
