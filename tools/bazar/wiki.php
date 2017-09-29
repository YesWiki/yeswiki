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

//principales fonctions de bazar
require_once BAZ_CHEMIN.'libs/bazar.fonct.php';
require_once BAZ_CHEMIN.'libs/bazar.fonct.misc.php';


// +------------------------------------------------------------------------------------------------------+
// |                                            CORPS du PROGRAMME                                        |
// +------------------------------------------------------------------------------------------------------+

//test de l'existance des tables de bazar et installation si absentes.
$req = "CREATE TABLE IF NOT EXISTS `" . $wakkaConfig['table_prefix'] . "nature` (
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

// Indique les onglets de vues a afficher.
// possibilités : mes_fiches,consulter,rss,saisir,formulaire,listes,importer,exporter
$wakkaConfig['baz_menu'] = getConfigValue('baz_menu', 'formulaire,consulter,saisir,listes,importer,exporter', $wakkaConfig);

// Constante pour l'envoi automatique de mail aux admins
$wakkaConfig['BAZ_ENVOI_MAIL_ADMIN'] = getConfigValue('BAZ_ENVOI_MAIL_ADMIN', false, $wakkaConfig);

// Definition d'un mail par defaut, car il y peut y avoir envoi de mail aux utilisateurs avec la constante suivante
$hrefdomain = $wiki->Href();
$fulldomain = parse_url($hrefdomain);
$hostdomain = $fulldomain["host"];
$adminmail = "noreply@" . $hostdomain;
$wakkaConfig['BAZ_ADRESSE_MAIL_ADMIN'] = getConfigValue('BAZ_ADRESSE_MAIL_ADMIN', $adminmail, $wakkaConfig);

//==================================== LES FLUX RSS==================================
// Valeurs liees aux flux RSS
//==================================================================================

//Nom du site indique dans les flux rss
$wakkaConfig['BAZ_RSS_NOMSITE'] = getConfigValue('BAZ_RSS_NOMSITE', $wakkaConfig['wakka_name'], $wakkaConfig);

//Adresse Internet du site indique dans les flux rss
$wakkaConfig['BAZ_RSS_ADRESSESITE'] = getConfigValue('BAZ_RSS_ADRESSESITE', $wakkaConfig['base_url'], $wakkaConfig);

//Description du site indiquee dans les flux rss
$wakkaConfig['BAZ_RSS_DESCRIPTIONSITE'] = getConfigValue('BAZ_RSS_DESCRIPTIONSITE', $wakkaConfig['meta_description'], $wakkaConfig);

//nombre maximum d'articles présents dans le flux rss
$wakkaConfig['BAZ_NB_ENTREES_FLUX_RSS'] = getConfigValue('BAZ_NB_ENTREES_FLUX_RSS', 20, $wakkaConfig);

//Logo du site indique dans les flux rss
$wakkaConfig['BAZ_RSS_LOGOSITE'] = getConfigValue('BAZ_RSS_LOGOSITE', 'https://yeswiki.net/tools/templates/themes/yeswiki/images/apple-touch-icon.png', $wakkaConfig);

//Managing editor du site
$wakkaConfig['BAZ_RSS_MANAGINGEDITOR'] = getConfigValue('BAZ_RSS_MANAGINGEDITOR', 'contact@yeswiki.net (Mr YesWiki)', $wakkaConfig);

//Mail Webmaster du site
$wakkaConfig['BAZ_RSS_WEBMASTER'] = getConfigValue('BAZ_RSS_WEBMASTER', 'contact@yeswiki.net (Mr YesWiki)', $wakkaConfig);

//categorie du flux RSS
$wakkaConfig['BAZ_RSS_CATEGORIE'] = getConfigValue('BAZ_RSS_CATEGORIE', 'Economie Sociale et Solidaire', $wakkaConfig);

//==================================== PARAMETRAGE =================================
// Pour regler certaines fonctionnalites de l'application
//==================================================================================

//Valeur par defaut d'etat de la fiche annonce apres saisie
//Mettre 0 pour 'en attente de validation d'un administrateur'
//Mettre 1 pour 'directement validee en ligne'
$wakkaConfig['BAZ_ETAT_VALIDATION'] = getConfigValue('BAZ_ETAT_VALIDATION', '1', $wakkaConfig);

//Valeur maximale en octets pour la taille d'un fichier joint a telecharger
$max = file_upload_max_size();
$wakkaConfig['BAZ_TAILLE_MAX_FICHIER'] = getConfigValue('BAZ_TAILLE_MAX_FICHIER', $max, $wakkaConfig);

