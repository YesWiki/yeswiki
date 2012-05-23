<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

	//le bouton du formulaire n'a pas d'attribut ' name :'(
	//if (isset($_POST["submit"]) && $_POST["submit"] == 'Ajouter Commentaire') {
			require_once('tools/hashcash/secret/wp-hashcash.lib');
			if(!isset($_POST["hashcash_value"]) || $_POST["hashcash_value"] != hashcash_field_value()) {
				$_POST['body'] = "";
			}
			
	//}
	

?>
