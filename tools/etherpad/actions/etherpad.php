<?php

if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}

$padname = $this->GetParameter("pad"); // nom du pad
if(!isset($padname)) $padname = $this->tag; //prend le nom de la page si le nom n'est pas défini.

$padurl = $this->GetParameter("url");
if(empty($padurl)){
	//cherche l'info dans le wakka.config.php
	if(isset($this->config["etherpad_base_url"]))
		$padurl = $this->config["etherpad_base_url"];
	else {
		//echo "Veuillez pr&eacute;ciser l'URL dans l'action etherpad (parametre url) ou le fichier wakka.config.php (parametre 'etherpad_base_url').";
		//exit(0);
		$padurl = "http://pad.cdrflorac.fr";
	}
}

$encodedurl = urlencode($padurl);

$divid = 'wikipad_'.$padname;

//ajoute le javascript dans la liste de ceux a charger.
$GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '').'<script type="text/javascript" src="tools/etherpad/libs/etherpad.js.php?ID='.$divid.'&URL='.$encodedurl.'"></script>'."\n";


if (empty($padname)) {
	$padname = $this->tag;
}

echo '<div id="'.$divid.'" title="'.$padname.'"></div>';
?>
