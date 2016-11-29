<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

/* Desactivated because replacetext everywere
if ($this->HasAccess("comment") && !$this->page['comment_on'])
{
	// Ajoute le javascript qui calcule le hashcash
	$siteurl = str_replace("/wakka.php?wiki=", "", $this->config['base_url']);

	$ChampsHashcash = '	<script src="' 
		. $siteurl . '/tools/security/wp-hashcash-js.php?siteurl='
		. $siteurl . '"></script>';

	$GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '').$ChampsHashcash."\n";
}
*/

?>

