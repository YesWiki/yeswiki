<?php
/*
*/
if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}


if ($this->HasAccess("write") && $this->HasAccess("read"))
{
	if (isset($_POST["submit"]) && $_POST["submit"] == 'Sauver') {
			require_once('tools/hashcash/secret/wp-hashcash.lib');
			if(!isset($_POST["hashcash_value"]) || $_POST["hashcash_value"] != hashcash_field_value()) {
				$error = '<div class="alert alert-danger"><a href="#" data-dismiss="alert" class="close">&times;</a>'._t('HASHCASH_ERROR_PAGE_UNSAVED').'</div>';
				$_POST["submit"] = '';	
			}
			
	}
	
}

?>
