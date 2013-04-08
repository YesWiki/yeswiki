<?php

if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}

if (isset($_POST["action"]) && $_POST["action"] == 'addcomment') {
	
	require_once('tools/hashcash/secret/wp-hashcash.lib');
	if(!isset($_POST["hashcash_value"]) || ($_POST["hashcash_value"] != hashcash_field_value())) {
		//$_POST['body'] .= " ".$this->href();
		$this->SetMessage("Votre commentaire n'a pas été enregistré, le wiki pense que vous êtes un robot.");
		$this->redirect($this->href());
	}	
}


?>
