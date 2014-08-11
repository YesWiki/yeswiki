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

//recuperation des parametres wikini

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
    $id_typeannonce = array();
}
else {
    $id_typeannonce=explode(",",$id_typeannonce);
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
    $latitude = BAZ_MAP_CENTER_LAT;
}

/*
* lon : longitude point central en degres WGS84 (exemple : 3.42313) , sinon parametre par defaut
* @atester
* 
*/

$longitude = $this->GetParameter("lon");
if (empty($longitude)) {
    $longitude = BAZ_MAP_CENTER_LON;
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
* Layer pour la carto : osm (leaflet) ou ggl (google), par defaut osm
* @atester
* 
*/
$layercarto = $this->GetParameter("layercarto");
if (empty($layercarto)) {
    $layercarto = BAZ_LAYER_CARTO;
}
if ($layercarto == 'google' || $layercarto == 'googlemap') {
    $layercarto = 'ggl';
}



/*
* Type de carto google : ROADMAP ou SATELLITE ou HYBRID ou TERRAIN , sinon parametre par defaut TERRAIN
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


/*
*
* Affichage en eclate des points superposes : true or false, par defaut false
*
*/

$spider= $this->GetParameter("spider"); // true or false
if (empty($spider)) {
    $spider= "false";
}


/*
*
* Affichage en cluster : true or false, par defaut false
*
*/

$cluster= $this->GetParameter("cluster"); // true or false
if (empty($cluster)) {
    $cluster= "false";
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
if (isset($_GET['query'])) {$query = $_GET['query'];}
else $query = $this->GetParameter("query");
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



// Filtres

$groups = $this->GetParameter("groups"); // parametre groups="bf_ce_titre,bf_ce_pays,etc."


if (empty($groups)) {
    $groups = array();
}
else {
    $groups=explode(",",$groups);
}


// Titres des filtres 

$titles = $this->GetParameter("titles"); // parametre titles="bf_ce_titre,bf_ce_pays,etc."

if (empty($titles)) {
    $titles = array();
}
else {  
    $titles=explode(",",$titles);
}


// Detection des parametres de type liste
$grouplist=array();
foreach ($groups as $group) {
    if (is_liste($group)) {
        $groupfix=preg_replace('/\*/', '', $group); // liste utilise plusieurs fois
        $grouplist[$groupfix]=liste_to_array($group); // On charge les valeurs de la liste
    }
    else {
        $grouplist[$group]=false;
    }
}


$facette = $this->GetParameter("facette"); // true or false 
if (empty($facette)) {
    $facette = false;
}




// Recuperation de tous les formulaires
$tous_les_formulaires=baz_valeurs_tous_les_formulaires();


$tableau_resultat=array();
$jointure=array();
$tabclick=array();
foreach ($id_typeannonce as $annonce) { // Pour chaque annonce : 

    // Fusion des resultats 
    $tableau_resultat = array_merge($tableau_resultat, baz_requete_recherche_fiches($tabquery, $ordre, $annonce, $categorie_nature));


    // Detection jointure avec autre fiche
/*    
     $val_formulaire = baz_valeurs_type_de_fiche($annonce);
     $tableau = formulaire_valeurs_template_champs($val_formulaire['bn_template']);
     //print_r($tableau);

    foreach ($tableau as $ligne) {
        if ($ligne[0]=="listefiche") { // jointure 
            $jointure[$annonce]=$ligne[1]; // il y a une fiche liee
        }
        
    }
  */  
/*
    // Detection autre fiche contenant une reference à cette fiche.
    foreach ($tous_les_formulaires as $formulaire => $template) {
        foreach ($template as $numformulaire => $val_formulaire) {
            $tableau = formulaire_valeurs_template_champs($val_formulaire['bn_template']);
            foreach ($tableau as $ligne) {
               if ($ligne[0]=="listefiche") { // jointure 
                    if ($ligne[1]==$annonce) {
                       $jointure[$numformulaire]=$ligne[1]; // numero de fiche liee
                    }
                }
        
            }
        }
    }*/

}




// Recherche de l'ensemble des fiches liee supplementaires 
/*
$tableau_resultat_lie=array();
foreach ($jointure as $cible=>$origine) {
    $tableau_resultat_lie[$origine][$cible]=baz_requete_recherche_fiches($tabquery, $ordre, $cible, '');
}
*/

$tab_points_carto = array();
$tab_layers_carto = array();


$tablinktitle = array();
$i = 0;

foreach ($tableau_resultat as $fiche) {
    $valeurs_fiche = json_decode($fiche["body"], true);
    if (TEMPLATES_DEFAULT_CHARSET != 'UTF-8') $valeurs_fiche = array_map('utf8_decode', $valeurs_fiche);


// Recherche des fiches liees supplementaires :
/*
    if (isset($tableau_resultat_lie[$valeurs_fiche['id_typeannonce']])) {

        //print $valeurs_fiche['listefiche'.$jointure[$valeurs_fiche['id_typeannonce']]]; // clef listefiche+idtypannonce cible

        foreach ($tableau_resultat_lie[$valeurs_fiche['id_typeannonce']] as $formulaire_lies) {

            foreach ($formulaire_lies as $fiche_lies) {
            
             $valeurs_fiche_liee = json_decode($fiche_lies["body"], true);
             if (TEMPLATES_DEFAULT_CHARSET != 'UTF-8') $valeurs_fiche_liee = array_map('utf8_decode', $valeurs_fiche_liee);


             if ($valeurs_fiche_liee['listefiche'.$valeurs_fiche['id_typeannonce']]==$valeurs_fiche['id_fiche']) { // clef  : listefiche+idtypannonce cible
                    $valeurs_fiche['ficheliees'][$valeurs_fiche_liee['id_fiche']]=$valeurs_fiche_liee;
             }
        }
        }

    }
*/
   


    $tab = explode('|', $valeurs_fiche['carte_google']);
    if (count($tab)>1 && $tab[0]!='' && $tab[1]!='' && is_numeric($tab[0]) && is_numeric($tab[1])) {
        if ($barregestion=="true") {
            $contenu_fiche=baz_voir_fiche(1,$valeurs_fiche);
        }
        else {
            $contenu_fiche=baz_voir_fiche(0,$valeurs_fiche);
        }

        $categories=Array();

        foreach ($groups as $group) {
            $group=preg_replace('/\*/', '', $group); // liste utilise plusieurs fois
            if (!$grouplist[$group]) {
                if ($valeurs_fiche[$group]!="") {
                    $categories[$group][]=trim(preg_replace('/\W+/','',strtolower(strip_tags($valeurs_fiche[$group]))));
                }
            }
            else { // C'est une  liste !
                $index_liste=explode(",",$valeurs_fiche['checkbox'.$group]); 
                if (empty($index_liste[0])) {
                    $index_liste=explode(",",$valeurs_fiche['liste'.$group]);
                }
                if (!empty($index_liste[0])) {
                    foreach ($index_liste as $element_liste) { 
                        if ($grouplist[$group][$element_liste]!="") {
                           $categories[$grouplist[$group][$element_liste]][]=trim(preg_replace('/\W+/','',strtolower(strip_tags($grouplist[$group][$element_liste]))));
                        }
                    }
                }
            }



        }
    

        if ($facette=="true") {

// TODO : a revoir
// "description": "<a href=\"#'.$valeurs_fiche['id_fiche'].'\" >'.$valeurs_fiche['bf_titre'].'</a>",
//            "description": "<a href=\"#'.$valeurs_fiche['id_fiche'].'\" onclick=\"(\"#'.$valeurs_fiche['id_fiche'].'.collapse(\"show\")\")\">'.'Voir la fiche'.'</a>",
            $tab_points_carto[]= '{
            "title": "'.addslashes($valeurs_fiche['bf_titre']).'",
            "description": "<a onclick=\"jQuery(\'#collapse'.$valeurs_fiche['id_fiche'].'\').collapse(\'show\');\" href=\"#collapse'.$valeurs_fiche['id_fiche'].'\" >Voir la fiche</a>",
            "descriptionlongue": \'<div class="BAZ_cadre_map">'.
            preg_replace("(\r\n|\n|\r|)", '', addslashes('<ul class="css-tabs"></ul>'.$contenu_fiche)).'\',
            "categories":'.json_encode($categories).',
            "idtypeannonce": '.$valeurs_fiche['id_typeannonce'].',
            "lat": '.$tab[0].',
            "lng": '.$tab[1].'


        }';

        }
        else  {
            $tab_points_carto[]= '{
            "title": "'.addslashes($valeurs_fiche['bf_titre']).'",
            "description": \'<div class="BAZ_cadre_map">'.
            preg_replace("(\r\n|\n|\r|)", '', addslashes('<ul class="css-tabs"></ul>'.$contenu_fiche)).'\',
            "categories":'.json_encode($categories).',
            "lat": '.$tab[0].',
            "lng": '.$tab[1].'


        }';


        }

        // Preparation tableau affiche sous la carte.
        if ($spider=="true") {
            $tablinktitle[$i] = '<li class="markerlist"><a class="markerlink" href="#" onclick="popups['.$i.'].setContent(markers['.$i.'].desc);popups['.$i.'].setLatLng(markers['.$i.'].getLatLng());map.openPopup(popups['.$i.']);map.panTo(new L.LatLng('.$tab[0].', '.$tab[1].'));return false;">'.$valeurs_fiche['bf_titre'].'</a></li>'."\n";
            $tabclick[$i] = 'popups['.$i.'].setContent(markers['.$i.'].desc);popups['.$i.'].setLatLng(markers['.$i.'].getLatLng());map.openPopup(popups['.$i.']);map.panTo(new L.LatLng('.$tab[0].', '.$tab[1].'));return false;';            
        }
        else {
            $tablinktitle[$i] = '<li class="markerlist"><a class="markerlink" href="#" onclick="markers['.$i.'].openPopup();map.panTo(new L.LatLng('.$tab[0].', '.$tab[1].'));return false;">'.$valeurs_fiche['bf_titre'].'</a></li>'."\n";
            $tabclick[$i] = 'markers['.$i.'].openPopup();map.panTo(new L.LatLng('.$tab[0].', '.$tab[1].'));return false;';
        }

        $i++;
    }
}

