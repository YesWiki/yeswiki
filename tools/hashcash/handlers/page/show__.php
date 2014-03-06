<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

/* Desactivated because replacetext everywere

if ( $this->HasAccess("comment") && !$this->page['comment_on']){

	require_once('tools/hashcash/secret/wp-hashcash.lib');

		// UPDATE RANDOM SECRET
		$curr = @file_get_contents(HASHCASH_SECRET_FILE);
		if(empty($curr) || (time() - @filemtime(HASHCASH_SECRET_FILE)) > HASHCASH_REFRESH) {	
			if (is_writable(HASHCASH_SECRET_FILE)) {
				//update our secret
				$fp = fopen(HASHCASH_SECRET_FILE, 'w');
				fwrite($fp, rand(21474836, 2126008810));
				fclose($fp);
			}
		}

	// Ajoute l'ID ACEditor au formulaire de commentaire.
	$plugin_output_new = 
		str_replace('/addcomment"', 
					'/addcomment" id="ACEditor"', 
					$plugin_output_new);

	$plugin_output_new = 
		str_replace('<div class="clearfix"></div></div>', 
					'<span id="hashcash-text" style="display:none" class="pull-right">'._t('HASHCASH_ANTISPAM_ACTIVATED').'</span>
					<div class="clearfix"></div></div>', 
					$plugin_output_new);
}*/

?>