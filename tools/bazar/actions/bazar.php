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
// CVS : $Id: bazar.php,v 1.13 2010-12-15 11:15:45 ddelon Exp $
/**
* bazar.php
*
* Description :
*
*@package wkbazar
//Auteur original :
*@author        Florian SCHMITT <florian@outils-reseaux.org>
*@copyright     Florian SCHMITT 2008
*@version       $Revision: 1.13 $ $Date: 2010-12-15 11:15:45 $
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

//si un identifiant fiche est renseigné, on récupère toutes les valeurs associées
if (isset($_REQUEST['id_fiche'])) {
    $GLOBALS['_BAZAR_']['id_fiche'] = $_REQUEST['id_fiche'];

    //on récupère les valeurs de la fiche
    $GLOBALS['_BAZAR_']['valeurs_fiche'] = baz_valeurs_fiche($GLOBALS['_BAZAR_']['id_fiche']);
    if ($GLOBALS['_BAZAR_']['valeurs_fiche']) {
        $GLOBALS['_BAZAR_']['id_typeannonce'] = $GLOBALS['_BAZAR_']['valeurs_fiche']['id_typeannonce'];
        //on récupère aussi les valeurs générales du type de fiche aussi
        $tab_nature = baz_valeurs_type_de_fiche($GLOBALS['_BAZAR_']['id_typeannonce']);
        $GLOBALS['_BAZAR_']['typeannonce'] = $tab_nature['bn_label_nature'];
        $GLOBALS['_BAZAR_']['condition'] = $tab_nature['bn_condition'];
        $GLOBALS['_BAZAR_']['template'] = $tab_nature['bn_template'];
        $GLOBALS['_BAZAR_']['commentaire'] = $tab_nature['bn_commentaire'];
        $GLOBALS['_BAZAR_']['appropriation'] = $tab_nature['bn_appropriation'];
        $GLOBALS['_BAZAR_']['class'] = $tab_nature['bn_label_class'];
        $GLOBALS['_BAZAR_']['categorie_nature'] = $tab_nature['bn_type_fiche'];
    } else {
        $GLOBALS['_BAZAR_']['id_fiche'] = NULL;
        exit('<div class="error_box">la fiche que vous recherchez n\'existe plus (sans doute a-t-elle &eacute;t&eacute; supprim&eacute;e entre temps)...</div>');
    }
}

// sinon on récupère les paramètres passés par l'action
else {
    $GLOBALS['_BAZAR_']['id_fiche'] = NULL;
    $GLOBALS['_BAZAR_']['id_typeannonce'] = $this->GetParameter("idtypeannonce");

    if (empty($GLOBALS['_BAZAR_']['id_typeannonce'])) {
        //si la valeur n'est pas passée en paramètre, on vérifie si l'application ne l'a pas initialisé
        if (isset($_REQUEST['id_typeannonce'])) {
            $GLOBALS['_BAZAR_']['id_typeannonce'] = $_REQUEST['id_typeannonce'];
            $tab_nature = baz_valeurs_type_de_fiche($GLOBALS['_BAZAR_']['id_typeannonce']);
            $GLOBALS['_BAZAR_']['typeannonce'] = $tab_nature['bn_label_nature'];
            $GLOBALS['_BAZAR_']['condition'] = $tab_nature['bn_condition'];
            $GLOBALS['_BAZAR_']['template'] = $tab_nature['bn_template'];
            $GLOBALS['_BAZAR_']['commentaire'] = $tab_nature['bn_commentaire'];
            $GLOBALS['_BAZAR_']['appropriation'] = $tab_nature['bn_appropriation'];
            $GLOBALS['_BAZAR_']['class'] = $tab_nature['bn_label_class'];
            $GLOBALS['_BAZAR_']['categorie_nature'] = $tab_nature['bn_type_fiche'];

        }
        //on met sur "toutes" sinon
        else {
            $GLOBALS['_BAZAR_']['id_typeannonce'] = 'toutes';
            $categorie_nature = $this->GetParameter("categorienature");
            if (!empty($categorie_nature)) {
                $GLOBALS['_BAZAR_']['categorie_nature'] = $categorie_nature;
            }
            //si rien n'est donne, on affiche toutes les categories
            else {
                $GLOBALS['_BAZAR_']['categorie_nature'] = 'toutes';
            }

        }
        $GLOBALS['_BAZAR_']['choix_categorie'] = true;
    } else {
        $GLOBALS['_BAZAR_']['choix_categorie'] = false;
    }

    //si l'on connait le type de fiche, on prend toutes les infos
    if ($GLOBALS['_BAZAR_']['id_typeannonce']!='toutes') {
        $_REQUEST['id_typeannonce'] = $GLOBALS['_BAZAR_']['id_typeannonce'];
        $tab_nature = baz_valeurs_type_de_fiche($GLOBALS['_BAZAR_']['id_typeannonce']);
        $GLOBALS['_BAZAR_']['typeannonce'] = $tab_nature['bn_label_nature'];
        $GLOBALS['_BAZAR_']['condition'] = $tab_nature['bn_condition'];
        $GLOBALS['_BAZAR_']['template'] = $tab_nature['bn_template'];
        $GLOBALS['_BAZAR_']['commentaire'] = $tab_nature['bn_commentaire'];
        $GLOBALS['_BAZAR_']['appropriation'] = $tab_nature['bn_appropriation'];
        $GLOBALS['_BAZAR_']['class'] = $tab_nature['bn_label_class'];
        $GLOBALS['_BAZAR_']['categorie_nature'] = $tab_nature['bn_type_fiche'];
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
    $output .= '<div class="alert alert-success">'."\n".'<a data-dismiss="alert" class="close" type="button">&times;</a>';
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
                        case BAZ_MOTEUR_RECHERCHE : $output .= baz_rechercher($GLOBALS['_BAZAR_']['id_typeannonce'],$GLOBALS['_BAZAR_']['categorie_nature']); break;
                        case BAZ_VOIR_FICHE : $output .= ( isset($GLOBALS['_BAZAR_']['valeurs_fiche']) ? baz_voir_fiche(1, $GLOBALS['_BAZAR_']['valeurs_fiche']) : baz_voir_fiche(1, $GLOBALS['_BAZAR_']['id_fiche'])); break;
                    }
                } else {
                    $output .= baz_rechercher($GLOBALS['_BAZAR_']['id_typeannonce'],$GLOBALS['_BAZAR_']['categorie_nature']);
                }
                break;
            case BAZ_VOIR_MES_FICHES :
                $output .= baz_afficher_liste_fiches_utilisateur();
                break;
            case BAZ_VOIR_S_ABONNER :
                if (isset ($_GET[BAZ_VARIABLE_ACTION])) {
                    switch ($_GET[BAZ_VARIABLE_ACTION]) {
                        case BAZ_LISTE_RSS : $output .= baz_liste_rss(); break;
                        case BAZ_VOIR_FLUX_RSS : exit(baz_afficher_flux_rss());break;
                    }
                } else {
                    $output .= baz_liste_rss();
                }
                break;
            case BAZ_VOIR_SAISIR :
                if (isset ($_GET[BAZ_VARIABLE_ACTION])) {
                    switch ($_GET[BAZ_VARIABLE_ACTION]) {
                        case BAZ_ACTION_SUPPRESSION : $output .= baz_suppression($_GET['id_fiche']); break;
                        case BAZ_ACTION_PUBLIER : $output .= publier_fiche(1).baz_voir_fiche(1, $GLOBALS['_BAZAR_']['id_fiche']); break;
                        case BAZ_ACTION_PAS_PUBLIER : $output .= publier_fiche(0).baz_voir_fiche(1, $GLOBALS['_BAZAR_']['id_fiche']); break;
                        default : $output .= baz_formulaire($_GET[BAZ_VARIABLE_ACTION]) ;break;
                    }
                } else {
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
                if (isset($_GET[BAZ_VARIABLE_ACTION])) {
                    $output .= baz_formulaire($_GET[BAZ_VARIABLE_ACTION]) ;
                } else {
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
                $output .= baz_rechercher($GLOBALS['_BAZAR_']['id_typeannonce']);
        }
}
//affichage de la page
echo $output ;

/* +--Fin du code ----------------------------------------------------------------------------------------+
*
* $Log: bazar.php,v $
* Revision 1.13  2010-12-15 11:15:45  ddelon
* nom du parametre voir  depuis la constante
*
* Revision 1.12  2010-12-01 17:01:38  mrflos
* amÃ©lioration de l'intÃ©gration dans wiki :
* les listes sont maintenant des PageWiki
* les fiches sont aussi des PageWiki
* ajout des parties apparaissant sous certaines conditions seulement
* import export csv
* mise a jour jquery ui et bazar.css
* handler rss
*
* Revision 1.11  2010-10-26 14:18:41  ddelon
* Remaniement des constantes
*
* Revision 1.10  2010-10-26 10:42:11  mrflos
* snapshot avant coding party
*
* Revision 1.9  2010-06-02 08:48:51  mrflos
* commit de transition
*
* Revision 1.8  2010/05/03 15:59:45  mrflos
* Un bazar par prÃ©fixe de table
*
* Revision 1.7  2010/05/03 08:36:15  mrflos
* maj gÃ©nÃ©rale des fonctions de bazar
*
* Revision 1.6  2010/03/04 14:19:03  mrflos
* nouvelle version bazar
*
* Revision 1.5  2009/09/09 15:36:37  mrflos
* maj css
* ajout de la google api v3
* possibilitÃ© d'insÃ©rer des utilisateurs wikini par bazar
* installation automatique du fichier sql avec type d'annonces par dÃ©faut
*
* Revision 1.4  2009/08/01 17:01:59  mrflos
* nouvelle action bazarcalendrier, correction bug typeannonce, validitÃ© html amÃ©liorÃ©e
*
* Revision 1.3  2008/09/09 12:46:42  mrflos
* sÃ©curitÃ©: seuls les identifies peuvent supprimer une fiche ou un type de fiche
*
* Revision 1.2  2008/08/27 13:18:57  mrflos
* maj gÃ©nÃ©rale
*
* Revision 1.1  2008/07/07 18:00:39  mrflos
* maj carto plus calendrier
*
* Revision 1.2  2008/03/06 00:15:40  mrflos
* correction des bugs bazar, ajout de fichiers d'images
*
* Revision 1.1  2008/02/18 09:12:47  mrflos
* Premiere release de 3 extensions en version alpha (bugs nombreux!) des plugins bazar, e2gallery, et templates
*
* Revision 1.1  2006/12/13 17:06:36  florian
* Ajout de l'applette bazar.
*
*
* +-- Fin du code ----------------------------------------------------------------------------------------+
*/
