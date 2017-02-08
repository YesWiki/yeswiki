<?php
# namespace YesWiki;

// Partie publique
if (!defined("WIKINI_VERSION")) {
        die("acc&egrave;s direct interdit");
}

//CONFIGURATION
//si 0 les admins ou le proprietaire d'une page doivent ouvrir les commentaires
//si 1 ils sont ouverts par defaut
define('COMMENTAIRES_OUVERTS_PAR_DEFAUT', 0);
define('CACHER_MOTS_CLES', 0);


$wiki  = new WikiTools($wakkaConfig);
$wikiClasses [] = 'Tags';

// fonctions supplementaires a ajouter la classe wiki
$fp = @fopen('tools/tags/libs/tags.class.inc.php', 'r');
$contents = fread($fp, filesize('tools/tags/libs/tags.class.inc.php'));
fclose($fp);
$wikiClassesContent [] = str_replace('<?php', '', $contents);
