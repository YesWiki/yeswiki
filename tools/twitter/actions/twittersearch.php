<?php
/*
twittersearch.php
Copyright 2012  Florian Schmitt <florian@outils-reseaux.org>
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/


if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}

$query = $this->GetParameter('query');
// si pas de user, on affiche une erreur
if (empty($query)) {
	echo ("<div class=\"error_box\">ERREUR action twittersearch : pas de recherche saisie (param&egrave;tre query=\"\" manquant).</div>");
}
else { 
	$limit = $this->GetParameter('limit');
	if (empty($limit)) $limit = 5;
	$feed = 'http://search.twitter.com/search.rss?q='.urlencode($query).'&count='.urlencode($limit);
    $tweets = file_get_contents($feed);
    
	$tweets = str_replace("&", "&", $tweets);	
	$tweets = str_replace("<", "<", $tweets);
	$tweets = str_replace(">", ">", $tweets);
	$tweet = explode("<item>", $tweets);
    $tcount = count($tweet) - 1;

	for ($i = 1; $i <= $tcount; $i++) {
	    $endtweet = explode("</item>", $tweet[$i]);
	    $title = explode("<title>", $endtweet[0]);
	    $content = explode("</title>", $title[1]);
		$content[0] = str_replace("&#8211;", "&mdash;", $content[0]);
		$content[0] = preg_replace("/(http:\/\/|(www\.))(([^\s<]{4,68})[^\s<]*)/", '<a href="http://$2$3" target="_blank">$1$2$4</a>', $content[0]);
		$content[0] = str_replace("$username: ", "", $content[0]);
		$content[0] = preg_replace("/@(\w+)/", "<a href=\"http://www.twitter.com/\\1\" target=\"_blank\">@\\1</a>", $content[0]);
		$content[0] = html_entity_decode($content[0]);
		/*$content[0] = preg_replace("/#(\w+)/", "<a href=\"http://search.twitter.com/search?q=\\1\" target=\"_blank\">#\\1</a>", $content[0]);*/
		$content[0] = preg_replace(
    '/\s+#(\w+)/',
    ' <a href="http://search.twitter.com/search?q=%23$1">#$1</a>',
    $content[0]);
	    $mytweets[] = $content[0];
	}
	$list='';
	while (list(, $v) = each($mytweets)) {
		$list .= "<li>".html_entity_decode($v)."</li>\n";
	}
	if ($list!='') echo '<ul class="tweet_list">'."\n".$list."\n".'</ul>'."\n";
}

?>
