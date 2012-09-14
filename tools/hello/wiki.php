<?php
// Partie publique test 123

if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

// Surcharge methode GetUserName de la class Wiki

$wikiClasses [] = 'Hello';
$wikiClassesContent [] = ' 

	function GetUserName() { 
		if ($user = $this->GetUser()) $name = $user["name"];
		else if (!$name = gethostbyaddr($_SERVER["REMOTE_ADDR"])) $name = $_SERVER["REMOTE_ADDR"]; 
		return "Bonjour ".$name;
	}	

';		