//Type d'affichage des dates dans la liste
//Mettre jma pour jour mois annee, ou jm, ou jmah
$wakkaConfig['BAZ_TYPE_AFFICHAGE_LISTE'] = getConfigValue('BAZ_TYPE_AFFICHAGE_LISTE', 'jma', $wakkaConfig);

// Reglage de l'affichage de la liste deroulante pour la saisie des dates Mettre a true pour afficher une liste deroulante vide pour la saisie des dates
$wakkaConfig['BAZ_DATE_VIDE'] = getConfigValue('BAZ_DATE_VIDE', false, $wakkaConfig);

// Option concernant la division des resultats en pages
$wakkaConfig['BAZ_NOMBRE_RES_PAR_PAGE'] = getConfigValue('BAZ_NOMBRE_RES_PAR_PAGE', 50, $wakkaConfig);

// 'Jumping' ou 'Sliding' voir http://pear.php.net/manual/fr/package.html.pager.compare.php
$wakkaConfig['BAZ_MODE_DIVISION'] = getConfigValue('BAZ_MODE_DIVISION', 'Jumping', $wakkaConfig);

// Le nombre de page a afficher avant le 'next';
$wakkaConfig['BAZ_DELTA'] = getConfigValue('BAZ_DELTA', 12, $wakkaConfig);


//=========================== PARAMETRAGE GOOGLE MAP API ===========================
// parametres pour la carto google
//==================================================================================

// coordonnees du centre de la carte : france par defaut
$wakkaConfig['baz_map_center_lat'] = getConfigValue('baz_map_center_lat', '46.22763', $wakkaConfig);
$wakkaConfig['baz_map_center_lon'] = getConfigValue('baz_map_center_lon', '2.213749', $wakkaConfig);

// prefixe des classes CSS pour les icones du marqueur
$wakkaConfig['baz_marker_icon_prefix'] = getConfigValue('baz_marker_icon_prefix', 'glyphicon glyphicon-', $wakkaConfig);

// icone du marqueur de base
$wakkaConfig['baz_provider'] = getConfigValue('baz_provider', 'OpenStreetMap.Mapnik', $wakkaConfig);

// icone du marqueur de base
$wakkaConfig['baz_marker_icon'] = getConfigValue('baz_marker_icon', 'glyphicon glyphicon-record', $wakkaConfig);

// couleur du marqueur de base
$wakkaConfig['baz_marker_color'] = getConfigValue('baz_marker_color', 'darkred', $wakkaConfig);

// petit marqueur (par defaut : non)
$wakkaConfig['baz_small_marker'] = getConfigValue('baz_small_marker', '', $wakkaConfig);

// niveau de zoom : de 1 (plus eloigne) a 15 (plus proche)
$wakkaConfig['baz_map_zoom'] = getConfigValue('baz_map_zoom', '5', $wakkaConfig);

// taille de la carte a l'ecran: valeur de l'attribut css width de la carte
$wakkaConfig['baz_map_width'] = getConfigValue('baz_map_width', '100%', $wakkaConfig);

 // taille de la carte a l'ecran : valeur de l'attribut css height de la carte
$wakkaConfig['baz_map_height'] = getConfigValue('baz_map_height', '600px', $wakkaConfig);

// afficher la navigation : true ou false
$wakkaConfig['baz_show_nav'] = getConfigValue('baz_show_nav', 'true', $wakkaConfig);

// zoom a la roulette de souris : true ou false
$wakkaConfig['baz_wheel_zoom'] = getConfigValue('baz_wheel_zoom', 'false', $wakkaConfig);

// image marqueur
$wakkaConfig['baz_marker_image_file'] = getConfigValue('baz_marker_image_file', 'tools/bazar/presentation/images/marker.png', $wakkaConfig);

