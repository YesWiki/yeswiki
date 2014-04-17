<?php

if (!defined("WIKINI_VERSION")) {
    die ("acc&egrave;s direct interdit");
}

$liste='';
$resultat = baz_valeurs_type_de_fiche() ;
foreach ($resultat as $key => $ligne) {
    $liste .= '  <link rel="alternate" type="application/rss+xml" title="'.$ligne['bn_label_nature'].'" href="'.$this->href('rss', $this->getPageTag(), 'id_typeannonce='.$ligne['bn_id_nature']).'"  />'."\n";
}

echo '  <link rel="alternate" type="application/rss+xml" title="'._t('BAZ_FLUX_RSS_GENERAL').'" href="'.$this->href('rss').'" />'."\n".$liste;
