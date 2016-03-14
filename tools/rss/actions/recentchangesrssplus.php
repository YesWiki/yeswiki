<?php
/*
recentchangesrss.php

Copyright 2003  David DELON
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

if ($user = $this->GetUser())
{
	$max = $user["changescount"];
}
else
{
	$max = 20;
}


if ($this->GetMethod() != 'xml')
{
	echo 'Pour obtenir le fil RSS des derniers changements, utilisez l\'adresse suivante: ';
	echo $this->Link($this->Href('xml'));
	return;
}

if ($pages = $this->LoadAll("select tag, time, user, owner, LEFT(body,500) as body from ".$this->config["table_prefix"]."pages where latest = 'Y' and comment_on = '' order by time desc limit ".$max  ))  {
	
	if (!($link = $this->GetParameter("link"))) $link=$this->config["root_page"];
	
    	$output = "<?xml version=\"1.0\" encoding=\"iso-8859-1\"?> \n";
		$output .= "<rss version=\"0.91\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\">\n";
		 
	$output .= "<channel>\n";
	$output .= "<title> Derniers changements sur ". $this->config["wakka_name"]  . "</title>\n";
	$output .= "<link>" . $this->config["base_url"] . $link . "</link>\n";
	$output .= "<description> Derniers changements sur " . $this->config["wakka_name"] . " </description>\n";


	$items = '';
	foreach ($pages as $i => $page)
	{
		list($day, $time) = explode(" ", $page["time"]);
		$day= preg_replace("/-/", " ", $day);
		list($hh,$mm,$ss) = explode(":", $time);
		
		
		$items .= "<item>\n";
		$items .= "<title>" . $page["tag"] . " --- par " .$page["user"] . " le " . $day ." - ". $hh .":". $mm . "</title>\n";
		$items .= "<description> Modification de " . $page["tag"] . " --- par " .$page["user"] . " le " . $day ." - ". $hh .":". $mm . htmlspecialchars($this->Format($page['body']), ENT_COMPAT, YW_CHARSET). "</description>\n";
		$items .= "<dc:format>text/html</dc:format>";
		$items .= "<link>" . $this->config["base_url"] . $page["tag"] . "&amp;time=" . rawurlencode($page["time"]) . "</link>\n";
		$items .= "</item>\n";
	}
	
	$output .= $items . "\n";
    $output .= "</channel>\n";
    $output .= "</rss>\n";

	// Définition du type de document et de son encodage.
	header("Content-Type: text/xml; charset=ISO-8859-1");
	echo $output;
	exit;
		
	
}
?>
