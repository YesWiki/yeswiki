<?php

/* recentchangesrss.php
 * Copyright 2003  David DELON
 * Copyright 2005-2007  Didier LOISEAU
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

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if ($this->GetMethod() != 'xml') {
    echo _t('TO_OBTAIN_RSS_FEED_TO_GO_THIS_ADDRESS').' : ';
    echo $this->Link($this->Href('xml'));
    return;
}

require_once 'includes/diff/rssdiff.function.php';

$max = 50;
if ($user = $this->GetUser()) {
    $max = $user["changescount"];
}

$pageTableName = $this->config["table_prefix"] . 'pages';
$sql = "SELECT id, tag, time, user, owner
    FROM $pageTableName
    WHERE comment_on = '' ORDER BY time desc limit $max";

$pages = $this->LoadAll($sql);
if (!$pages) {
    return;
}

if (!($link = $this->GetParameter("link"))) {
    $link = $this->GetConfigValue("root_page");
}

$xmlUrl = $this->Href("xml");
$wakkaName = htmlspecialchars(
    $this->GetConfigValue("wakka_name"),
    ENT_COMPAT,
    YW_CHARSET
);

$output = "<rss version=\"2.0\" "
    . "xmlns:dc=\"http://purl.org/dc/elements/1.1/\""
    . "xmlns:atom=\"http://www.w3.org/2005/Atom\">\n"
    . "<channel>\n"
    . "<atom:link href='$xmlUrl' rel='self' type='application/rss+xml' />\n"
    . "<title>$wakkaName</title>\n"
    . "<link>" . $this->Href(false, $link) . "</link>\n"
    . "<description>$wakkaName</description>\n"
    . "<language>fr</language>\n"
    . '<generator>WikiNi ' . WIKINI_VERSION . "</generator>\n";

for ($i = 0; $i < sizeof($pages); $i++) {
    $page = $pages[$i];
    $firstpage = $page;
    $lastpage = $page;
    $break_on_tag = $page['tag'];
    $break_on_user = $page['user'];

    while (($page['tag'] == $break_on_tag)
        and ($page['user'] == $break_on_user)
        and ($i < sizeof($pages))
    ) {
        $i++;
        $lastpage = $page;
        if ($i < sizeof($pages)) {
            $page = $pages[$i];
        }
    }

    if ($i < sizeof($pages)) {
        $page = $firstpage;
        $tag = htmlspecialchars($page["tag"], ENT_COMPAT, YW_CHARSET);
        $user = htmlspecialchars($page["user"], ENT_COMPAT, YW_CHARSET);
        $date = gmdate('D, d M Y H:i:s \G\M\T', strtotime($page['time']));
        $description = htmlspecialchars(
            'Modification de ' . $this->ComposeLinkToPage($page["tag"])
            . ' (' . $this->ComposeLinkToPage($page["tag"], 'revisions', 'historique') . ')'
            . " --- par $user"  . rssdiff($page["tag"], $firstpage["id"], $lastpage["id"])
            . "<item>\n"
            . "<title>$tag</title>\n"
            . "<dc:creator>$user</dc:creator>\n"
            . "<pubDate>$date</pubDate>\n"
            . "<description>$description</description>\n"
            . "<dc:format>text/html</dc:format>"
        );

        $itemurl = $this->href(
            false,
            $page["tag"],
            "time=" . htmlspecialchars(rawurlencode($page["time"]), ENT_COMPAT, YW_CHARSET)
        );
        $output .= '<guid>' . $itemurl . "</guid>\n";
        $output .= "</item>\n";
    }
}
$output .= "</channel>\n";
$output .= "</rss>\n";
echo $output ;
