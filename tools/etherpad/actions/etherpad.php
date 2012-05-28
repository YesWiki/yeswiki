<?php

if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}


$padname = $this->GetParameter("name"); // nom du pad

if (empty($padname)) {
	$padname = $this->tag;
}

echo '<div id="wikipad" title="'.$padname.'"></div>';



?>
