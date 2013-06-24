<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

include_once 'tools/tags/libs/tags.functions.php';
$nbcartrunc = 200;

$template = $this->GetParameter('template');
if (empty($template) || !file_exists('tools/tags/presentation/templates/'.$template)) {
	$template = 'filter_grid.tpl.html';
}

$params = get_filtertags_parameters_recursive();
$taglist = $params['tags'];
unset($params['tags']);

// requete avec toutes les pages contenants
$req = "SELECT DISTINCT tag, time, user, owner, body 
FROM ".$this->config['table_prefix']."pages, ".$this->config['table_prefix']."triples tags
WHERE latest = 'Y' AND comment_on = '' AND tags.value IN (".$taglist.") AND tags.property = \"http://outils-reseaux.org/_vocabulary/tag\" AND tags.resource = tag ORDER BY tag ASC";
$pages = $this->LoadAll($req);

echo '<div class="well no-dblclick controls">'."\n".'<div class="pull-right muted"><span class="nbfilteredelements">'.count($pages).'</span> '.TAGS_RESULTS.'</div>';
foreach ($params as $param) {
  	echo '<div class="filter-group '.$param['class'].'" data-type="'.$param['toggle'].'">'."\n".$param['title']."\n".'<div class="btn-group filter-tags">'."\n";
 	foreach ($param['arraytags'] as $tagname) {
 		echo '<button type="button" class="btn filter" data-filter="'.sanitizeEntity($tagname).'">'.$tagname.'</button>'."\n";
 	}
 	echo  '</div>'."\n".'</div>'."\n";
} 
echo '</div>';

$element = array();
// affichage des resultats
foreach ($pages as $page) {
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

include_once 'tools/tags/libs/squelettephp.class.php';
$templateelements = new SquelettePhp('tools/tags/presentation/templates/'.$template);
$templateelements->set(array('elements' => $element));
echo $templateelements->analyser();

// ajout du javascript gerant le filtrage
$GLOBALS['js'] = (isset($GLOBALS['js']) ? $GLOBALS['js'] : '')."\n".
'	<script src="tools/tags/libs/vendor/jquery.mixitup.min.js"></script>
	<script src="tools/tags/libs/vendor/jquery.wookmark.min.js"></script>
	<script src="tools/tags/libs/filtertags.js"></script>'."\n";

?>
