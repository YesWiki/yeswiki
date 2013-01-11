<?php
/*
generationusb.php

Copyright 2010  Florian Schmitt <florian@outils-reseaux.org>
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}

include_once 'tools/generationusb/libs/generationusb.functions.php';

echo $this->Header();

$output = '<div style="background:#FFF;color:#444;padding:10px;height:300px;overflow:auto;">';

// on efface le précédent
if (is_file("files/".$GLOBALS['wiki']->config['generationusb']['filename'].".zip")) unlink("files/".$GLOBALS['wiki']->config['generationusb']['filename'].".zip");

if (!copy("tools/generationusb/libs/YesWikiPortable.zip", "files/".$GLOBALS['wiki']->config['generationusb']['filename'].".zip")) {
	die("<div class=\"error_box\">La copie des fichiers du serveur usb de base vers \"files/".$GLOBALS['wiki']->config['generationusb']['filename'].".zip\" a &eacute;chou&eacute;e...</div>\n");
} else {
	$output .= '<div class="info_box">Copie de l\'archive dans le cache r&eacute;ussie!</div>';
}


$zip = new ZipArchive();

if ($zip->open('files/'.$GLOBALS['wiki']->config['generationusb']['filename'].'.zip', ZipArchive::CREATE) === TRUE) {
 
	// on ajoute les fichiers de base pour l'appli clé usb
	$output .= '<div class="info_box">On ajoute les fichiers de base pour l\'appli clé usb</div>';

	// on récupère les extensions du yeswiki
	$tablefichier = read_all_files('tools');
	if ($tablefichier) {
		 foreach ($tablefichier['files'] as $fichier) {
			  $zip->addFile($fichier, 'YesWikiPortable/App/yeswiki/'.$fichier);
			  $output .= 'Ajout de '.$fichier.'<br />';
		 }
	} 
	
	// on récupère les fichiers uploadés du yeswiki
	$tablefichier = read_all_files('files');
	if ($tablefichier) {
		 foreach ($tablefichier['files'] as $fichier) {
			  $zip->addFile($fichier, 'YesWikiPortable/App/yeswiki/'.$fichier);
			  $output .= 'Ajout de '.$fichier.'<br />';
		 }
	}
	
	// on récupère les themes (templates) du yeswiki
	if (is_dir('themes')) {
		$tablefichier = read_all_files('themes');
		if ($tablefichier) {
			 foreach ($tablefichier['files'] as $fichier) {
				  $zip->addFile($fichier, 'YesWikiPortable/App/yeswiki/'.$fichier);
				  $output .= 'Ajout de '.$fichier.'<br />';
			 }
		}
	}
	
	// des repertoires supplémentaires doivent etre rajoutés : on les ajoute
	if (isset($GLOBALS['wiki']->config['generationusb']['extra_dir'])) {
		foreach($GLOBALS['wiki']->config['generationusb']['extra_dir'] as $extra_dir) {
			if (is_dir($extra_dir)) {
				$tablefichier = read_all_files($extra_dir);
				if ($tablefichier) {
					foreach ($tablefichier['files'] as $fichier) {
						  $zip->addFile($fichier, 'YesWikiPortable/App/yeswiki/'.$fichier);
						  $output .= 'Ajout de '.$fichier.'<br />';
					}
				}
			} else {
				$output .= '<div class="error_box">R&eacute;pertoire "'.$extra_dir.'" non trouv&eacute;.</div>';
			}
		}
	}
	
	// récupération du fichier de configuration
	$fichierconf = fopen('wakka.config.php','r');
	$contents = fread($fichierconf, filesize('wakka.config.php'));
	fclose($fichierconf);
	
	// on rajoute le code pour sauvegarder la base de données pour la version portable
	$contents = str_replace('?>', '
	include_once \'tools/generationusb/libs/generationusb.functions.php\';
	checkportabledb($wakkaConfig[\'mysql_host\'],$wakkaConfig[\'mysql_user\'],$wakkaConfig[\'mysql_password\'],$wakkaConfig[\'mysql_database\']);
	
	?>', $contents);
	
	// on remplace par les valeurs du serveur mysql du site par celles de la clé usb
	$contents = preg_replace("/'mysql_host' => '.*',/", "'mysql_host' => 'localhost:3306',", $contents);
	$contents = preg_replace("/'mysql_database' => '.*',/", "'mysql_database' => 'wikini',", $contents);
	$contents = preg_replace("/'mysql_user' => '.*',/", "'mysql_user' => 'root',", $contents);
	$contents = preg_replace("/'mysql_password' => '.*',/", "'mysql_password' => '',", $contents);
	$contents = preg_replace("/'base_url' => '.*',/", "'base_url' => 'http://127.0.0.1:80/yeswiki/wakka.php?wiki=',", $contents);
	
	$zip->addFromString('YesWikiPortable/App/yeswiki/wakka.config.php', $contents);
	$output .= '<div class="info_box">Ajout de wakka.config.php modifi&eacute; pour la cl&eacute;.</div>';
	
	// on backup les tables
	$sql = backup_tables($this->config['mysql_host'], $this->config['mysql_user'], $this->config['mysql_password'], $this->config['mysql_database'],
						 $this->config['table_prefix'].'acls,'.$this->config['table_prefix'].'links,'.$this->config['table_prefix'].'nature,'.
						 $this->config['table_prefix'].'pages,'.$this->config['table_prefix'].'referrers,'.$this->config['table_prefix'].'triples,'.
						 $this->config['table_prefix'].'users');
	$zip->addFromString('YesWikiPortable/App/yeswiki/portable-mysql-dump.sql', $sql);
	$output .= '<div class="info_box">Sauvegarde de la base MySQL et ajout de portable-mysql-dump.sql.</div>';
	
	// gestion du safe_mode : il doit être à l'identique sur le serveur de la clé pour bien afficher les fichiers joints
	if (ini_get("safe_mode")) {
		$phpini = $zip->getFromName('YesWikiPortable/ZMWS/php5/php.ini');
		$pos = strpos($phpini, 'safe_mode = Off');
		if (!($pos === false)) {
			$phpini = substr_replace($phpini, 'safe_mode = On', $pos, strlen('safe_mode = Off')); 
		}
		$zip->addFromString('YesWikiPortable/ZMWS/php5/php.ini', $phpini);
		$output .= '<div class="info_box">Reconfiguration du safe_mode de php.ini en "On" plut&ocirc;t que "Off".</div>';
	}
	
	// on ferme l'archive et on sauvegarde le fichier
	$zip->close();
	
	$output .= '</div>';
	echo '<div class="info_box">L\'ajout des fichiers du YesWiki dans l\'archive '.$GLOBALS['wiki']->config['generationusb']['filename'].'.zip a réussi, le YesWiki portable est pr&ecirc;t!!&nbsp;<a href="'.$this->href().'" title="Retourner au site">Retourner au site</a>.</div>'."\n".
	$GLOBALS['wiki']->Format('{{yeswikiportable}}')."\n".
	$output;

} else {

    echo "<div class=\"error_box\">Erreur à l'ouverture de fichier \"files/".$GLOBALS['wiki']->config['generationusb']['filename'].".zip\".</div>\n";

}


echo $this->Footer();

?>

