<?php

/*vim: set expandtab tabstop=4 shiftwidth=4: */

// +------------------------------------------------------------------------------------------------------+
// | PHP version 5.1                                                                                      |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 1999-2006 Kaleidos-coop.org                                                            |
// +------------------------------------------------------------------------------------------------------+
// | This file is part of wkbazar.                                                                     |
// |                                                                                                      |
// | Foobar is free software; you can redistribute it and/or modify                                       |
// | it under the terms of the GNU General Public License as published by                                 |
// | the Free Software Foundation; either version 2 of the License, or                                    |
// | (at your option) any later version.                                                                  |
// |                                                                                                      |
// | Foobar is distributed in the hope that it will be useful,                                            |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                                        |
// | GNU General Public License for more details.                                                         |
// |                                                                                                      |
// | You should have received a copy of the GNU General Public License                                    |
// | along with Foobar; if not, write to the Free Software                                                |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
// CVS : $Id: wiki.php,v 1.12 2010-12-01 17:01:38 mrflos Exp $


/**
 * wiki.php
 *
 * Description : fichier de configuration de bazar
 *
 *@package wkbazar
 *
 *@author        Florian SCHMITT <florian@outils-reseaux.org>
 *
 *@copyright     outils-reseaux.org 2008
 *@version       $Revision: 1.12 $ $Date: 2010-12-01 17:01:38 $
 *  +------------------------------------------------------------------------------------------------------+
 */

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// +------------------------------------------------------------------------------------------------------+
// |                                            ENTETE du PROGRAMME                                       |
// +------------------------------------------------------------------------------------------------------+

//chemin relatif d'acces au bazar
define('BAZ_CHEMIN', 'tools/bazar/');
define('BAZ_CHEMIN_UPLOAD', 'files/');

//bouh! c'est pas propre! c'est a cause de PEAR et de ses includes
set_include_path(BAZ_CHEMIN.'libs/vendor/'.PATH_SEPARATOR.get_include_path());

//librairies PEAR
//require_once BAZ_CHEMIN.'libs'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'DB.php' ;
require_once BAZ_CHEMIN.'libs/vendor/Net/URL.php';

//principales fonctions de bazar
require_once BAZ_CHEMIN.'libs/bazar.fonct.php';

//prefixe des tables bazar
define('BAZ_PREFIXE', $wakkaConfig['table_prefix']);

// +------------------------------------------------------------------------------------------------------+
// |                                            CORPS du PROGRAMME                                        |
// +------------------------------------------------------------------------------------------------------+

$wikireq = $_REQUEST['wiki'];

// remove leading slash
$wikireq = preg_replace("/^\//", "", $wikireq);

// split into page/method, checking wiki name & method name (XSS proof)
if (preg_match('`^' . '(' . "[A-Za-z0-9]+" . ')/(' . "[A-Za-z0-9_-]" . '*)' . '$`', $wikireq, $matches)) {
    list(, $GLOBALS['_BAZAR_']['pagewiki'], $method) = $matches;
} elseif (preg_match('`^' . "[A-Za-z0-9]+" . '$`', $wikireq)) {
    $GLOBALS['_BAZAR_']['pagewiki'] = $wikireq;
}
$methode = ($method == 'iframe') ? '/iframe' : '';

// Variable d'url
$GLOBALS['_BAZAR_']['url'] = new Net_URL($wakkaConfig['base_url'] . $GLOBALS['_BAZAR_']['pagewiki'] . $methode);

