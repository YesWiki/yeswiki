<?php
/**
* map : programme affichant les fiches du bazar sous forme de Cartographie Leaflet
*
* @package		Bazar
* @author 		Florian SCHMITT <florian@outils-reseaux.org>
*
*/

// +------------------------------------------------------------------------------------------------------+
// |                                            ENTETE du PROGRAMME                                       |
// +------------------------------------------------------------------------------------------------------+

if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}

$width = $this->GetParameter("width");
if (empty($width)) {
    $width = '100%';
}

$height = $this->GetParameter("height");
if (empty($height)) {
    $height = '700px';
}

$latitude = $this->GetParameter("lat");
if (empty($latitude)) {
    $latitude = '21';
}

$longitude = $this->GetParameter("lon");
if (empty($longitude)) {
    $longitude = '7';
}

$zoom = $this->GetParameter("zoom");
if (empty($zoom)) {
    $zoom = '3';
}

$markersjs = '';
$tablinktitle = array();
$i = 0;

// on passe par une fiche bazar
$id_typeannonce = $this->GetParameter("formid");
if (!empty($id_typeannonce)) {
    //r?cup?ration des param?tres pour la recherche bazar
    $categorie_nature = $this->GetParameter("categorienature");
    if (empty($categorie_nature)) {
        $categorie_nature = 'toutes';
    }

    $ordre = $this->GetParameter("ordre");
    if (empty($ordre)) {
        $ordre = 'alphabetique';
    }

    //on r?cup?re les param?tres pour une requ?te sp?cifique
    $query = $this->GetParameter("query");
    if (!empty($query)) {
        $tabquery = array();
        $tableau = array();
        $tab = explode('|', $query);
        foreach ($tab as $req) {
            $tabdecoup = explode('=', $req, 2);
            $tableau[$tabdecoup[0]] = trim($tabdecoup[1]);
        }
        $tabquery = array_merge($tabquery, $tableau);
    } else {
        $tabquery = '';
    }

    $tableau_resultat = baz_requete_recherche_fiches($tabquery, $ordre, $id_typeannonce, $categorie_nature);

    foreach ($tableau_resultat as $fiche) {
        $valeurs_fiche = json_decode($fiche["body"], true);
        $valeurs_fiche = array_map('utf8_decode', $valeurs_fiche);
        $tab = explode('|', $valeurs_fiche['carte_google']);
        if (count($tab)>1 && $tab[0]!='' && $tab[1]!='' && is_numeric($tab[0]) && is_numeric($tab[1])) {
            // on genere le point marqueur sur la carte
            $markersjs .= '
            i++;
            var markerLocation = new L.LatLng('.$tab[0].', '.$tab[1].');
            marker[i] = new L.Marker(markerLocation);
            map.addLayer(marker[i]);
            marker[i].bindPopup(\''.preg_replace("(\r\n|\n|\r|)", '', addslashes(baz_voir_fiche(0, $valeurs_fiche))).'\');

            ';
            // on genere la liste des titres ? cliquer pour faire apparaitre sur la carte
            $i++;
            $tablinktitle[$i] = '<li class="markerlist"><a class="markerlink" href="#" onclick="marker['.$i.'].openPopup();map.panTo(new L.LatLng('.$tab[0].', '.$tab[1].'));return false;">'.$valeurs_fiche['bf_titre'].'</a></li>'."\n";
        }
    }

} else {

}

echo '<link rel="stylesheet" href="tools/bazar/libs/vendor/leaflet/leaflet.css" />
<!--[if lte IE 8]>
    <link rel="stylesheet" href="tools/bazar/libs/vendor/leaflet/leaflet.ie.css" />
<![endif]-->
<div id="osmmap" style="width:'.$width.'; height:'.$height.'"></div>'."\n";


$js = '<script src="tools/bazar/libs/vendor/leaflet/leaflet.js"></script>
<script>
    var map = new L.Map(\'osmmap\');

    var cloudmadeUrl = \'http://{s}.tile.cloudmade.com/BC9A493B41014CAABB98F0471D759707/997/256/{z}/{x}/{y}.png\',
        cloudmadeAttribution = \'Donn&eacute;es OpenStreetMap\',
        cloudmade = new L.TileLayer(cloudmadeUrl, {maxZoom: 18, attribution: cloudmadeAttribution});

    map.setView(new L.LatLng('.$latitude.', '.$longitude.'), '.$zoom.').addLayer(cloudmade);

    var i = 0;
    var marker = Array();
    '.$markersjs.'
</script>'."\n";

$GLOBALS['js'] = (isset($GLOBALS['js']) ? str_replace($js, '', $GLOBALS['js']) : '').$js;

if (count($tablinktitle)>0) {
    //asort($tablinktitle);
    echo '<ol class="listofmarkers" style="-moz-column-count:4; -webkit-column-count:4; column-count:4;">'."\n";
    foreach ($tablinktitle as $key => $value) {
        echo $value;
    }
    echo '</ol>'."\n";
}
