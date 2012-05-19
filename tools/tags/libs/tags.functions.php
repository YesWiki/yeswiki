<?php
function afficher_commentaires_recursif($page, $wiki, $premier=true) {
	$output = '';
	$comments = $wiki->LoadComments($page);

	// display comments themselves
	if ($comments)
	{
		$valcomment=array();
		$i=0;
		foreach ($comments as $comment)
		{
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
		include_once('squelettephp.class.php');
		$squelcomment = new SquelettePhp('tools/tags/presentation/commentaire_microblog.tpl.html');
		$squelcomment->set($valcomment);
		$output .= $squelcomment->analyser();
	} 
	if ($premier && $wiki->HasAccess("comment",$page))
	{
		// display comment form
		$output .= "<div class=\"microblogcommentform\">\n" ;
		$output .= $wiki->FormOpen("addcomment", $page).'
				<textarea name="body" class="commentaire_microblog" rows="3" placeholder="Ecrire votre commentaire ici..."></textarea><br />
				<button class="btn btn-primary btn-microblog" name="action" value="addcomment">Ajouter votre commentaire</button>'.$wiki->FormClose();
		$output .= "</div>\n" ;
	}

	return $output;
}

function array_non_empty($array){
$retour = array();
  foreach ($array as $a){
  if (!empty($a)){ array_push($retour, $a); }
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