<?php
/** afficher_image_attach() - genere une image en cache (gestion taille et vignettes) et l'affiche comme il faut
 *
 * @param   string	nom du fichier image
 * @param   string	label pour l'image
 * @param   string	classes html supplementaires
 * @param   int		largeur en pixel de la vignette
 * @param   int		hauteur en pixel de la vignette
 * @param   int		largeur en pixel de l'image redimensionnee
 * @param   int		hauteur en pixel de l'image redimensionnee
 * @return  html    affichage a l'ecran
 */
function afficher_image_attach($idfiche, $nom_image, $label, $class, $largeur_vignette, $hauteur_vignette)
{
    $oldpage = $GLOBALS['wiki']->GetPageTag();
    $GLOBALS['wiki']->tag = $idfiche;
    $GLOBALS['wiki']->page['time'] = date('YmdHis');
    $GLOBALS['wiki']->setParameter("desc", $label);
    $GLOBALS['wiki']->setParameter("file", $nom_image);   
    $GLOBALS['wiki']->setParameter("class", $class);
    $GLOBALS['wiki']->setParameter("width", $largeur_vignette);  
    $GLOBALS['wiki']->setParameter("height", $hauteur_vignette); 
    if (!class_exists('attach')){
        include('tools/attach/actions/attach.class.php');
    }
    $attach = new Attach($GLOBALS['wiki']);
    ob_start();
    $attach->doAttach();  
    $output = ob_get_contents();
    ob_end_clean();           
    $GLOBALS['wiki']->tag = $oldpage;

    $output = preg_replace('/width=\".*\".*height=\".*\"/U', '', $output );
    return $output;
}

function sanitizeEntity($string) {
	return htmlspecialchars(strtr(str_replace('\\\'','_',$string),'/ àáâãäçèéêëìíîïñòóôõöùúûüıÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜİ',
'__aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY'));
}

function tokenTruncate($string, $your_desired_width) {
  $parts = preg_split('/([\s\n\r]+)/', $string, null, PREG_SPLIT_DELIM_CAPTURE);
  $parts_count = count($parts);

  $length = 0;
  $last_part = 0;
  for (; $last_part < $parts_count; ++$last_part) {
    $length += strlen($parts[$last_part]);
    if ($length > $your_desired_width) { break; }
  }

  return implode(array_slice($parts, 0, $last_part));
}

function get_filtertags_parameters_recursive($nb=1, $tab = array()) {	
	$filter = $GLOBALS['wiki']->GetParameter('filter'.$nb);

	if (empty($filter) && $nb == 1) exit('<div class="alert alert-danger">'.TAGS_NO_FILTERS.'</div>'."\n");
	elseif (empty($filter)) return $tab;
	else {
		if (!isset($tab['tags'])) $tab['tags'] = ''; else $tab['tags'] .= ',';
		$explodelabel = explode(":", $filter);

		// on decoupe le choix pour recuperer le titre
		if (count($explodelabel)> 2) exit('<div class="alert alert-danger">'.TAGS_ONLY_ONE_DOUBLEPOINT.'</div>'."\n");
		elseif (count($explodelabel) == 2) {
			$tab[$nb]['title'] =  '<strong>'.$explodelabel[0].' : </strong>'."\n"; 
			$tab[$nb]['arraytags'] = explode(',', $explodelabel[1]);
		}
		else {
			$tab[$nb]['title'] = '';
			$tab[$nb]['arraytags']  = explode(',', $explodelabel[0]);
		}
		$toggle = $GLOBALS['wiki']->GetParameter('select'.$nb);
		if (!empty($toggle) && $toggle == 'checkbox') $tab[$nb]['toggle'] = $toggle;
		else $tab[$nb]['toggle'] = 'radio';
		$class = $GLOBALS['wiki']->GetParameter('class'.$nb);
		if (!empty($class)) $tab[$nb]['class'] = $class;
		else $tab[$nb]['class'] = 'filter-inline';
		$tab['tags'] .= '"'.implode('","', $tab[$nb]['arraytags']).'"';
		$nb++;
		$tab = get_filtertags_parameters_recursive($nb, $tab);

		return $tab;
	}
}

function afficher_commentaires_recursif($page, $wiki, $premier=true) {
	$output = '';
	$comments = $wiki->LoadComments($page);
	$valcomment['tag'] = $page;
	$valcomment['commentaires'] = array();
	// display comments themselves
	if ($comments) {
		$valcomment=array();
		$i=0;
		foreach ($comments as $comment) {
			$valcomment['commentaires'][$i]['tag'] = $comment["tag"];
			$valcomment['commentaires'][$i]['body'] = $wiki->Format($comment["body"]);
			$valcomment['commentaires'][$i]['infos'] = "de ".$wiki->Format($comment["user"]).", ".date("\l\e d.m.Y &\a\g\\r\av\e; H:i:s", strtotime($comment["time"]));
			$valcomment['commentaires'][$i]['actions'] = '';
			if ($wiki->HasAccess("comment", $comment['tag']))
			{
				$valcomment['commentaires'][$i]['actions'] .= '<a href="'.$wiki->href('', $comment['tag']).'" class="repondre_commentaire">R&eacute;pondre</a> ';
			}
			if ($wiki->HasAccess('write', $comment['tag']) || $wiki->UserIsOwner($comment['tag']) || $wiki->UserIsAdmin($comment['tag']))
			{
				$valcomment['commentaires'][$i]['actions'] .= '<a href="'.$wiki->href('edit', $comment['tag']).'" class="editer_commentaire">Editer</a> ';
			}
			if ($wiki->UserIsOwner($comment['tag']) || $wiki->UserIsAdmin())
			{
				$valcomment['commentaires'][$i]['actions'] .= '<a href="'.$wiki->href('deletepage', $comment['tag']).'" class="supprimer_commentaire">Supprimer</a>'."\n" ;
			}
			$valcomment['commentaires'][$i]['reponses'] = afficher_commentaires_recursif($comment['tag'], $wiki, false);
			$i++;
		}
	} 

	// formulaire d'ajout de commentaire
	$valcomment['commentform'] = '';
	if ($premier && $wiki->HasAccess("comment", $page))	{
		$valcomment['commentform'] .= "<div class=\"microblog-comment-form\">\n" ;
		$valcomment['commentform'] .= $wiki->FormOpen("addcomment", $page).'
				<textarea name="body" class="comment-microblog" rows="3" placeholder="Ecrire votre commentaire ici..."></textarea>
				<button class="btn btn-primary btn-microblog" name="action" value="addcomment">Ajouter votre commentaire</button>'.$wiki->FormClose();
		$valcomment['commentform'] .= "<div class=\"clear\"></div></div>\n" ;
	}

	include_once('squelettephp.class.php');
	$squelcomment = new SquelettePhp('tools/tags/presentation/templates/comment_list.tpl.html');
	$squelcomment->set($valcomment);
	$output .= $squelcomment->analyser();

	return $output;
}

function array_non_empty($array) {
	$retour = array();
	foreach ($array as $a){
		if (!empty($a)) { 
			array_push($retour, $a);
		}
	}
	return $retour;
}

function split_words($string){
$retour = array();
  $delimiteurs = ' .!?, :;(){}[]%';
  $tok = strtok($string, " ");
  while (strlen(join(" ", $retour)) != strlen($string)) {
  array_push($retour, $tok);
  $tok = strtok($delimiteurs);
  }
  return array_non_empty($retour);
}
?>