// Un tableau qui peut servir pour actioner l'ouverture d'une fenetre sur un clic
$GLOBALS['tabclick']=$tabclick;


 // print_r ($tab_points_carto);

//$js_array_layers_carto = json_encode($tab_layers_carto);


        //var cities = L.layerGroup([littleton, denver, aurora, golden]);


$points_carto = implode(',',$tab_points_carto);

$GLOBALS['points_carto']=$tab_points_carto;

// Leaflet + plugins :
// Google : add Google layer.
// Label : add a label to markers
// Spiderfier : Spiderfy multiple markers on a same point.


    


echo
    '<link rel="stylesheet" href="tools/bazar/libs/vendor/leaflet/leaflet.css" />
    <!--[if lte IE 8]>
    <link rel="stylesheet" href="http://cdn.leafletjs.com/leaflet-0.6.4/leaflet.ie.css" />
    <![endif]-->
    <link rel="stylesheet" href="tools/bazar/libs/vendor/leaflet/label/leaflet.label.css" />'."\n";
    
$this->AddJavascriptFile('http://maps.google.com/maps/api/js?v=3&amp;sensor=false');
$this->AddJavascriptFile('tools/bazar/libs/vendor/leaflet/leaflet.js');
$this->AddJavascriptFile('tools/bazar/libs/vendor/leaflet/layer/tile/Google.js');
$this->AddJavascriptFile('tools/bazar/libs/vendor/leaflet/label/leaflet.label.js');


