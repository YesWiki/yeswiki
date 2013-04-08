<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

## Ajoute le javascript qui calcule le hashcash
if ($this->HasAccess("comment") && $this->page && !$this->page['comment_on'])
{
	$siteurl = str_replace("/wakka.php?wiki=", "", $this->config['base_url']);

	$ChampsHashcash = '<script type="text/javascript" src="' 
		. $siteurl . '/tools/hashcash/wp-hashcash-js.php?siteurl='
		.$siteurl.'"></script><span id="hashcash-text" style="display:none" class="pull-right">Protection anti-spam active</span><hr class="hr_clear" />';

	 $GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '').$ChampsHashcash."\n";
}

?>