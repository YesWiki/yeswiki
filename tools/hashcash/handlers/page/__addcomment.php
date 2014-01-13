<?php

if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}

if (isset($_POST["action"]) && $_POST["action"] == 'addcomment') {
	
	require_once('tools/hashcash/secret/wp-hashcash.lib');
	if(!isset($_POST["hashcash_value"]) || ($_POST["hashcash_value"] != hashcash_field_value())) {
		$this->SetMessage(_t('HASHCASH_COMMENT_NOT_SAVED_MAYBE_YOU_ARE_A_ROBOT'));
		$this->redirect($this->href());
	}	
}


?>