if ($spider=="true") {
    $this->AddJavascriptFile('tools/bazar/libs/vendor/leaflet/spiderfier/oms.min.js');
}


if ($cluster=="true") {
    echo '
    <link rel="stylesheet" href="tools/bazar/libs/vendor/leaflet/markercluster/MarkerCluster.css" />
    <link rel="stylesheet" href="tools/bazar/libs/vendor/leaflet/markercluster/MarkerCluster.Default.css" />'."\n";
    $this->AddJavascriptFile('tools/bazar/libs/vendor/leaflet/markercluster/leaflet.markercluster-src.js');
    
}



echo '<div id="map" style="width: '.$cartowidth.'; height: '.$cartoheight.'"></div> <ul id="markers"></ul>';
$script = 
    '
// Specifique facette javascript
    var layers=Array();
    var groups = '.json_encode($groups).';

// Fin Specifique facette javascript

    var markers = Array();';

$script .= '
//tableau des points des fiches bazar
    var places = [
        '.$points_carto.'
    ];
';



if ($spider=="true") {
    $script .= 
    'var popups = Array();';
}
$script .=  

    'var map;
     var oms;
    
    

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

        map.addLayer('.$layercarto.');';

    
        $script .=  '
        if (choixcarte) {
            map.addControl(new L.Control.Layers( {"OSM":osm, "Google":ggl}, {}));
        }';




        if ($spider=="true") {
        $script .=  '
            oms = new OverlappingMarkerSpiderfier(map);

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

                       // Specifique facette javascript
                  // Creation tableau layers pour ajout/suppression de point en fonction des criteres
                  $.each(item.categories, function(key, categorie){
                     if (typeof (layers[categorie])=="undefined") {
                        layers[categorie]=Array();
                     }
                     layers[categorie].push(i);
                  });
                 // Fin specifique facette javascript
                
      

               

            });
        ';
        }
        else { 


            if ($cluster=="true") {

                $script .=  '

                    var markerscluster = new L.MarkerClusterGroup();

       
                     $.each(places, function(i, item){

                           var marker=new L.Marker (new L.LatLng(item.lat, item.lng),{icon: customIcon}).bindLabel(item.title).bindPopup(new L.Popup({maxWidth:"1000"}).setContent(item.description));
                           markers[i]=marker;
                           markerscluster.addLayer(marker);
                         
                           // Specifique facette javascript
                          // Creation tableau layers pour ajout/suppression de point en fonction des criteres
                          $.each(item.categories, function(key, categorie){
                             if (typeof (layers[categorie])=="undefined") {
                                layers[categorie]=Array();
                             }
                             layers[categorie].push(i);
                          });
                         // Fin specifique facette javascript
    

                     });

                     map.addLayer(markerscluster);


                ';


            }

            else {   // Pas de spider ni de cluster: option a privilegier si autre plugin a charger



        $script .=  '
  


    $.each(places, function(i, item){
          var marker=new L.Marker (new L.LatLng(item.lat, item.lng),{icon: customIcon}).bindLabel(item.title).bindPopup(new L.Popup({maxWidth:"1000"}).setContent(item.description)).addTo(map);
          markers[i]=marker;
         
           // Specifique facette javascript
          // Creation tableau layers pour ajout/suppression de point en fonction des criteres
          $.each(item.categories, function(key, categorie){
             if (typeof (layers[categorie])=="undefined") {
                layers[categorie]=Array();
             }
             layers[categorie].push(i);
          });
         // Fin specifique facette javascript
    

    });

 // alert (dump( layers )); 
            ';
            }
        }
        $script .=  '
        }
    ';

$this->AddJavascript($script);

if (($listepoint=="true") && count($tablinktitle)>0) {
    //asort($tablinktitle);
    echo '<ol class="listofmarkers" style="-moz-column-count:4; -webkit-column-count:4; column-count:4;">'."\n";
    foreach ($tablinktitle as $key => $value) {
        echo $value;
    }
    echo '</ol>'."\n";
}


?>