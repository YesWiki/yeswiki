<?php

if ($user = $this->GetUser()) {
    $max = $user['changescount'];
} else {
    $max = 20;
}

if ($this->GetMethod() != 'xml') {
    echo _t('TO_OBTAIN_RSS_FEED_TO_GO_THIS_ADDRESS') . ' : ';
    echo $this->Link($this->getPageTag(), 'xml', null, $this->Href('xml'));

    return;
}

$dbService = $this->services->get(\YesWiki\Core\Service\DbService::class);
$userCol = $dbService->quoteIdentifier('user');
$bodyExpr = ($dbService->getDriver() === 'sqlite') ? 'substr(body,1,500)' : 'LEFT(body,500)';
if ($pages = $this->LoadAll("select tag, time, $userCol, owner, $bodyExpr as body from " . $this->config['table_prefix'] . "pages where latest = 'Y' and comment_on = '' order by time desc limit " . $max)) {
    if (!($link = $this->GetParameter('link'))) {
        $link = $this->config['root_page'];
    }

    $output = "<?xml version=\"1.0\" encoding=\"iso-8859-1\"?> \n";
    $output .= "<rss version=\"0.91\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\">\n";

    $output .= "<channel>\n";
    $output .= '<title> ' . _t('LATEST_CHANGES_ON') . ' ' . $this->config['yeswiki_name'] . "</title>\n";
    $output .= '<link>' . $this->config['base_url'] . $link . "</link>\n";
    $output .= '<description> ' . _t('LATEST_CHANGES_ON') . ' ' . $this->config['yeswiki_name'] . " </description>\n";

    $items = '';
    foreach ($pages as $i => $page) {
        $readAcl = $this->HasAccess('read', $page['tag']);
        $tag = $readAcl ? $page['tag'] : substr($page['tag'], 0, 3) . '___';

        list($day, $time) = explode(' ', $page['time']);
        $day = preg_replace('/-/', ' ', $day);
        list($hh, $mm, $ss) = explode(':', $time);

        $body = $readAcl
            ? htmlspecialchars($this->Format($page['body'], 'wakka', $page['tag']), ENT_COMPAT, YW_CHARSET)
            : '<br><div><i>' . _t('RSS_HIDDEN_CONTENT') . '</i></div>';

        $items .= "<item>\n";
        $items .= '<title>' . $tag . ' --- ' . _t('BY') . ' ' . $page['user'] . ' le ' . $day . ' - ' . $hh . ':' . $mm . "</title>\n";
        $items .= '<description> ' . _t('RSS_CHANGE_OF') . ' ' . $tag . ' --- ' . _t('BY') . ' ' . $page['user'] . ' ' . _t('RSS_ON_DATE') . ' ' . $day . ' - ' . $hh . ':' . $mm . $body . "</description>\n";
        $items .= '<dc:format>text/html</dc:format>';
        $items .= '<link>' . $this->config['base_url'] . $tag . '&amp;time=' . rawurlencode($page['time']) . "</link>\n";
        $items .= "</item>\n";
    }

    $output .= $items . "\n";
    $output .= "</channel>\n";
    $output .= "</rss>\n";

    // Définition du type de document et de son encodage.
    header('Content-Type: text/xml; charset=ISO-8859-1');
    echo $output;
    $this->exit();
}
