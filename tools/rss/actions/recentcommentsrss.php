<?php
/*
 * recentcommentsrss.php
 *
 * Copyright 2003        David DELON
 * Copyright 2004        MattRixx
 * Copyright 2005-2007    Didier LOISEAU
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

if ($this->GetMethod() != 'xml') {
    echo _t('TO_OBTAIN_COMMENTS_RSS_FEED_TO_GO_THIS_ADDRESS').' : ';
    echo $this->Link($this->Href('xml'));
    return;
}

$max = 50;
if ($user = $this->GetUser()) {
    $max = $user["changescount"];
}

if (!($link = $this->GetParameter("link"))) {
    $link = $this->GetConfigValue("root_page");
}

$title = _t('LATEST_COMMENTS_ON')." ". $this->GetConfigValue("wakka_name");
$rssLink = $this->Href('', $link);
$rssDescription = _t('LATEST_COMMENTS_ON') . " "
    . $this->GetConfigValue("wakka_name");

$output = "<rss version=\"2.0\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\">
    <channel>
    <title>$title</title>
    <link>$rssLink</link>
    <description>$rssDescription</description>
    <language>fr</language>
    <generator>YesWiki " . YESWIKI_VERSION . "</generator>
";

if ($comments = $this->LoadRecentComments($max)) {
    foreach ($comments as $comment) {
        $output .= "<item>\n";
        $output .= "<title>" . htmlspecialchars($comment['comment_on'] . ' -- ' . $comment["user"], ENT_COMPAT, YW_CHARSET) . "</title>\n";
        $output .= '<dc:creator>' . htmlspecialchars($comment["user"], ENT_COMPAT, YW_CHARSET) . "</dc:creator>\n";
        $output .= '<pubDate>' . gmdate('D, d M Y H:i:s \G\M\T', strtotime($comment['time'])) . "</pubDate>\n";
        $output .= "<description>" . htmlspecialchars('<h3>Commentaire sur ' . $this->ComposeLinkToPage($comment["comment_on"], ENT_COMPAT, YW_CHARSET) . '</h3>');
        $output .= '<pre>' . htmlspecialchars($comment['body'], ENT_COMPAT, YW_CHARSET) . "</pre> </description>\n";
        // notice for later: before introducing Format()ed comments, think to spam and recursive calls to {{recentcommentsrss}} (RegisterInclusion() etc.)
        $itemurl = $this->Href('', $comment['comment_on'], 'show_comments=1') . '#' . htmlspecialchars(rawurlencode($comment["tag"]), ENT_COMPAT, YW_CHARSET);
        $output .= "<link>" . $itemurl . "</link>\n";
        $permalink = $this->href(false, $comment["tag"], "time=" . htmlspecialchars(rawurlencode($comment["time"]), ENT_COMPAT, YW_CHARSET));
        $output .= '<guid>' . $permalink . "</guid>\n";
        $output .= "</item>\n";
    }
}
$output .= "</channel>\n";
$output .= "</rss>\n";
echo $output ;
