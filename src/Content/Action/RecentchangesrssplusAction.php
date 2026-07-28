<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

/**
 * `{{recentchangesrssplus}}` -- converted from the procedural actions/recentchangesrssplus.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
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
            $max = $user['changescount'];
        } else {
            $max = 20;
        }

        if ($this->wiki->GetMethod() != 'xml') {
            echo _t('TO_OBTAIN_RSS_FEED_TO_GO_THIS_ADDRESS') . ' : ';
            echo $this->wiki->Link($this->wiki->getPageTag(), 'xml', null, $this->wiki->Href('xml'));

            return;
        }

        $dbService = $this->wiki->services->get(\YesWiki\Kernel\Service\DbService::class);
        $userCol = $dbService->quoteIdentifier('user');
        $bodyExpr = ($dbService->getDriver() === 'sqlite') ? 'substr(body,1,500)' : 'LEFT(body,500)';
        if ($pages = $this->wiki->LoadAll("select tag, time, $userCol, owner, $bodyExpr as body from " . $this->wiki->config['table_prefix'] . "pages where latest = 'Y' and comment_on = '' order by time desc limit " . $max)) {
            if (!($link = $this->wiki->GetParameter('link'))) {
                $link = $this->wiki->config['root_page'];
            }

            $output = "<?xml version=\"1.0\" encoding=\"iso-8859-1\"?> \n";
            $output .= "<rss version=\"0.91\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\">\n";

            $output .= "<channel>\n";
            $output .= '<title> ' . _t('LATEST_CHANGES_ON') . ' ' . $this->wiki->config['yeswiki_name'] . "</title>\n";
            $output .= '<link>' . $this->wiki->config['base_url'] . $link . "</link>\n";
            $output .= '<description> ' . _t('LATEST_CHANGES_ON') . ' ' . $this->wiki->config['yeswiki_name'] . " </description>\n";

            $items = '';
            foreach ($pages as $i => $page) {
                $readAcl = $this->wiki->HasAccess('read', $page['tag']);
                $tag = $readAcl ? $page['tag'] : substr($page['tag'], 0, 3) . '___';

                list($day, $time) = explode(' ', $page['time']);
                $day = preg_replace('/-/', ' ', $day);
                list($hh, $mm, $ss) = explode(':', $time);

                $body = $readAcl
                    ? htmlspecialchars($this->wiki->Format($page['body'], 'wakka', $page['tag']), ENT_COMPAT, YW_CHARSET)
                    : '<br><div><i>' . _t('RSS_HIDDEN_CONTENT') . '</i></div>';

                $items .= "<item>\n";
                $items .= '<title>' . $tag . ' --- ' . _t('BY') . ' ' . $page['user'] . ' le ' . $day . ' - ' . $hh . ':' . $mm . "</title>\n";
                $items .= '<description> ' . _t('RSS_CHANGE_OF') . ' ' . $tag . ' --- ' . _t('BY') . ' ' . $page['user'] . ' ' . _t('RSS_ON_DATE') . ' ' . $day . ' - ' . $hh . ':' . $mm . $body . "</description>\n";
                $items .= '<dc:format>text/html</dc:format>';
                $items .= '<link>' . $this->wiki->config['base_url'] . $tag . '&amp;time=' . rawurlencode($page['time']) . "</link>\n";
                $items .= "</item>\n";
            }

            $output .= $items . "\n";
            $output .= "</channel>\n";
            $output .= "</rss>\n";

            // Définition du type de document et de son encodage.
            header('Content-Type: text/xml; charset=ISO-8859-1');
            echo $output;
            $this->wiki->exit();
        }
    }
}