// TODO : verifier si utilisé
// $wakkaConfig['BAZ_DIMENSIONS_IMAGE_MARQUEUR'] = getConfigValue('BAZ_DIMENSIONS_IMAGE_MARQUEUR', '12, 20', $wakkaConfig);
//
// $wakkaConfig['BAZ_COORD_ORIGINE_IMAGE_MARQUEUR'] = getConfigValue('BAZ_COORD_ORIGINE_IMAGE_MARQUEUR', '0,0', $wakkaConfig);
//
// $wakkaConfig['BAZ_COORD_ARRIVEE_IMAGE_MARQUEUR'] = getConfigValue('BAZ_COORD_ARRIVEE_IMAGE_MARQUEUR', '0,20', $wakkaConfig);
//
// // image ombre marqueur
// $wakkaConfig['BAZ_IMAGE_OMBRE_MARQUEUR'] = getConfigValue('BAZ_IMAGE_OMBRE_MARQUEUR', 'tools/bazar/presentation/images/marker_shadow.png', $wakkaConfig);
//
// $wakkaConfig['BAZ_DIMENSIONS_IMAGE_OMBRE_MARQUEUR'] = getConfigValue('BAZ_DIMENSIONS_IMAGE_OMBRE_MARQUEUR', '22, 20', $wakkaConfig);
//
// $wakkaConfig['BAZ_COORD_ORIGINE_IMAGE_OMBRE_MARQUEUR'] = getConfigValue('BAZ_COORD_ORIGINE_IMAGE_OMBRE_MARQUEUR', '0,0', $wakkaConfig);
//
// $wakkaConfig['BAZ_COORD_ARRIVEE_IMAGE_OMBRE_MARQUEUR'] = getConfigValue('BAZ_COORD_ARRIVEE_IMAGE_OMBRE_MARQUEUR', '0,20', $wakkaConfig);
//

// // Controles carte
// $wakkaConfig['BAZ_AFFICHER_NAVIGATION'] = getConfigValue('BAZ_AFFICHER_NAVIGATION', 'true', $wakkaConfig);
//
// // true ou false
// $wakkaConfig['BAZ_AFFICHER_CHOIX_CARTE'] = getConfigValue('BAZ_AFFICHER_CHOIX_CARTE', 'true', $wakkaConfig);
//
// // true ou false
// $wakkaConfig['BAZ_AFFICHER_ECHELLE'] = getConfigValue('BAZ_AFFICHER_ECHELLE', 'false', $wakkaConfig);
//
//
// // SMALL ou ZOOM_PAN ou ANDROID ou DEFAULT
// $wakkaConfig['BAZ_STYLE_NAVIGATION'] = getConfigValue('BAZ_STYLE_NAVIGATION', 'ZOOM_PAN', $wakkaConfig);
//
// // HORIZONTAL_BAR ou DROPDOWN_MENU ou DEFAULT
// $wakkaConfig['BAZ_STYLE_CHOIX_CARTE'] = getConfigValue('BAZ_STYLE_CHOIX_CARTE', 'DROPDOWN_MENU', $wakkaConfig);
//
// // marqueur de base
// $wakkaConfig['BAZ_MAP_DEFAULT_MARKER_IMAGE'] = getConfigValue('BAZ_MAP_DEFAULT_MARKER_IMAGE', 'minimarkers/marker_rounded_red.png', $wakkaConfig);
// $wakkaConfig['BAZ_MAP_DEFAULT_MARKER_SIZE_WIDTH'] = getConfigValue('BAZ_MAP_DEFAULT_MARKER_SIZE_WIDTH', '16', $wakkaConfig);
// $wakkaConfig['BAZ_MAP_DEFAULT_MARKER_SIZE_HEIGHT'] = getConfigValue('BAZ_MAP_DEFAULT_MARKER_SIZE_HEIGHT', '16', $wakkaConfig);
// $wakkaConfig['BAZ_MAP_DEFAULT_MARKER_POINTER_X'] = getConfigValue('BAZ_MAP_DEFAULT_MARKER_POINTER_X', '8', $wakkaConfig);
// $wakkaConfig['BAZ_MAP_DEFAULT_MARKER_POINTER_Y'] = getConfigValue('BAZ_MAP_DEFAULT_MARKER_POINTER_Y', '16', $wakkaConfig);

// Choix du look du template par défaut $GLOBALS['wiki']->config['default_bazar_template']
$wakkaConfig['default_bazar_template'] = getConfigValue('default_bazar_template', 'liste_accordeon.tpl.html', $wakkaConfig);

// les passages de parametres query en get affectent ils les resultats de fiches croisees avec checkboxfiche?
$wakkaConfig['global_query'] = getConfigValue('global_query', true, $wakkaConfig);

// Fonctions ajoutées par bazar a la classe Wiki
$wikiClasses[] = 'Bazar';

// fonctions supplementaires a ajouter la classe wiki
$fp = @fopen('tools/bazar/libs/bazar.class.inc.php', 'r');
$contents = fread($fp, filesize('tools/bazar/libs/bazar.class.inc.php'));
fclose($fp);
$wikiClassesContent [] = str_replace('<?php', '', $contents);
