
<?php


/* Exemple de config a ajouter dans le wakka config.php (a l'interieur du tabeau)
  'clickheat' => array (
        'clickheatsource' => 
            "http://[domaine]/[chemin_vers]/clickheat/js/clickheat.js",
        'clickheatsite' => "Nom_Site",
        'clickheatgroup' => $_GET["wiki"],
        'clickheatserver' => "http://[domaine]/[chemin_vers]/clickheat/click.php",
        ),
*/

# Adresse du javascript
$clickheatsource = "http://[domaine]/[chemin_vers]/clickheat/js/clickheat.js";
# Nom du site
$clickheatsite = "Nom_Site";
# Nom du groupe 
#$clickheatgroup = "FermeDeWiki";   // Si vous voulez toute les pages ensemble
$clickheatgroup = $_GET["wiki"];    // Pour separer chaque page
# Adresse appelée pour envoyer les données.
$clickheatserver = "http://[domaine]/[chemin_vers]/clickheat/click.php";

# Si des paramêtres sont passé dans wakka.config.php 
# On ecrase ceux par défaut.

$clickheat = $this->config["clickheat"];
if (isset($clickheat)) {
    $clickheatsource   = $clickheat["clickheatsource"];
    $clickheatsite     = $clickheat["clickheatsite"];
    $clickheatgroup    = $clickheat["clickheatgroup"];
    $clickheatserver   = $clickheat["clickheatserver"]; 
}

if ($clickheatsource == "http://[domaine]/[chemin_vers]/clickheat/js/clickheat.js") {
	echo 'tools clickheat : il faut éditer config.php et renseigner une url pour clickheat.<br />';
	return;
}
?>
