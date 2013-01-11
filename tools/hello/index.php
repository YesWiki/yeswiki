<?php
// index.php
// Administration de l'extension : initialisations (tables, fichier de configuration) , information etc. : toutes
// op�rations r�serv�es � l'administrateur technique de Wikini.

// V�rification de s�curit�
if (!defined("TOOLS_MANAGER"))
{
        die ("acc&egrave;s direct interdit");
}

// Affichage au moyen de la m�thode statique buffer::str :

buffer::str(
'Parametrage de l\'extension Hello ...'.'<br />'
);

// Utilisation d'un objet Wiki pour acces � la base de donn�e

$wiki=new Wiki($wakkaConfig);

buffer::str(
'Utilisateurs enregistr�s : '.'<br />'
);

$last_users = $wiki->LoadAll("select name, signuptime from ".$wiki->config["table_prefix"]."users order by signuptime desc limit 10");
foreach($last_users as $user) {
	buffer::str(
	  $user["name"]." . . . ".$user["signuptime"]."<br />\n" 
	);
}


?>
