<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

//requete pour obtenir l'id et le label des types d'annonces
$requete = 'SELECT bn_id_nature, bn_label_nature '.
           'FROM '.BAZ_PREFIXE.'nature WHERE 1';
$resultat = $GLOBALS['_BAZAR_']['db']->query($requete) ;
if (DB::isError($resultat)) {
	return ($resultat->getMessage().$resultat->getDebugInfo()) ;
}

// Nettoyage de l url
$GLOBALS['_BAZAR_']['url']->removeQueryString(BAZ_VARIABLE_VOIR);
$liste='';
$lien_RSS= clone($GLOBALS['_BAZAR_']['url']);
$lien_RSS->addQueryString('wiki', $this->minihref('xmlutf8',$this->tag));
$lien_RSS->addQueryString(BAZ_VARIABLE_VOIR, 'rss');
$lien_RSS->addQueryString(BAZ_VARIABLE_ACTION, BAZ_VOIR_FLUX_RSS);
while ($ligne = $resultat->fetchRow(DB_FETCHMODE_ASSOC)) {
	$lien_RSS->addQueryString('annonce', $ligne['bn_id_nature']);
	$liste .= '<link rel="alternate" type="application/rss+xml" title="'.$ligne['bn_label_nature'].'" href="'.str_replace('&','&amp;',$lien_RSS->getURL()).'"  />'."\n";
	$lien_RSS->removeQueryString('annonce');
}
$liste = '<link rel="alternate" type="application/rss+xml" title="'.BAZ_FLUX_RSS_GENERAL.'" href="'.str_replace('&','&amp;',$lien_RSS->getURL()).'" />'."\n".$liste."\n";


//ajout des styles css pour bazar, le calendrier, la google map
$style = '<link rel="stylesheet" type="text/css" href="tools/bazar/presentation/bazar.css" media="screen" />'."\n".
'<link rel="stylesheet" type="text/css" href="tools/bazar/libs/fullcalendar/fullcalendar.css" media="screen" />'."\n";


$plugin_output_new = preg_replace ('/<\/head>/', $liste.$style."\n".'</head>', $plugin_output_new);		
?>