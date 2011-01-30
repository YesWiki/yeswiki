<?php
// Partie publique 

if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

$wikiClasses [] = 'Aceditor';
$wikiClassesContent [] = ' 

	function FormOpen($method = "", $tag = "", $formMethod = "post") {

		if (ereg("edit$", $this->href($method, $tag))) {
			$result = "<form id=\"ACEditor\" name=\"ACEditor\" action=\"".$this->href($method, $tag)."\" method=\"".$formMethod."\">\n";
		} else {
		$result = "<form action=\"".$this->href($method, $tag)."\" method=\"".$formMethod."\">\n";
		}

		if (!$this->config["rewrite_mode"]) $result .= "<input type=\"hidden\" name=\"wiki\" value=\"".$this->MiniHref($method, $tag)."\" />\n";
		return $result;
	}
';		