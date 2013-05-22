<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

include_once 'tools/tags/libs/tags.functions.php';
$nbcartrunc = 200;

$template = $this->GetParameter('template');
if (empty($template) || !file_exists('tools/tags/presentation/templates/'.$template)) {
	$template = 'pages_grid.tpl.html';
}

$params = get_filtertags_parameters_recursive();
$taglist = $params['tags'];
unset($params['tags']);
echo '<div class="filter-buttons well no-dblclick">'."\n".'<div class="pull-right muted"><span class="nbfilteredelements"></span> '.TAGS_RESULTS.'</div>';
foreach ($params as $param) {
 	echo '<div class="'.$param['class'].'" data-type="'.$param['toggle'].'">'."\n".$param['title']."\n".'<div class="btn-group filter-tags">'."\n";
	foreach ($param['arraytags'] as $tagname) {
		echo '<button type="button" class="btn btn-filter-tag" data-filter="'.sanitizeEntity($tagname).'">'.$tagname.'</button>'."\n";
	}
	echo  '</div>'."\n".'</div>'."\n";
 } 
 echo '</div>'."\n";

// requete avec toutes les pages contenants
$req = "SELECT DISTINCT tag, time, user, owner, body 
FROM ".$this->config['table_prefix']."pages, ".$this->config['table_prefix']."triples tags
WHERE latest = 'Y' AND comment_on = '' AND tags.value IN (".$taglist.") AND tags.property = \"http://outils-reseaux.org/_vocabulary/tag\" AND tags.resource = tag ORDER BY tag ASC";
$pages = $this->LoadAll($req);

$element = array();

// affichage des resultats
foreach ($pages as $page) {
	$element[$page['tag']]['tagnames'] = '';
	$element[$page['tag']]['tagbadges'] = '';
	$element[$page['tag']]['body'] = $page['body'];
	$element[$page['tag']]['owner'] = $page['owner'];
	$element[$page['tag']]['user'] = $page['user'];
	$element[$page['tag']]['time'] = $page['time'];
	// on recupere les bf_titre ou les titres de niveau 1 et de niveau 2, on met la PageWiki sinon
	preg_match_all("/\"bf_titre\":\"(.*)\"/U", $page['body'], $titles);
	if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0]!='') {
		$page['title'] = utf8_decode(preg_replace("/\\\\u([a-f0-9]{4})/e", "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))", $titles[1][0]));
	} 
	else {
		preg_match_all("/\={6}(.*)\={6}/U", $page['body'], $titles);
		if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0]!='') {
			$page['title'] = $this->Format(trim($titles[1][0]));
		}
		else {
			preg_match_all("/={5}(.*)={5}/U", $page['body'], $titles);
			if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0]!='') {
				$page['title'] = $this->Format(trim($titles[1][0]));
			}
			else {
				$page['title'] = $page['tag'];
			}
		}
	}
	// on cherche les actions attach avec image, puis les images bazar
	preg_match_all("/\{\{attach.*file=\".*\.(?i)(jpg|png|gif|bmp).*\}\}/U", $page['body'], $images);
	if (is_array($images[0]) && isset($images[0][0]) && $images[0][0]!='') {
		preg_match_all("/.*file=\"(.*\.(?i)(jpg|png|gif|bmp))\".*desc=\"(.*)\".*\}\}/U", $images[0][0], $attachimg);
		$page['image'] = afficher_image_attach($page['tag'], $attachimg[1][0], $attachimg[3][0], 'filtered-image', 400, 400) ;
	}
	else {
		preg_match_all("/\"imagebf_image\":\"(.*)\"/U", $page['body'], $image);
		if (is_array($image[1]) && isset($image[1][0]) && $image[1][0]!='') {
			$imagefile = utf8_decode(preg_replace("/\\\\u([a-f0-9]{4})/e", "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))", $image[1][0]));
			$page['image'] =  afficher_image($imagefile, $imagefile, 'filtered-image', '', '', 400, 400);
		} else {
			$page['image'] = '';
		}	
	}
	$element[$page['tag']]['title'] = $page['title'];
	$element[$page['tag']]['image'] = $page['image'];
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

echo '<div class="tags-no-results-message alert alert-info" style="display:none">'."\n".TAGS_NO_RESULTS."\n".'</div>'."\n";

// ajout du javascript gerant le filtrage
$GLOBALS['js'] = (isset($GLOBALS['js']) ? $GLOBALS['js'] : '').'  <script src="tools/tags/libs/vendor/jquery.wookmark.min.js"></script>
  <script src="tools/tags/libs/filtertags.js"></script>'."\n";

?>
