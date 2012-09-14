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
// CVS : $Id: bazar.php,v 1.15 2011-07-13 10:33:23 mrflos Exp $
/**
* bazar.php
*
* Description :
*
*@package wkbazar
//Auteur original :
*@author        Florian SCHMITT <florian@outils-reseaux.org>
*@copyright     Florian SCHMITT 2008
*@version       $Revision: 1.15 $ $Date: 2011-07-13 10:33:23 $
// +------------------------------------------------------------------------------------------------------+
*/

// +------------------------------------------------------------------------------------------------------+
// |                                            ENTETE du PROGRAMME                                       |
// +------------------------------------------------------------------------------------------------------+


if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}

//recuperation des parametres
$action = $this->GetParameter(BAZ_VARIABLE_ACTION);
if (!empty($action)) {
	$_GET[BAZ_VARIABLE_ACTION] = $action;
}

$vue = $this->GetParameter(BAZ_VARIABLE_VOIR);
if (!empty($vue) && !isset($_GET[BAZ_VARIABLE_VOIR])) {
	$_GET[BAZ_VARIABLE_VOIR] = $vue;
}
//si rien n'est donne, on met la vue de consultation
elseif (!isset($_GET[BAZ_VARIABLE_VOIR])) {
	$_GET[BAZ_VARIABLE_VOIR] = BAZ_VOIR_CONSULTER;
}

//ordre d'affichage des fiches : chronologique ou alphabetique
$GLOBALS['_BAZAR_']['tri'] = $this->GetParameter('tri');
if (empty($GLOBALS['_BAZAR_']['tri'])) {
	$GLOBALS['_BAZAR_']['tri'] = 'chronologique';
}

$GLOBALS['_BAZAR_']['affiche_menu'] = $this->GetParameter("voirmenu");

if (isset($_REQUEST['form_id'])) {
	$GLOBALS['_BAZAR_']['form_id'] = $_REQUEST['form_id'];
} else {
	$GLOBALS['_BAZAR_']['form_id'] = $this->GetParameter("form");
}
if (empty($GLOBALS['_BAZAR_']['form_id'])) {
	//on indique qu'il n'y a pas de type de formulaire choisi, et on recupere eventuellement la categorie
	$GLOBALS['_BAZAR_']['form_id'] = NULL;
	$GLOBALS['_BAZAR_']['form_category'] = $this->GetParameter("category");
	if (empty($GLOBALS['_BAZAR_']['form_category'])) {
		$GLOBALS['_BAZAR_']['form_category'] = NULL;
	}
} else {
	//si un type de fiche est passe en parametres, on prend toutes les informations
	$tab_nature = baz_valeurs_formulaire($GLOBALS['_BAZAR_']['form_id']);
	$GLOBALS['_BAZAR_']['form_name'] = $tab_nature['form_name'];
	$GLOBALS['_BAZAR_']['terms_of_use'] = $tab_nature['terms_of_use'];
	$GLOBALS['_BAZAR_']['field'] = $tab_nature['field'];
	$GLOBALS['_BAZAR_']['class'] = $GLOBALS['_BAZAR_']['form_id'];
	$GLOBALS['_BAZAR_']['form_category'] = $tab_nature['form_category'];
}

//si un identifiant fiche est renseigné, on récupère toutes les valeurs associées
if (isset($_REQUEST['id_fiche'])) {
	$GLOBALS['_BAZAR_']['id_fiche'] = $_REQUEST['id_fiche'];
	
	//on récupère les valeurs de la fiche
	$GLOBALS['_BAZAR_']['entry_values'] = baz_entry_values($GLOBALS['_BAZAR_']['id_fiche']);
	if ($GLOBALS['_BAZAR_']['entry_values']) {
		$GLOBALS['_BAZAR_']['form_id'] = $GLOBALS['_BAZAR_']['entry_values']['form_id'];
		$_REQUEST['form_id'] = $GLOBALS['_BAZAR_']['form_id'];
		//on récupère aussi les valeurs générales du type de fiche aussi
		$tab_nature = baz_valeurs_formulaire($GLOBALS['_BAZAR_']['form_id']);
		$GLOBALS['_BAZAR_']['form_name'] = $tab_nature['form_name'];
		$GLOBALS['_BAZAR_']['terms_of_use'] = $tab_nature['terms_of_use'];
		$GLOBALS['_BAZAR_']['field'] = $tab_nature['field'];
		$GLOBALS['_BAZAR_']['class'] = $GLOBALS['_BAZAR_']['form_id'];
		$GLOBALS['_BAZAR_']['form_category'] = $tab_nature['form_category'];
	} else {
		$GLOBALS['_BAZAR_']['id_fiche'] = NULL;
		exit('<div class="error_box">la fiche que vous recherchez n\'existe plus (sans doute a t\'elle &eacute;t&eacute; supprim&eacute;e entre temps)...</div>');
	}
}

