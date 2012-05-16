
<?php

/* Exemple de config a ajouter dans le wakka config.php (a l'interieur du tabeau)
  'piwik' => array (
        'server' => "machine.domain.tnl",
        'path' => "/Chemin/Vers/Piwik/",
        ),
*/

# nom de la machine et domaine (www.google.fr par exemple)
$piwik_server = "machine.domain.tnl";
# Chemin vers piwik sur le serveur (/admin/piwik/ par exmple) 
# /!\ : attention au "/" au début et a la fin
$piwik_path = "/Chemin/Vers/Piwik/";

# Si des paramêtres sont passé dans wakka.config.php 
# On ecrase ceux par défaut.

$clickheat = $this->config["clickheat"];
if (isset($clickheat)) {
	$piwik_server 	= $piwik["server"];
	$piwik_path 	= $piwik["path"]; 
}

?>
