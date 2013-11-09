<?php
/**
*  Programme gerant les fiches bazar depuis une interface de type geographique
*
**/

// +------------------------------------------------------------------------------------------------------+
// |                                            ENTETE du PROGRAMME                                       |
// +------------------------------------------------------------------------------------------------------+

if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}

//reuperation des parametres wikini

/*
* categorienature : filtre par categorie de formulaire,affiche l'ensemble des fiches d'une meme categorie
* @testok
* 
*/

$categorie_nature = $this->GetParameter("categorienature");
if (empty($categorie_nature)) {
    $categorie_nature = 'toutes';
}

/*
* categorienature : filtre par numero de formulaire.
* @testok
* 
*/

$id_typeannonce = $this->GetParameter("idtypeannonce");
if (empty($id_typeannonce)) {
    $id_typeannonce = 'toutes';
}

/*
* ordre : ordre affichage detail des points
* @atester
* 
*/

$ordre = $this->GetParameter("ordre");
if (empty($ordre)) {
    $ordre = 'alphabetique';
}

/*
* lat : latitude point central en degres WGS84 (exemple : 46.22763) , sinon parametre par defaut
* @atester
* 
*/

$latitude = $this->GetParameter("lat");
if (empty($latitude)) {
    $latitude = BAZ_GOOGLE_CENTRE_LAT;
}

/*
* lon : longitude point central en degres WGS84 (exemple : 3.42313) , sinon parametre par defaut
* @atester
* 
*/

$longitude = $this->GetParameter("lon");
if (empty($longitude)) {
    $longitude = BAZ_GOOGLE_CENTRE_LON;
}


/*
* niveau de zoom : de 1 (plus eloigne) a 15 (plus proche) , sinon parametre par defaut 5
* @atester
* 
*/

$zoom = $this->GetParameter("zoom");
if (empty($zoom)) {
    $zoom = BAZ_GOOGLE_ALTITUDE;
}

/*
* Type de carto : ROADMAP ou SATELLITE ou HYBRID ou TERRAIN , sinon parametre par defaut TERRAIN
* @atester
* 
*/


$typecarto = $this->GetParameter("typecarto");
if (empty($typecarto)) {
    $typecarto = BAZ_TYPE_CARTO;
} else {
    $typecarto = strtoupper($typecarto);
}

/*
* Outil de navigation , sinon parametre par defaut true
* @atester
* 
*/

$navigation = $this->GetParameter("navigation"); // true or false 
if (empty($navigation)) {
    $navigation = BAZ_AFFICHER_NAVIGATION;
}



/*
* Bouton choix carte : true or false, par defaut true
* @atester
* 
*/

$choix_carte= $this->GetParameter("choixcarte"); // 
if (empty($choix_carte)) {
    $choix_carte = BAZ_AFFICHER_CHOIX_CARTE;
}


/*
* Zoom sur molette : true or false, par defaut false
* @atester
* 
*/


$zoom_molette= $this->GetParameter("zoommolette"); 
if (empty($zoom_molette)) {
    $zoom_molette= BAZ_PERMETTRE_ZOOM_MOLETTE;
}


/*
* Barre de gestion de fiches bazar affiche sous un point : true or false, par defaut true
* @atester
* @FIXME : ajouter parametre par defaut dans wiki.php
* 
*/

$barregestion= $this->GetParameter("barregestion");

if (empty($barregestion)) {
    $barregestion= "true";
}


/*
* Affichage detail des points en dessous de la carte : true or false, par defaut true
* @atester
* @FIXME : ajouter parametre par defaut dans wiki.php
* 
*/

$listepoint= $this->GetParameter("liste"); // true or false
if (empty($listepoint)) {
    $listepoint= "true";
}



$cartowidth = $this->GetParameter("width");
if (empty($cartowidth)) {
    $cartowidth = BAZ_GOOGLE_IMAGE_LARGEUR;
}
$cartoheight = $this->GetParameter("height");
if (empty($cartoheight)) {
    $cartoheight = BAZ_GOOGLE_IMAGE_HAUTEUR;
}

//on recupere les parametres pour une requete specifique
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
$tab_points_carto = array();


$tablinktitle = array();
$i = 0;