//test de l'existance des tables de bazar et installation si absentes.
$req = "CREATE TABLE IF NOT EXISTS `" . BAZ_PREFIXE . "nature` (
  `bn_id_nature` int(10) unsigned NOT NULL DEFAULT '0',
  `bn_label_nature` varchar(255) DEFAULT NULL,
  `bn_description` text,
  `bn_condition` text,
  `bn_ce_id_menu` int(3) unsigned NOT NULL DEFAULT '0',
  `bn_commentaire` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `bn_appropriation` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `bn_image_titre` varchar(255) NOT NULL DEFAULT '',
  `bn_image_logo` varchar(255) NOT NULL DEFAULT '',
  `bn_couleur_calendrier` varchar(255) NOT NULL DEFAULT '',
  `bn_picto_calendrier` varchar(255) NOT NULL DEFAULT '',
  `bn_template` text NOT NULL,
  `bn_ce_i18n` varchar(5) NOT NULL DEFAULT '',
  `bn_type_fiche` varchar(255) NOT NULL,
  `bn_label_class` varchar(255) NOT NULL,
  PRIMARY KEY (`bn_id_nature`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
$resultat = $GLOBALS['wiki']->query($req);

// +------------------------------------------------------------------------------------------------------+
// |                             LES CONSTANTES DES ACTIONS DE BAZAR                                      |
// +------------------------------------------------------------------------------------------------------+

// Constante des noms des variables
define('BAZ_VARIABLE_VOIR', 'vue');
define('BAZ_VARIABLE_ACTION', 'action');

// Indique les onglets de vues a afficher.
// possibilités : mes_fiches,consulter,rss,saisir,formulaire,listes,importer,exporter
define('BAZ_VOIR_AFFICHER', 'formulaire,consulter,saisir,listes,importer,exporter');

// Permet d'indiquer la vue par defaut si la variable vue n'est pas defini

// Premier niveau d'action : pour toutes les fiches

define('BAZ_VOIR_DEFAUT', 'formulaire');
 // Recherche
define('BAZ_VOIR_CONSULTER', 'consulter');
 // Recherche
define('BAZ_VOIR_MES_FICHES', 'mes_fiches');
define('BAZ_VOIR_S_ABONNER', 'rss');
define('BAZ_VOIR_SAISIR', 'saisir');
define('BAZ_VOIR_FORMULAIRE', 'formulaire');
define('BAZ_VOIR_LISTES', 'listes');
define('BAZ_VOIR_ADMIN', 'administrer');
define('BAZ_VOIR_GESTION_DROITS', 'droits');
define('BAZ_VOIR_IMPORTER', 'importer');
define('BAZ_VOIR_EXPORTER', 'exporter');

// Second : actions du choix de premier niveau.

define('BAZ_MOTEUR_RECHERCHE', 'recherche');
define('BAZ_CHOISIR_TYPE_FICHE', 'choisir_type_fiche');
 //
define('BAZ_GERER_DROITS', 'droits');
define('BAZ_MODIFIER_FICHE', 'modif_fiches');
 // Modifier le formulaire de creation des fiches
define('BAZ_VOIR_FICHE', 'voir_fiche');
define('BAZ_ACTION_NOUVEAU', 'saisir_fiche');
define('BAZ_ACTION_NOUVEAU_V', 'sauver_fiche');
 // Creation apres validation
define('BAZ_ACTION_MODIFIER', 'modif_fiche');
define('BAZ_ACTION_MODIFIER_V', 'modif_sauver_fiche');
 // Modification apres validation
define('BAZ_ACTION_NOUVELLE_LISTE', 'saisir_liste');
define('BAZ_ACTION_NOUVELLE_LISTE_V', 'sauver_liste');
 // Creation apres validation
define('BAZ_ACTION_MODIFIER_LISTE', 'modif_liste');
define('BAZ_ACTION_MODIFIER_LISTE_V', 'modif_sauver_liste');
 // Modification apres validation
define('BAZ_ACTION_SUPPRIMER_LISTE', 'supprimer_liste');
define('BAZ_ACTION_SUPPRESSION', 'supprimer');
define('BAZ_ACTION_PUBLIER', 'publier');
 // Valider la fiche
define('BAZ_ACTION_PAS_PUBLIER', 'pas_publier');
 // Invalider la fiche
define('BAZ_LISTE_RSS', 'rss');
 // Tous les flux  depend de s'abonner
define('BAZ_VOIR_FLUX_RSS', 'affiche_rss');
 // Un flux
define('BAZ_OBTENIR_TOUTES_LES_LISTES_ET_TYPES_DE_FICHES', 'listes_et_fiches');

// Constante pour l'envoi automatique de mail aux admins
define('BAZ_ENVOI_MAIL_ADMIN', false);

// Definition d'un mail par defaut, car il y peut y avoir envoi de mail aux utilisateurs avec la constante suivante
$hrefdomain = $wiki->Href();
$fulldomain = parse_url($hrefdomain);
$hostdomain = $fulldomain["host"];
$adminmail = "noreply@" . $hostdomain;
define('BAZ_ADRESSE_MAIL_ADMIN', isset($wakkaConfig['mail_admin']) ? $wakkaConfig['mail_admin'] : $adminmail);

//==================================== LES FLUX RSS==================================
// Constantes liees aux flux RSS
//==================================================================================
define('BAZ_RSS_NOMSITE', $wakkaConfig['wakka_name']);
 //Nom du site indique dans les flux rss
define('BAZ_RSS_ADRESSESITE', $wakkaConfig['base_url']);
 //Adresse Internet du site indique dans les flux rss
define('BAZ_RSS_DESCRIPTIONSITE', $wakkaConfig['meta_description']);
 //Description du site indiquee dans les flux rss
define('BAZ_NB_ENTREES_FLUX_RSS', 20);
 //nombre maximum d'articles présents dans le flux rss

//Logo du site indique dans les flux rss
define(
    'BAZ_RSS_LOGOSITE',
    (isset($wakkaConfig['baz_rss_logosite'])) ?
    $wakkaConfig['baz_rss_logosite']
    : 'http://outils-reseaux.org/tools/templates/themes/outils-reseaux/images/Puce-titre.gif'
);

//Managing editor du site
define(
    'BAZ_RSS_MANAGINGEDITOR',
    (isset($wakkaConfig['baz_rss_managingeditor'])) ?
    $wakkaConfig['baz_rss_managingeditor']
    : 'accueil@outils-reseaux.org (association Outils-Reseaux)'
);

//Mail Webmaster du site
define(
    'BAZ_RSS_WEBMASTER',
    (isset($wakkaConfig['baz_rss_webmaster'])) ?
    $wakkaConfig['baz_rss_webmaster']
    :'accueil@outils-reseaux.org (association Outils-Reseaux)'
);

//categorie du flux RSS
define(
    'BAZ_RSS_CATEGORIE',
    (isset($wakkaConfig['baz_rss_categorie'])) ?
    $wakkaConfig['baz_rss_categorie']
    : 'Economie Sociale et Solidaire'
);

//==================================== PARAMETRAGE =================================
// Pour regler certaines fonctionnalites de l'application
//==================================================================================

//Valeur par defaut d'etat de la fiche annonce apres saisie
//Mettre 0 pour 'en attente de validation d'un administrateur'
//Mettre 1 pour 'directement validee en ligne'
define('BAZ_ETAT_VALIDATION', "1");

//Valeur maximale en octets pour la taille d'un fichier joint a telecharger
define('BAZ_TAILLE_MAX_FICHIER', 10000 * 1024);

//Type d'affichage des dates dans la liste
//Mettre jma pour jour mois annee, ou jm, ou jmah
define('BAZ_TYPE_AFFICHAGE_LISTE', 'jma');

/** Reglage des droits pour deposer des annonces */

// Mettre a true pour limiter le depot aux redacteurs
//define ('BAZ_RESTREINDRE_DEPOT', false) ;



/** Reglage de l'affichage de la liste deroulante pour la saisie des dates */

// Mettre a true pour afficher une liste deroulante vide pour la saisie des dates
define('BAZ_DATE_VIDE', false);

// Mettre a true pour faire apparaitre un champs texte deroulant dans le formulaire
// de recherche des fiches, pour choisir les emetteurs
define('BAZ_RECHERCHE_PAR_EMETTEUR', false);

/**Choix de l'affichage (true) ou pas (false) de l'email du redacteur dans la fiche.*/
define('BAZ_FICHE_REDACTEUR_MAIL', true);
 // true ou false

//==================================== LES LANGUES ==================================
// Constantes liees a l'utilisation des langues
//==================================================================================
$GLOBALS['_BAZAR_']['langue'] = 'fr-FR';
define('BAZ_LANGUE_PAR_DEFAUT', 'fr');
 //Indique un code langue par defaut
define('BAZ_VAR_URL_LANGUE', 'lang');
 //Nom de la variable GET qui sera passee dans l'URL (Laisser vide pour les sites monolingues)

// Option concernant la division des resultats en pages
define('BAZ_NOMBRE_RES_PAR_PAGE', 50);
define('BAZ_MODE_DIVISION', 'Jumping');
 // 'Jumping' ou 'Sliding' voir http://pear.php.net/manual/fr/package.html.pager.compare.php
define('BAZ_DELTA', 12);
 // Le nombre de page a afficher avant le 'next';



/** Reglage de l'affichage du formulaire de recherche avancee */

// Mettre a true pour afficher automatiquement le formulaire de recherche avancee
// Mettre a false pour avoir un lien afficher la recherche avancee
define('BAZ_MOTEUR_RECHERCHE_AVANCEE', true);

/** Mettre a 0 pour ne pas proposer de filtre dans le moteur de recherche */
define('BAZ_AFFICHER_FILTRE_MOTEUR', true);

//=========================== PARAMETRAGE GOOGLE MAP API ===========================
// parametres pour la carto google
//==================================================================================

// coordonnees du centre de la carte
define(
    'BAZ_MAP_CENTER_LAT',
    (isset($wakkaConfig['baz_google_centre_lat'])) ? $wakkaConfig['baz_google_centre_lat'] : '46.22763'
);
define(
    'BAZ_MAP_CENTER_LON',
    (isset($wakkaConfig['baz_google_centre_lon'])) ? $wakkaConfig['baz_google_centre_lon'] : '2.213749'
);
define('BAZ_GOOGLE_CENTRE_LAT', BAZ_MAP_CENTER_LAT);
define('BAZ_GOOGLE_CENTRE_LON', BAZ_MAP_CENTER_LON);

// prefixe des classes CSS pour les icones du marqueur
define('BAZ_MARKER_ICON_PREFIX', (isset($wakkaConfig['baz_marker_icon'])) ? $wakkaConfig['baz_marker_icon'] : 'glyphicon');

// icone du marqueur de base
define('BAZ_PROVIDER', (isset($wakkaConfig['baz_provider'])) ? $wakkaConfig['baz_provider'] : 'OpenStreetMap.Mapnik');

// icone du marqueur de base
define('BAZ_MARKER_ICON', (isset($wakkaConfig['baz_marker_icon'])) ? $wakkaConfig['baz_marker_icon'] : 'record');

// couleur du marqueur de base
define('BAZ_MARKER_COLOR', (isset($wakkaConfig['baz_marker_color'])) ? $wakkaConfig['baz_marker_color'] : 'darkred');

// petit marqueur (par defaut : non)
define('BAZ_SMALL_MARKER', (isset($wakkaConfig['baz_small_marker'])) ? $wakkaConfig['baz_small_marker'] : '');

// niveau de zoom : de 1 (plus eloigne) a 15 (plus proche)
define('BAZ_GOOGLE_ALTITUDE', (isset($wakkaConfig['baz_google_altitude'])) ? $wakkaConfig['baz_google_altitude'] : '5');

// layer pour carte leaflet
// osm ou google
define('BAZ_LAYER_CARTO', (isset($wakkaConfig['baz_layer_carto'])) ? $wakkaConfig['baz_layer_carto'] : 'osm');

// type de carto google
// ROADMAP ou SATELLITE ou HYBRID ou TERRAIN
define('BAZ_TYPE_CARTO', (isset($wakkaConfig['baz_type_carto'])) ? $wakkaConfig['baz_type_carto'] : 'TERRAIN');

// taille de la carte a l'ecran
define('BAZ_GOOGLE_IMAGE_LARGEUR', '100%');
 // valeur de l'attribut css width de la carte
define('BAZ_GOOGLE_IMAGE_HAUTEUR', '600px');
 // valeur de l'attribut css height de la carte

// image marqueur
define('BAZ_IMAGE_MARQUEUR', 'tools/bazar/presentation/images/marker.png');
define('BAZ_DIMENSIONS_IMAGE_MARQUEUR', '12, 20');
define('BAZ_COORD_ORIGINE_IMAGE_MARQUEUR', '0,0');
define('BAZ_COORD_ARRIVEE_IMAGE_MARQUEUR', '0,20');

// image ombre marqueur
define('BAZ_IMAGE_OMBRE_MARQUEUR', 'tools/bazar/presentation/images/marker_shadow.png');
define('BAZ_DIMENSIONS_IMAGE_OMBRE_MARQUEUR', '22, 20');
define('BAZ_COORD_ORIGINE_IMAGE_OMBRE_MARQUEUR', '0,0');
define('BAZ_COORD_ARRIVEE_IMAGE_OMBRE_MARQUEUR', '0,20');

// Controles carte
define('BAZ_AFFICHER_NAVIGATION', 'true');
 // true ou false
define('BAZ_AFFICHER_CHOIX_CARTE', 'true');
 // true ou false
define('BAZ_AFFICHER_ECHELLE', 'false');
 // true ou false
define('BAZ_PERMETTRE_ZOOM_MOLETTE', 'false');
define('BAZ_STYLE_NAVIGATION', 'ZOOM_PAN');
 // SMALL ou ZOOM_PAN ou ANDROID ou DEFAULT
define('BAZ_STYLE_CHOIX_CARTE', 'DROPDOWN_MENU');
 // HORIZONTAL_BAR ou DROPDOWN_MENU ou DEFAULT

// marqueur de base
define('BAZ_MAP_DEFAULT_MARKER_IMAGE', 'minimarkers/marker_rounded_red.png');
define('BAZ_MAP_DEFAULT_MARKER_SIZE_WIDTH', '16');
define('BAZ_MAP_DEFAULT_MARKER_SIZE_HEIGHT', '16');
define('BAZ_MAP_DEFAULT_MARKER_POINTER_X', '8');
define('BAZ_MAP_DEFAULT_MARKER_POINTER_Y', '16');

// inclure l'url d'un fichier kml (carte google creee precedemment) a afficher sur la carte
define('BAZ_GOOGLE_FOND_KML', '');

//inclure un fichier js specifique, pour ajouter des polygones a la carte par exemple
define('BAZ_JS_INIT_MAP', '');

// Choix du look du template par défaut
define(
    'BAZ_TEMPLATE_LISTE_DEFAUT',
    isset($wakkaConfig['default_bazar_template']) ? $wakkaConfig['default_bazar_template'] : 'liste_accordeon.tpl.html'
);

// les passages de parametres query en get affectent ils les resultats de fiches croisees avec checkboxfiche?
$wakkaConfig['global_query'] = isset($wakkaConfig['global_query']) ? $wakkaConfig['global_query'] : true ;

// Fonctions ajoutées par bazar a la classe Wiki
$wikiClasses[] = 'Bazar';

// fonctions supplementaires a ajouter la classe wiki
$fp = @fopen('tools/bazar/libs/bazar.class.inc.php', 'r');
$contents = fread($fp, filesize('tools/bazar/libs/bazar.class.inc.php'));
fclose($fp);
$wikiClassesContent [] = str_replace('<?php', '', $contents);
