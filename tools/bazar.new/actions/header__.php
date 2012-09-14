<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

$lien_RSS = $this->href('xmlutf8', $this->tag, BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_S_ABONNER.'&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_VOIR_FLUX_RSS);

$listerss = '';

//on prend toutes les PageWiki de type formulaire
$val_formulaire = baz_valeurs_tous_les_formulaires();

foreach ($val_formulaire as $type_fiche => $formulaire) {					
	foreach ($formulaire as $nomwiki => $ligne) {
		$listerss .= '<link rel="alternate" type="application/rss+xml" title="'.$ligne['form_name'].' ('.$type_fiche.')" href="'.$lien_RSS.'&amp;type='.$nomwiki.'" />'."\n";
	}
}
$listerss = '<link rel="alternate" type="application/rss+xml" title="'.BAZ_FLUX_RSS_GENERAL.'" href="'.$lien_RSS.'" />'."\n".$listerss."\n";



//ajout des styles css pour bazar, le calendrier, la google map
$style = '<link rel="stylesheet" type="text/css" href="tools/bazar/presentation/styles/bazar.css" media="screen" />'."\n".
'<link rel="stylesheet" type="text/css" href="tools/bazar/libs/fullcalendar/fullcalendar.css" media="screen" />'."\n";


$plugin_output_new = preg_replace ('/<\/head>/', $listerss.$style."\n".'</head>', $plugin_output_new);		
?>
