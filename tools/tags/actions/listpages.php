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

$tags = $this->GetParameter('tags');
$type = $this->GetParameter('type');
// recuperation de tous les parametres
$lienedit = '';
$class = $this->GetParameter('class');
$nb = $this->GetParameter('nb');
$tri = $this->GetParameter('tri');
$template = $this->GetParameter('template');
if (empty($template)) $template = 'pages_grid.tpl.html';

$output = '';
/*
// creation de la liste des mots cles a filtrer
$GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '').'<script src="tools/tags/libs/tag.js"></script>';

$tab_selected_tags = explode(',',$tags);
$selectiontags = ' AND value IN ("'.implode(",",$tab_selected_tags).'")';

// on recupere tous les tags existants
$sql = 'SELECT DISTINCT value FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag" ORDER BY value ASC';
$tab_tous_les_tags = $this->LoadAll($sql);

if (is_array($tab_tous_les_tags))
{	
	foreach ($tab_tous_les_tags as $tag)
	{
		if (in_array($tag['value'], $tab_selected_tags)) {
			$additionnal_class = ' selectedtag';
			$taglist = $tag['value'];
			// suppression ergonomie david delon
			//$tab_reduced = $tab_selected_tags;
			//unset($tab_reduced[array_search($tag['value'], $tab_reduced)]);
			//$taglist = implode(',',$tab_reduced);
		} else {
			$additionnal_class = '';
			// suppression ergonomie david delon
			//$taglist = ($tags=='') ? $tag['value'] : $tags.','.$tag['value'] ;
			$taglist = $tag['value'];
		}
		$texte_liste  = '<li class="tagit-tag ui-widget-content ui-state-default ui-corner-all'.$additionnal_class;
		$texte_liste .= '">'."\n";
		$texte_liste .= '<a href="'.$this->href('listpages',$this->GetPageTag(),'tags='.$taglist).'">'.$tag['value'].'</a>'."\n";
		$texte_liste .= '</li>'."\n";
		$tab_tag[] = $texte_liste;
	}

	$outputselecttag = '';
	if (is_array($tab_tag))
	{
		$outputselecttag .= '<div class="filter_tags"><strong>Filtrer :</strong>'.
		//<strong>Filtrer les r&eacute;sultats en cochant / d&eacute;cochant les mots cl&eacute;s ci-dessous :</strong>
		'<ul  class="tagit ui-widget ui-widget-content ui-corner-all show">'."\n";
		foreach ($tab_tag as $tag) {
			$outputselecttag .= $tag;
		}
		$outputselecttag .= '</ul>'."\n".'</div>'."\n";
	}
}

*/

