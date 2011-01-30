<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

//Sauvegarde
if (!CACHER_MOTS_CLES && isset($_POST["submit"]) && $_POST["submit"] == 'Sauver' && $this->HasAccess("write") && isset($_POST["tags"]) && $_POST['antispam']==1 )
{
	$this->SaveTags($this->GetPageTag(), $_POST["tags"]);
}
?>
