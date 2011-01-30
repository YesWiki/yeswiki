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
			if($_POST["hashcash_value"] != hashcash_field_value()) {
				$error="Cette page n'a pas &eacute;t&eacute; enregistr&eacute;e car ce wiki pense que vous etes un robot.  Copiez-collez vos modifications et activez Javascript  !";
				$_POST["submit"] = '';	
			}
			
	}
	
}

?>
