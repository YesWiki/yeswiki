<?php
// Partie publique 

if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

// Indique un code langue par defaut
define ('HASHCASH_DEFAULT_LANG', 'fr') ; 

// Code pour l'inclusion des langues
if ( isset ($_GET['lang']) && file_exists('tools/hashcash/lang/hashcash_'.$_GET['lang'].'.inc.php')) {
    include_once 'tools/hashcash/lang/hashcash_'.$_GET['lang'].'.inc.php';
} else {
    include_once 'tools/hashcash/lang/hashcash_'.HASHCASH_DEFAULT_LANG.'.inc.php';
}


$wikiClasses [] = 'Hashcash';
$wikiClassesContent [] = ' 

	function FormOpen($method = "", $tag = "", $formMethod = "post", $class="") {

		if ($method=="edit") {
			$result  = "<form id=\"ACEditor\" name=\"ACEditor\" enctype=\"multipart/form-data\" action=\"".$this->href($method, $tag)."\" method=\"".$formMethod."\"";
			$result .= ((!empty($class)) ? " class=\"".$class."\"" : "");
			$result .= ">\n";
		} else {
			$result = "<form action=\"".$this->href($method, $tag)."\" method=\"".$formMethod."\"";
			$result .= ((!empty($class)) ? " class=\"".$class."\"" : "");
			$result .= ">\n";
		}

		if (!$this->config["rewrite_mode"]) $result .= "<input type=\"hidden\" name=\"wiki\" value=\"".$this->MiniHref($method, $tag)."\" />\n";
		return $result;
	}
';

?>