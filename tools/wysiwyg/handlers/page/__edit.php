<?php

if (!defined("WIKINI_VERSION")) {
            die ("acc&egrave;s direct interdit");
}

// Sauvegarde : on transforme le html en code wiki
if ( isset($_POST["submit"]) && $_POST["submit"] == 'Sauver') {
	
	//on remplace les retours charriot par des balises br en vu de les remplacer ensuite
	$_POST["body"] = str_replace(array("\n","\r","&nbsp;"), '', trim($_POST["body"]));
	
	//on supprime les retours à la ligne par balise br
	$patterns[] = '/<br \/>/U';
	$replacements[] = "\n";
	$patterns[] = '/<br\/>/U';
	$replacements[] = "\n";
	$patterns[] = '/<br>/U';
	$replacements[] = "\n";
	
	//on supprime les paragraphes : on ajoute juste un retour à la ligne
	$patterns[] = '/<p.*>/U';
	$replacements[] = '';
	$patterns[] = '/<\/p>/U';
	$replacements[] = "\n";
		
	//on formate les lignes hr
	$patterns[] = '/<hr \/>/U';
	$replacements[] = "------\n";
	
	//on formate les titres
	$patterns[] = '/<h1>/U';
	$replacements[] = '======';
	$patterns[] = '/<\/h1>/U';
	$replacements[] = "======\n";
	$patterns[] = '/<h2>/U';
	$replacements[] = '=====';
	$patterns[] = '/<\/h2>/U';
	$replacements[] = "=====\n";
	$patterns[] = '/<h3>/U';
	$replacements[] = '====';
	$patterns[] = '/<\/h3>/U';
	$replacements[] = "====\n";
	$patterns[] = '/<h4>/U';
	$replacements[] = '===';
	$patterns[] = '/<\/h4>/U';
	$replacements[] = "===\n";
	$patterns[] = '/<h5>/U';
	$replacements[] = '==';
	$patterns[] = '/<\/h5>/U';
	$replacements[] = "==\n";
	
	//on formate le style du texte (gras,italique,barré,souligné)
	$patterns[] = '/<strong>/U';
	$replacements[] = '**';
	$patterns[] = '/<\/strong>/U';
	$replacements[] = "**";
	$patterns[] = '/<em>/U';
	$replacements[] = '//';
	$patterns[] = '/<\/em>/U';
	$replacements[] = "//";
	$patterns[] = '/<span style="text-decoration: underline;">(.*)<\/span>/Uis';
	$replacements[] = '__$1__';
	$patterns[] = '/<span style="text-decoration: line-through;">(.*)<\/span>/Uis';
	$replacements[] = "@@$1@@";
	
	//on formate les liens
	$patterns[]  = '/<a.*href=[\'"]+(.*)[\'"].*>(.*)<\/a>/Uis';
	$replacements[] = "[[$1 $2]]";
	
	//on formate les images
	$patterns[]  = '/<img.*src=[\'"]+(.*)[\'"].*alt=[\'"]+(.*)[\'"]\/>/Uis';
	$replacements[] = "[[$1 $2]]";
	
	//on formate les listes
	$patterns[]  = '/(<ul[^>]*>|<\/ul>)/i';
	$replacements[] = "\n";
	$patterns[]  = '/<li[^>]*>(.*?)<\/li>/i';
	$replacements[] = " - \\1\n";
	
	//TODO: on formate les indentations
	
	$_POST["body"] = preg_replace($patterns, $replacements, $_POST["body"]);
}

?>
