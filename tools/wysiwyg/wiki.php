<?php
if (!defined("WIKINI_VERSION")) {
	die ("acc&egrave;s direct interdit");
}

// TODO: Desactivation de l'extension aceditor si l'extension wysiwyg est presente et active. 
if (isset($plugins_list['aceditor'])) {
	//marche pas : unset($plugins_list['aceditor']);	
}
?>