//utilisateur
$GLOBALS['_BAZAR_']['nomwiki'] = $GLOBALS['wiki']->GetUser();

//variable d'affichage du bazar
$output = '';
// +------------------------------------------------------------------------------------------------------+
// |                                            CORPS du PROGRAMME                                        |
// +------------------------------------------------------------------------------------------------------+

if ($GLOBALS['_BAZAR_']['affiche_menu']!='0') {
	$output .= baz_afficher_menu();
}

if (isset($_GET['message'])) {
	$output .= '<div class="alert alert-info">';
	if ($_GET['message']=='ajout_ok') $output.= BAZ_FICHE_ENREGISTREE;
	if ($_GET['message']=='modif_ok') $output.= BAZ_FICHE_MODIFIEE;
	if ($_GET['message']=='delete_ok') $output.= BAZ_FICHE_SUPPRIMEE;
	$output .= '</div>'."\n";
}

if (isset ($_GET[BAZ_VARIABLE_VOIR])) {
		switch ($_GET[BAZ_VARIABLE_VOIR]) {
			case BAZ_VOIR_CONSULTER:
				if (isset ($_GET[BAZ_VARIABLE_ACTION])) {
					switch ($_GET[BAZ_VARIABLE_ACTION]) {
						case BAZ_MOTEUR_RECHERCHE : $output .= baz_rechercher(); break;
						case BAZ_VOIR_FICHE : $output .= ( isset($GLOBALS['_BAZAR_']['entry_values']) ? baz_voir_fiche(1, $GLOBALS['_BAZAR_']['entry_values']) : baz_voir_fiche(1, $GLOBALS['_BAZAR_']['id_fiche'])); break;
					}
				}
				else
				{
					$output .= baz_rechercher();
				}
				break;
			case BAZ_VOIR_MES_FICHES :
				$output .= baz_afficher_liste_fiches_utilisateur();
				break;
			case BAZ_VOIR_S_ABONNER :
				if (isset ($_GET[BAZ_VARIABLE_ACTION]))
				{
					switch ($_GET[BAZ_VARIABLE_ACTION])
					{
						case BAZ_LISTE_RSS : $output .= baz_liste_rss(); break;
						case BAZ_VOIR_FLUX_RSS : exit(baz_afficher_flux_rss());break;
					}
				}
				else
				{
					$output .= baz_liste_rss();
				}
				break;
			case BAZ_VOIR_SAISIR :
				if (isset ($_GET[BAZ_VARIABLE_ACTION]))
				{
					switch ($_GET[BAZ_VARIABLE_ACTION])
					{						
						case BAZ_ACTION_SUPPRESSION : $output .= baz_suppression($_GET['id_fiche']); break;
						case BAZ_ACTION_PUBLIER : $output .= publier_fiche(1).baz_voir_fiche(1, $GLOBALS['_BAZAR_']['id_fiche']); break;
						case BAZ_ACTION_PAS_PUBLIER : $output .= publier_fiche(0).baz_voir_fiche(1, $GLOBALS['_BAZAR_']['id_fiche']); break;
						default : $output .= baz_formulaire($_GET[BAZ_VARIABLE_ACTION]) ;break;
					}
				}
				else
				{
					$_GET[BAZ_VARIABLE_ACTION] = BAZ_CHOISIR_TYPE_FICHE;
					$output .= baz_formulaire($_GET[BAZ_VARIABLE_ACTION]);
				}
				break;
			case BAZ_VOIR_FORMULAIRE :
				$output .= baz_gestion_formulaire();
				break;
			case BAZ_VOIR_LISTES :
				$output .= baz_gestion_listes();
				break;
			case BAZ_VOIR_ADMIN:
				if (isset($_GET[BAZ_VARIABLE_ACTION]))
				{
					$output .= baz_formulaire($_GET[BAZ_VARIABLE_ACTION]) ;
				}
				else
				{
					$output .= fiches_a_valider();
				}
				break;
			case BAZ_VOIR_GESTION_DROITS:
				$output .= baz_gestion_droits();
				break;
			case BAZ_VOIR_IMPORTER:
				$output .= baz_afficher_formulaire_import();
				break;
			case BAZ_VOIR_EXPORTER:
				$output .= baz_afficher_formulaire_export();
				break;	
			default :
				$output .= baz_rechercher();
		}
}

//affichage de la page
echo $output ;
?>
