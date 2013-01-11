<?php
// index.php
// Administration de l'extension : initialisations (tables, fichier de configuration) , information etc. : toutes
// opérations réservées à l'administrateur technique de Wikini.

// Vérification de sécurité
if (!defined("TOOLS_MANAGER"))
{
        die ("acc&egrave;s direct interdit");
}

// Affichage au moyen de la méthode statique buffer::str :

buffer::str(
'Parametrage de l\'extension Hello ...'.'<br />'
);

// Utilisation d'un objet Wiki pour acces à la base de donnée

$wiki=new Wiki($wakkaConfig);

buffer::str(
'Utilisateurs enregistrés : '.'<br />'
);

$last_users = $wiki->LoadAll("select name, signuptime from ".$wiki->config["table_prefix"]."users order by signuptime desc limit 10");
foreach($last_users as $user) {
	buffer::str(
	  $user["name"]." . . . ".$user["signuptime"]."<br />\n" 
	);
}


?>
