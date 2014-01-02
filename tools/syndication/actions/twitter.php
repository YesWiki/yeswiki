<?php
/*
twitter.php
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

$username = $this->GetParameter('user');
// si pas de user, on affiche une erreur
if (empty($username)) {
	echo ("<div class=\"alert alert-danger\"><strong>"._t('SYNDICATION_ACTION_TWITTER')."</strong> : "._t('SYNDICATION_PARAM_USER_REQUIRED').".</div>");
}
else { 
	 //on inclue Magpie le parser RSS
	if (!defined('MAGPIE_OUTPUT_ENCODING')) define('MAGPIE_OUTPUT_ENCODING', 'UTF-8');
	if (!defined('MAGPIE_DIR')) define('MAGPIE_DIR', 'tools/syndication/libs/');
	require_once(MAGPIE_DIR.'rss_fetch.inc');

	//pour cacher les erreurs Warning de Magpie
	error_reporting(0);
    
	// gestion de nb de tweets
	$limit = $this->GetParameter('limit');
	if (empty($limit)) $limit = 5;
	$feed = 'http://twitter.com/statuses/user_timeline.rss?screen_name='.$username.'&count='.$limit;
	echo $feed;
    $rssfeed = fetch_rss( $feed );

	$class = $this->GetParameter("class");

	$template = $this->GetParameter("template");
	if (empty($template)) {
		$template = 'tools/syndication/templates/twitter.tpl.html';
	} else {
		$template = 'tools/syndication/templates/'.$this->GetParameter("template");
		if (!file_exists($template)) {
				echo 'Le fichier template: "'.$template.'" n\'existe pas, on utilise le template twitter.tpl.html par d&eacute;faut.';
				$template = 'tools/syndication/templates/twitter.tpl.html';
		}
	}

	$tweets = array();
	if ($rssfeed) {									
		// Gestion du nombre de pages syndiquees
		$i = 0;
		foreach ($rssfeed->items as $item) {	
			$i++;
			$tweets[$i]['title'] = str_replace("&#8211;", "&mdash;", $item['title']);
			$tweets[$i]['title'] = preg_replace("/(http:\/\/|(www\.))(([^\s<]{4,68})[^\s<]*)/", '<a href="http://$2$3" target="_blank">$1$2$4</a>', $tweets[$i]['title']);
			$tweets[$i]['title'] = str_replace("$username: ", "", $tweets[$i]['title']);
			$tweets[$i]['title'] = preg_replace("/@(\w+)/", "<a href=\"https://twitter.com/#!/\\1\" target=\"_blank\">@\\1</a>", $tweets[$i]['title']);
			$tweets[$i]['title'] = html_entity_decode($tweets[$i]['title']);
			$tweets[$i]['title'] = utf8_decode(preg_replace('/\s+#(\w+)/', ' <a href="http://search.twitter.com/search?q=%23$1">#$1</a>', $tweets[$i]['title']));
			$tweets[$i]['username'] = '';
			include_once('tools/syndication/libs/syndication.lib.php');
			$tweets[$i]['date'] = getRelativeDate($item['pubdate']);
		}
		include($template);
	} else {
		echo '<p class="alert alert-danger">'._t('ERROR').' : '.magpie_error().'</p>'."\n";        			    
	}	
}

?>