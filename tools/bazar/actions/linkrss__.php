<?php

if (!defined("WIKINI_VERSION")) {
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
$lien_RSS->addQueryString('wiki', $this->minihref('rss',$this->tag));
while ($ligne = $resultat->fetchRow(DB_FETCHMODE_ASSOC)) {
    $lien_RSS->addQueryString('id_typeannonce', $ligne['bn_id_nature']);
    $liste .= '	<link rel="alternate" type="application/rss+xml" title="'.$ligne['bn_label_nature'].'" href="'.str_replace('&','&amp;',$lien_RSS->getURL()).'"  />'."\n";
    $lien_RSS->removeQueryString('id_typeannonce');
}

echo '	<link rel="alternate" type="application/rss+xml" title="'.BAZ_FLUX_RSS_GENERAL.'" href="'.str_replace('&','&amp;',$lien_RSS->getURL()).'" />'."\n".$liste;
