<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

include_once 'tools/tags/libs/tags.functions.php';
$nbcartrunc = 200;

$elementwidth = $this->GetParameter('elementwidth');
if (empty($elementwidth)) $elementwidth = 300;

$elementoffset = $this->GetParameter('elementoffset');
if (empty($elementoffset)) $elementoffset = 10;

$template = $this->GetParameter('template');
if (empty($template) || !file_exists('tools/tags/presentation/templates/'.$template)) {
	$template = 'pages_grid_filter.tpl.html';
}

$params = get_filtertags_parameters_recursive();
if (!is_array($params) && strstr($params, 'alert-danger')) {
	echo $params;
	return;
}
$taglist = _convert($params['tags'], YW_CHARSET, TRUE);
unset($params['tags']);

// requete avec toutes les pages contenants les mots cles
$req = "SELECT DISTINCT tag, time, user, owner, body 
FROM ".$this->config['table_prefix']."pages, ".$this->config['table_prefix']."triples tags
WHERE latest = 'Y' AND comment_on = '' AND tags.value IN (".$taglist.") AND tags.property = \"http://outils-reseaux.org/_vocabulary/tag\" AND tags.resource = tag AND tag NOT IN (\"".implode('","', $this->GetAllInclusions())."\") ORDER BY tag ASC";
$pages = $this->LoadAll($req);

echo '<div class="well well-sm no-dblclick controls">'."\n".'<div class="pull-right muted"><span class="nbfilteredelements">'.count($pages).'</span> '._t('TAGS_RESULTS').'</div>';
foreach ($params as $param) {
  	echo '<div class="filter-group '.$param['class'].'" data-type="'.$param['toggle'].'">'."\n".$param['title']."\n".'<div class="btn-group filter-tags">'."\n";
 	foreach ($param['arraytags'] as $tagname) {
 		if ($tagname == "alaligne") { 
 			echo '<br />'."\n";
 		} 
 		else {
 			echo '<button type="button" class="btn btn-default filter" data-filter="'.sanitizeEntity(_convert($tagname, YW_CHARSET, TRUE)).'">'.$tagname.'</button>'."\n";
 		}	
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
	$this->RegisterInclusion($page['tag']);
	$element[$page['tag']]['desc'] = tokenTruncate(strip_tags($this->Format($page['body'])), $nbcartrunc);
	$this->UnregisterLastInclusion();
	$pagetags = $this->GetAllTriplesValues($page['tag'], 'http://outils-reseaux.org/_vocabulary/tag', '', '');
	foreach ($pagetags as $tag) {
		$tag['value'] = _convert(stripslashes($tag['value']), 'ISO-8859-1');
		$element[$page['tag']]['tagnames'] .= sanitizeEntity($tag['value']).' ';
		$element[$page['tag']]['tagbadges'] .= '<span class="tag-label label label-primary">'.$tag['value'].'</span>&nbsp;';
	}
}

include_once 'tools/libs/squelettephp.class.php';
$templateelements = new SquelettePhp('tools/tags/presentation/templates/'.$template);
$templateelements->set(array('elements' => $element, 'elementwidth' => $elementwidth, 'elementoffset' => $elementoffset));
echo $templateelements->analyser();

// ajout du javascript gerant le filtrage
$this->AddJavascriptFile('tools/tags/libs/vendor/imagesloaded.pkgd.min.js');
$this->AddJavascriptFile('tools/tags/libs/vendor/jquery.wookmark.min.js');
$this->AddJavascriptFile('tools/tags/libs/filtertags.js');

?>
