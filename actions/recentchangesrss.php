<?php
/*
recentchangesrss.php

Copyright 2003  David DELON
Copyright 2005-2007  Didier LOISEAU
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if ($this->GetMethod() != 'xml')
{
	echo _t('TO_OBTAIN_RSS_FEED_TO_GO_THIS_ADDRESS').' : ';
	echo $this->Link($this->Href('xml'));
	return;
}

if ($user = $this->GetUser())
{
	$max = $user["changescount"];
}
else
{
	$max = 50;
}

if ($pages = $this->LoadRecentlyChanged($max))
{
	if (!($link = $this->GetParameter("link"))) $link=$this->GetConfigValue("root_page");
	$output = '<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n";
	$output .= "<channel>\n";
	$output .= "<title> "._t('LATEST_CHANGES_ON')." ". $this->GetConfigValue("wakka_name")  . "</title>\n";
	$output .= "<link>" . $this->Href(false, $link) . "</link>\n";
	$output .= "<description> "._t('LATEST_CHANGES_ON')." " . htmlspecialchars($this->GetConfigValue("wakka_name"), ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET) . " </description>\n";
	$output .= "<language>fr</language>\n";
	$output .= '<generator>YesWiki ' . YESWIKI_VERSION . "</generator>\n";
	foreach ($pages as $i => $page)
	{
		$output .= "<item>\n";
		$output .= "<title>" . htmlspecialchars($page["tag"], ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET) . "</title>\n";
		$output .= '<dc:creator>' . htmlspecialchars($page["user"], ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET) . "</dc:creator>\n";
		$output .= '<pubDate>' . gmdate('D, d M Y H:i:s \G\M\T', strtotime($page['time'])) . "</pubDate>\n";
		$output .= "<description>" . htmlspecialchars(
				'Modification de ' . $this->ComposeLinkToPage($page["tag"])
				. ' (' . $this->ComposeLinkToPage($page["tag"], 'revisions', 'historique') . ')'
				. " --- "._t('BY')." " .$page["user"])
				. "</description>\n";
		$itemurl = $this->href(false, $page["tag"], "time=" . htmlspecialchars(rawurlencode($page["time"]), ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET));
		$output .= '<guid>' . $itemurl . "</guid>\n";
		$output .= "</item>\n";
	}
	$output .= "</channel>\n";
	$output .= "</rss>\n";
	echo $output ;
}
?>