$text = '';
// affiche le resultat de la recherche
$resultat = $this->PageList($tags,$type,$nb,$tri,$template,$class,$lienedit);
if ($resultat) {
	$nb_total = count($resultat);
	// affichage des resultats
	foreach ($resultat as $page) {
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
			$page['image'] = afficher_image_attach($page['tag'], $attachimg[1][0], $attachimg[3][0], 'filtered-image', 300, 225) ;
		}
		else {
			preg_match_all("/\"imagebf_image\":\"(.*)\"/U", $page['body'], $image);
			if (is_array($image[1]) && isset($image[1][0]) && $image[1][0]!='') {
				$imagefile = utf8_decode(preg_replace("/\\\\u([a-f0-9]{4})/e", "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))", $image[1][0]));
				$page['image'] =  afficher_image($imagefile, $imagefile, 'filtered-image', '', '', 300, 225);
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

	// ajout du javascript gerant le filtrage
/*	$GLOBALS['js'] = (isset($GLOBALS['js']) ? $GLOBALS['js'] : '')."\n".
	'	<script src="tools/tags/libs/vendor/jquery.mixitup.min.js"></script>
		<script src="tools/tags/libs/vendor/jquery.wookmark.min.js"></script>
		<script src="tools/tags/libs/filtertags.js"></script>'."\n";
		*/






	/*
	foreach ($resultat as $microblogpost)
	{
	    if (!file_exists('tools/tags/presentation/templates/'.$template)) 
		{
			exit('Le fichier template du formulaire de microblog "tools/tags/presentation/templates/'.$template.'" n\'existe pas. Il doit exister...');
		}
		else
		{
			include_once('tools/tags/libs/squelettephp.class.php');
			$valtemplate=array();
			$squel = new SquelettePhp('tools/tags/presentation/templates/'.$template);
			$valtemplate['class'] = $class;
			$valtemplate['lien'] = $this->href('',$microblogpost['tag']);
			$valtemplate['nompage'] = $microblogpost['tag'];
			if ($template=='liste_microblog.tpl.html')
			{		
				$squel->set($valtemplate);
				$text .= '<ul>'.$squel->analyser().'</ul>';
			}
			else 
			{
				$valtemplate['user'] = $this->Format($microblogpost["user"]);					
				$valtemplate['date'] = date("\l\e d.m.Y &\a\g\\r\av\e; H:i:s", strtotime($microblogpost["time"]));
				if (strstr($microblogpost["body"], "bf_titre")) {

					$tab_valeurs = json_decode($microblogpost["body"], true);
					$tab_valeurs = array_map('utf8_decode', $tab_valeurs);
					$microblogpost["body"] = '""'.baz_voir_fiche(0, $tab_valeurs).'""';
					$valtemplate['nompage'] = $tab_valeurs['bf_titre'];
				}
				$valtemplate['billet'] = $this->Format($microblogpost["body"]);

				// load comments for this page
				$valtemplate['commentaire'] = '';
				$pageouverte = $this->GetTripleValue($microblogpost['tag'],'http://outils-reseaux.org/_vocabulary/comments', '', '');
				if ((COMMENTAIRES_OUVERTS_PAR_DEFAUT && $pageouverte!='0' ) || (!COMMENTAIRES_OUVERTS_PAR_DEFAUT && $pageouverte=='1')) {
			        include_once('tools/tags/libs/tags.functions.php');
			        $valtemplate['commentaire'] .= '<strong class="lien_commenter">Commentaires</strong>'."\n";
		    		$valtemplate['commentaire'] .= "<div class=\"commentaires_billet_microblog\">\n";
					$valtemplate['commentaire'] .= afficher_commentaires_recursif($microblogpost['tag'], $this);
					$valtemplate['commentaire'] .= "</div>\n";
				}	
				//liens d'actions sur le billet			
				$valtemplate['edition'] = '<a href="'.$this->href('', $microblogpost['tag']).'" class="voir_billet">Afficher</a> ';
				if ($this->HasAccess('write', $microblogpost['tag']))
				{
					$valtemplate['edition'] .= '<a href="'.$this->href('edit', $microblogpost['tag']).'" class="editer_billet">Editer</a> ';
				}			
				if ($this->UserIsOwner($microblogpost['tag']) || $this->UserIsAdmin())
				{
					$valtemplate['edition'] .= '<a href="'.$this->href('deletepage', $microblogpost['tag']).'" class="supprimer_billet">Supprimer</a>'."\n" ;
				}				
				$squel->set($valtemplate);
				$text .= $squel->analyser();			
			}					
		} 
	}*/
} else {
	$nb_total = 0;
}

$shownumberinfo = $this->GetParameter('shownumberinfo');
if (!empty($shownumberinfo) && $shownumberinfo == 1) {
	$output .= '<div class="alert alert-info">'."\n";
	if ($nb_total > 1) $output .= 'Un total de '.$nb_total.' pages ont &eacute;t&eacute; trouv&eacute;es';
	elseif ($nb_total == 1) $output .= 'Une page a &eacute;t&eacute; trouv&eacute;e';
	else $output .= 'Aucune page trouv&eacute;e';
	$output .= (!empty($tags) ? ' avec le mot cl&eacute; <span class="label label-info">'.$tags.'</span>' : '').'.';
	$output .= $this->Format('{{rss tags="'.$tags.'" class="pull-right"}}')."\n".'</div>'."\n";
}
$output .= $text;

echo $output."\n";

?>