foreach ($tableau_resultat as $fiche) {
    $valeurs_fiche = json_decode($fiche[0], true);
    $valeurs_fiche = array_map('utf8_decode', $valeurs_fiche);
    $tab = explode('|', $valeurs_fiche['carte_google']);
    if (count($tab)>1 && $tab[0]!='' && $tab[1]!='' && is_numeric($tab[0]) && is_numeric($tab[1])) {
    	if ($barregestion=="true") {
    		$contenu_fiche=baz_voir_fiche(1,$valeurs_fiche);
    	}
    	else {
    		$contenu_fiche=baz_voir_fiche(0,$valeurs_fiche);
    	}
        $tab_points_carto[]= '{
            "title": "'.addslashes($valeurs_fiche['bf_titre']).'",
            "description": \'<div class="BAZ_cadre_map">'.
            preg_replace("(\r\n|\n|\r|)", '', addslashes('<ul class="css-tabs"></ul>'.$contenu_fiche)).'\',
            "lat": '.$tab[0].',
            "lng": '.$tab[1].'
        }';
         // on genere la liste des titres ? cliquer pour faire apparaitre sur la carte
           
            $tablinktitle[$i] = '<li class="markerlist"><a class="markerlink" href="#" onclick="popups['.$i.'].setContent(markers['.$i.'].desc);popups['.$i.'].setLatLng(markers['.$i.'].getLatLng());map.openPopup(popups['.$i.']);map.panTo(new L.LatLng('.$tab[0].', '.$tab[1].'));return false;">'.$valeurs_fiche['bf_titre'].'</a></li>'."\n";
            $i++;
    }



}
$points_carto = implode(',',$tab_points_carto);

echo
    '<link rel="stylesheet" href="http://cdn.leafletjs.com/leaflet-0.6.4/leaflet.css" />
    <!--[if lte IE 8]>
    <link rel="stylesheet" href="http://cdn.leafletjs.com/leaflet-0.6.4/leaflet.ie.css" />
    <![endif]-->
    <link rel="stylesheet" href="tools/bazar/libs/leaflet/label/leaflet.label.css" />
    
    <script src="http://cdn.leafletjs.com/leaflet-0.6.4/leaflet.js"></script>
    <script src="http://maps.google.com/maps/api/js?v=3&sensor=false"></script>
    <script src="tools/bazar/libs/leaflet/layer/tile/Google.js"></script>
    <script src="tools/bazar/libs/leaflet/label/leaflet.label.js"></script>
    <script src="tools/bazar/libs/leaflet/spiderfier/oms.min.js"></script>';

// Leaflet + plugins :
// Google : add Google layer.
// Label : add a label to markers
// Spiderfier : Spiderfy multiple markers on a same point.

echo 
    '<div id="map" style="width: '.$cartowidth.'; height: '.$cartoheight.'"></div> <ul id="markers"></ul>';
echo 
    '<script type="text/javascript">

    var markers = Array();
    var popups = Array();

    var map;
    
    function initialize() {
    
        map = L.map("map", {
            center: ['.$latitude.', '.$longitude.'],
            zoom: '.$zoom.',
            scrollWheelZoom:'.$zoom_molette.',
            zoomControl:'.$navigation.'
        });


        //Extend the Default marker class
        var CustomIcon = L.Icon.Default.extend({
        options: {
                iconUrl: "'.BAZ_IMAGE_MARQUEUR.'",
                iconSize:['.BAZ_DIMENSIONS_IMAGE_MARQUEUR.'],
                shadowSize:   ['.BAZ_DIMENSIONS_IMAGE_OMBRE_MARQUEUR.'],
                iconAnchor:   [6, 20],
                shadowAnchor: [6, 20], 
            }
         });

        var  customIcon = new CustomIcon();

        var choixcarte= '.$choix_carte.';
        var osm = new L.TileLayer("http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png");
        var ggl = new L.Google("'.$typecarto.'");

        map.addLayer(ggl);

        if (choixcarte) {
            map.addControl(new L.Control.Layers( {"OSM":osm, "Google":ggl}, {}));
        }
        
            //tableau des points des fiches bazar
            var places = [
                '.$points_carto.'
            ];


        var oms = new OverlappingMarkerSpiderfier(map);

        $.each(places, function(i, item){
            var marker =  new L.Marker(new L.LatLng(item.lat, item.lng),{icon: customIcon}).bindLabel(item.title);    
            marker.desc = item.description;
            var popup = new L.Popup({maxWidth:"1000"});
            oms.addListener("click", function(marker) {
                popup.setContent(marker.desc);
                popup.setLatLng(marker.getLatLng());
                map.openPopup(popup);
            });
            map.addLayer(marker);
            oms.addMarker(marker);
            markers[i]=marker;
            popups[i]=popup;


            //markers[i]=new L.Marker(new L.LatLng(item.lat, item.lng),{icon: customIcon}).bindLabel(item.title).bindPopup(new L.Popup({maxWidth:"1000"}).setContent(item.description) );
        });
    
        
    }

    ';

echo 
    '</script>';

if (($listepoint=="true") && count($tablinktitle)>0) {
    //asort($tablinktitle);
    echo '<ol class="listofmarkers" style="-moz-column-count:4; -webkit-column-count:4; column-count:4;">'."\n";
    foreach ($tablinktitle as $key => $value) {
        echo $value;
    }
    echo '</ol>'."\n";
}

?>