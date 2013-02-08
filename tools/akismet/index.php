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
'Parametrage de l\'extension akismet ...'.'<br />'
);

# Liste des thèmes
$plugins_root = dirname(__FILE__).'/../';
$plugins = new plugins($plugins_root);
$plugins->getPlugins(false);
$plugins_list = $plugins->getPluginsList();

$is_writable = is_writable($plugins_root);

if (!$is_writable)
{
	buffer::str(
	'<p>'.sprintf(__('The folder %s is not writable, please check its permissions.'),
	DC_ECRIRE.'/tools/').'</p>'
	);
}
else
{
	buffer::str(
	'<form action="tools.php" method="post">'.
	'<p><label for="tool_url">'.__('Entrez votre clef Akismet').' :</label>'.
	form::field('akismet_key',50,'',$akismet_key).'</p>'.
	'<p><input type="submit" class="submit" value="'.__('Install').'" />'.
	'<input type="hidden" name="p" value="akismet" /></p>'.
	'</form>'
	);
}

if (isset($_POST['akismet_key'])) {
		$key_path = $plugins->location.$p.'/akismet.key.php';
	
		$content="<?php\n". "\$akismet_key='".	$_POST['akismet_key'] ."';\n" . "?>";
		
		if (!files::putContent($key_path,$content)) {
			buffer::str(
			'Problème installation de la clef'.'<br />'
			);
		}
		else {
			buffer::str(
			'Clef installée !'.'<br />'
			);
		}
}
	
?>
