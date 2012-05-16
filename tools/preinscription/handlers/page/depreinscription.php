<?php
//vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

if (isset($_GET['email'])) 
{
	$req = "DELETE FROM ".$this->config["table_prefix"]."triples WHERE ".
		"resource = '".mysql_escape_string($this->tag)."' AND ".
		"property  = '".mysql_escape_string('http://outils-reseaux.org/_vocabulary/preinscription')."' AND ".
		"value LIKE '".mysql_escape_string($_GET['email'])."%'";        
	$this->Query($req);
	$msg = "Vous avez &eacute;t&eacute; d&eacute;sinscrit de la formation.";
	$this->SetMessage($msg);
	if (isset($_GET['page'])) $this->Redirect($this->config['base_url'].$_GET['page']); else $this->Redirect($this->href());
}
?>
