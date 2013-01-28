<?php
function afficher_commentaires_recursif($page, $wiki, $premier=true) {
	$output = '';
	$comments = $wiki->LoadComments($page);
	$valcomment['tag'] = $page;
	$valcomment['commentaires'] = array();
	// display comments themselves
	if ($comments) {
		$valcomment = array();
		$i = 0;
		foreach ($comments as $comment) {
			$valcomment['commentaires'][$i]['tag'] = $comment["tag"];
			$valcomment['commentaires'][$i]['body'] = $wiki->Format($comment["body"]);
			$valcomment['commentaires'][$i]['infos'] = $wiki->Format($comment["user"]).", ".date(TAGS_DATE_FORMAT, strtotime($comment["time"]));
			$valcomment['commentaires'][$i]['hasrighttoaddcomment'] = $wiki->HasAccess("comment", $wiki->page["comment_on"]);
			$valcomment['commentaires'][$i]['hasrighttomodifycomment'] = $wiki->HasAccess('write', $comment['tag']) || $wiki->UserIsOwner($comment['tag']) || $wiki->UserIsAdmin();
			$valcomment['commentaires'][$i]['hasrighttodeletecomment'] = $wiki->UserIsOwner($comment['tag']) || $wiki->UserIsAdmin();
			$valcomment['commentaires'][$i]['replies'] = afficher_commentaires_recursif($comment['tag'], $wiki, false);
			$i++;
		}
	} 

	// formulaire d'ajout de commentaire
	$commentform = '';
	if ($premier && $wiki->HasAccess("comment", $page))	{
		$commentform .= "<div class=\"comment-form\">\n" ;
		$commentform .= $wiki->FormOpen("addcomment", $page).'
				<textarea name="body" required="required" class="textarea-comment" rows="3" placeholder="'.TAGS_WRITE_YOUR_COMMENT_HERE.'"></textarea>
				<button class="btn btn-small" name="action" value="addcomment"><i class="icon-comment"></i>&nbsp;'.TAGS_ADD_YOUR_COMMENT.'</button>'.$wiki->FormClose();
		$commentform .= "<div class=\"clearfix\"></div></div>\n" ;
	}

	include_once('squelettephp.class.php');
	$squelcomment = new SquelettePhp('tools/tags/presentation/templates/comment_list.tpl.html');
	$squelcomment->set($valcomment);
	$output .= $squelcomment->analyser()."\n".$commentform;

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