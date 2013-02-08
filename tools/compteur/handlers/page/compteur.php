<?php
// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
	die ('acc&egrave;s direct interdit');
}

// on initialise la sortie:
$output = '';

if ($this->HasAccess('read')) {

	$body = $this->page['body'];

	//formatage de la page afin d'éliminer la syntaxe wiki
	$body = $this->Format($body);

	//supression des balises html
	$body = strip_tags($body);

	$nb_char = strlen($body);

	//remplace les accents par leur équivalent non accectué
	//sinon le mot est découpé au niveau de l'accent.
	// Code pour suppimer les accents pris la : http://www.weirdog.com/blog/php/supprimer-les-accents-des-caracteres-accentues.html
	$body = htmlentities($body, ENT_NOQUOTES | ENT_IGNORE);
    
	$body = preg_replace('#\&([A-za-z])(?:acute|cedil|circ|grave|ring|tilde|uml)\;#', '\1', $body);
    $body = preg_replace('#\&([A-za-z]{2})(?:lig)\;#', '\1', $body); // pour les ligatures e.g. '&oelig;'
    $body = preg_replace('#\&quot\;#', '', $body); // bug avec ENT_NOQUOTES qui convertit quand même les doubles guillemets
    $body = preg_replace('#\&[^;]+\;#', '', $body); // supprime les autres caractères/**/


    //remplace les URLs par 'URL' pour ne compter qu'un mot.
    $body = preg_replace("#(((https?|ftp)://(www\.)?[^www][[:alnum:]_.-]+)\.([a-z]{2,4}))#", "URL", $body);

	echo "debug : <pre>";
	print_r($body);
	echo "</pre>";/**/

	//$word_table = preg_split("#[\W]+#", $body, -1, PREG_SPLIT_NO_EMPTY);
	$word_table = preg_split("#[\W]+#", $body, -1, PREG_SPLIT_NO_EMPTY);

	echo "debug : <pre>";
	print_r($word_table);
	echo "</pre>";/**/

	$nb_word = count($word_table);
	$nb_char = strlen($body);

	$output = "Nombre de mots : ".$nb_word."<br />";
	$output .= "Nombre de caract&egrave;res : ".$nb_char." (Ponctuation et espaces inclus)<br />";

}
else {

	$output = "<b>Acc&egrave;s en lecture n&eacute;cessaire.</b>";
}


echo $this->Header();
echo $output;
echo $this->Footer();
?>