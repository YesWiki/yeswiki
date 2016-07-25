<?php
/*
listpages.php

Copyright 2009  Florian SCHMITT
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
if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

include_once 'tools/tags/libs/tags.functions.php';
$nbcartrunc = 200;
$output = '';
$class = $this->GetParameter('class');
$pages = $this->GetParameter('pages');


if (empty($pages)) {
	$output .= '<div class="alert alert-danger"><strong>'._t('TAGS_ACTION_INCLUDEPAGES').'</strong> : '._t('TAGS_NO_PARAM_PAGES').'</div>'."\n";
}
else {
	$template = $this->GetParameter('template');
	if (empty($template)) $template = 'pages_list.tpl.html';

	$resultat = explode(',', $pages);
	foreach ($resultat as $page) {
		$page = $this->LoadPage(trim($page));
		$element[$page['tag']]['tagnames'] = '';
		$element[$page['tag']]['tagbadges'] = '';
		$element[$page['tag']]['body'] = $page['body'];
		$element[$page['tag']]['owner'] = $page['owner'];
		$element[$page['tag']]['user'] = $page['user'];
		$element[$page['tag']]['time'] = $page['time'];
		$element[$page['tag']]['title'] = get_title_from_body($page);
		$element[$page['tag']]['image'] = get_image_from_body($page);
		$element[$page['tag']]['desc'] = tokenTruncate(strip_tags($this->Format($page['body'])), $nbcartrunc);
		$pagetags = $this->GetAllTriplesValues($page['tag'], 'http://outils-reseaux.org/_vocabulary/tag', '', '');
		foreach ($pagetags as $tag) {
			$element[$page['tag']]['tagnames'] .= sanitizeEntity($tag['value']).' ';
			$element[$page['tag']]['tagbadges'] .= '<span class="label label-info">'.$tag['value'].'</span>&nbsp;';
		}
	}

	include_once 'tools/libs/squelettephp.class.php';
	$templateelements = new SquelettePhp('tools/tags/presentation/templates/'.$template);
	$templateelements->set(array('elements' => $element));
	$output .= $templateelements->analyser();
}

if (empty($class)) {
	echo $output."\n";
} 
else {
	echo '<div class="'.$class.'">'."\n".$output."\n".'</div>'."\n";
}

?>
