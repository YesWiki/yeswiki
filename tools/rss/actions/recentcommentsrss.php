<?php

if ($this->GetMethod() != 'xml') {
    echo _t('TO_OBTAIN_COMMENTS_RSS_FEED_TO_GO_THIS_ADDRESS') . ' : ';
    echo $this->Link($this->Href('xml'));

    return;
}

$max = 50;
if ($user = $this->GetUser()) {
    $max = $user['changescount'];
}

if (!($link = $this->GetParameter('link'))) {
    $link = $this->GetConfigValue('root_page');
}

$title = _t('LATEST_COMMENTS_ON') . ' ' . $this->GetConfigValue('wakka_name');
$rssLink = $this->Href('', $link);
$rssDescription = _t('LATEST_COMMENTS_ON') . ' '
    . $this->GetConfigValue('wakka_name');

$output = "<rss version=\"2.0\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\">
    <channel>
    <title>$title</title>
    <link>$rssLink</link>
    <description>$rssDescription</description>
    <language>" . $this->defaultLanguage() . '</language>
    <generator>YesWiki ' . YESWIKI_VERSION . '</generator>
';

if ($comments = $this->LoadRecentComments($max)) {
    foreach ($comments as $comment) {
        $output .= "<item>\n";
        $output .= '<title>' . htmlspecialchars($comment['comment_on'] . ' -- ' . $comment['user'], ENT_COMPAT, YW_CHARSET) . "</title>\n";
        $output .= '<dc:creator>' . htmlspecialchars($comment['user'], ENT_COMPAT, YW_CHARSET) . "</dc:creator>\n";
        $output .= '<pubDate>' . gmdate('D, d M Y H:i:s \G\M\T', strtotime($comment['time'])) . "</pubDate>\n";
        $output .= '<description>' . htmlspecialchars('<h3>' . _t('COMMENT_ON') . ' ' . $this->ComposeLinkToPage($comment['comment_on']) . '</h3>', ENT_COMPAT, YW_CHARSET);
        $output .= '<pre>' . htmlspecialchars($comment['body'], ENT_COMPAT, YW_CHARSET) . "</pre> </description>\n";
        $itemurl = $this->Href('', $comment['comment_on'], 'show_comments=1') . '#' . htmlspecialchars(rawurlencode($comment['tag']), ENT_COMPAT, YW_CHARSET);
        $output .= '<link>' . $itemurl . "</link>\n";
        $permalink = $this->href(false, $comment['tag'], 'time=' . htmlspecialchars(rawurlencode($comment['time']), ENT_COMPAT, YW_CHARSET));
        $output .= '<guid>' . $permalink . "</guid>\n";
        $output .= "</item>\n";
    }
}
$output .= "</channel>\n";
$output .= "</rss>\n";
echo $output;
