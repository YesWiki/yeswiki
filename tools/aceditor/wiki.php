<?php
// Partie publique 

if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

// inclusion de la langue
if (isset($metadatas['lang'])) { $wakkaConfig['lang'] = $metadatas['lang']; }
elseif (!isset($wakkaConfig['lang'])) { $wakkaConfig['lang'] = 'fr'; } 
include_once 'tools/aceditor/lang/aceditor_'.$wakkaConfig['lang'].'.inc.php';

$wikiClasses [] = 'Aceditor';
$wikiClassesContent [] = ' 

	function FormOpen($method = "", $tag = "", $formMethod = "post") {

		if ($method=="edit") {
			$result = "<form id=\"ACEditor\" name=\"ACEditor\" action=\"".$this->href($method, $tag)."\" method=\"".$formMethod."\">\n";
		} else {
		$result = "<form action=\"".$this->href($method, $tag)."\" method=\"".$formMethod."\">\n";
		}

		if (!$this->config["rewrite_mode"]) $result .= "<input type=\"hidden\" name=\"wiki\" value=\"".$this->MiniHref($method, $tag)."\" />\n";
		return $result;
	}
';		
?>
