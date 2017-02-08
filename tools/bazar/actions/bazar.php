<?php

/*vim: set expandtab tabstop=4 shiftwidth=4: */

// +------------------------------------------------------------------------------------------------------+
// | PHP version 5.1                                                                                      |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 1999-2006 Kaleidos-coop.org                                                            |
// +------------------------------------------------------------------------------------------------------+
// | This file is part of wkbazar.                                                                        |
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


/**
 * bazar.php.
 *
 * Description :
 *
 *
 *@author        Florian SCHMITT <florian@outils-reseaux.org>
 *@copyright     Florian SCHMITT 2008
 *
 *@version       $Revision: 1.13 $ $Date: 2010-12-15 11:15:45 $
 *  +------------------------------------------------------------------------------------------------------+
 */

// +------------------------------------------------------------------------------------------------------+
// |                                            ENTETE du PROGRAMME                                       |
// +------------------------------------------------------------------------------------------------------+

if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

// recuperation des parametres
$GLOBALS['params'] = getAllParameters($this);


// +------------------------------------------------------------------------------------------------------+
// |                                            CORPS du PROGRAMME                                        |
// +------------------------------------------------------------------------------------------------------+

// si c'est demandé, on affiche le menu
if ($GLOBALS['params']['voirmenu'] != '0') {
    $menuitems = array_map('trim', explode(',', $GLOBALS['params']['voirmenu']));
    echo baz_afficher_menu($menuitems);
}

// on affiche les infos correspondantes à la vue
switch ($GLOBALS['params']['vue']) {
    case BAZ_VOIR_CONSULTER:
        switch ($GLOBALS['params']['action']) {
            case BAZ_MOTEUR_RECHERCHE:
                echo baz_rechercher(
                    $GLOBALS['params']['idtypeannonce'],
                    $GLOBALS['params']['categorienature']
                );
                break;
            case BAZ_VOIR_FICHE:
                if (isset($_REQUEST['id_fiche'])) {
                    $fiche = baz_valeurs_fiche($_REQUEST['id_fiche']);
                    if (!is_array($fiche)) {
                        echo '<div class="alert alert-danger">'
                            ._t('BAZ_PAS_DE_FICHE_AVEC_CET_ID').' : '.$_REQUEST['id_fiche'].'</div>';
                    } else {
                        echo baz_voir_fiche(1, $fiche);
                    }
                } else {
                    echo '<div class="alert alert-danger">'._t('BAZ_PAS_D_ID_DE_FICHE_INDIQUEE').'</div>';
                }
                break;
            default:
                echo baz_rechercher(
                    isset($_REQUEST['id_typeannonce']) ?
                    $_REQUEST['id_typeannonce'] : $GLOBALS['params']['idtypeannonce'],
                    $GLOBALS['params']['categorienature']
                );
                break;
        }
        break;
    case BAZ_VOIR_MES_FICHES:
        echo baz_afficher_liste_fiches_utilisateur();
        break;
    case BAZ_VOIR_S_ABONNER:
        switch ($GLOBALS['params']['action']) {
            case BAZ_LISTE_RSS:
                echo baz_liste_rss();
                break;
            case BAZ_VOIR_FLUX_RSS:
                exit(baz_afficher_flux_rss());
                break;
            default:
                echo baz_liste_rss();
                break;
        }
        break;
    case BAZ_VOIR_SAISIR:
        switch ($GLOBALS['params']['action']) {
            case BAZ_ACTION_SUPPRESSION:
                echo baz_suppression($_REQUEST['id_fiche']);
                break;
            case BAZ_ACTION_PUBLIER:
                echo publier_fiche(1).baz_voir_fiche(1, $_REQUEST['id_fiche']);
                break;
            case BAZ_ACTION_PAS_PUBLIER:
                echo publier_fiche(0).baz_voir_fiche(1, $_REQUEST['id_fiche']);
                break;
            case BAZ_ACTION_NOUVEAU:
                // Affichage du formulaire du saisie d'une' fiche
                echo baz_formulaire(BAZ_ACTION_NOUVEAU);
                break;
            case BAZ_ACTION_MODIFIER:
                // Affichage du formulaire de modification d'une fiche
                echo baz_formulaire(BAZ_ACTION_MODIFIER);
                break;
            case BAZ_ACTION_NOUVEAU_V:
                // Affichage du formulaire du saisie d'une' fiche
                echo baz_formulaire(BAZ_ACTION_NOUVEAU_V);
                break;
            case BAZ_ACTION_MODIFIER_V:
                // Affichage du formulaire de modification d'une fiche
                echo baz_formulaire(BAZ_ACTION_MODIFIER_V);
                break;
            default:
                // Choix du type de fiche à saisir
                echo baz_formulaire(BAZ_CHOISIR_TYPE_FICHE);
                break;
        }
        break;
    case BAZ_VOIR_FORMULAIRE:
        echo baz_gestion_formulaire();
        break;
    case BAZ_VOIR_LISTES:
        echo baz_gestion_listes();
        break;
    case BAZ_VOIR_GESTION_DROITS:
        echo baz_gestion_droits();
        break;
    case BAZ_VOIR_IMPORTER:
        echo baz_afficher_formulaire_import();
        break;
    case BAZ_VOIR_EXPORTER:
        echo baz_afficher_formulaire_export();
        break;
    default:
        echo baz_rechercher(
            isset($_REQUEST['id_typeannonce']) ? $_REQUEST['id_typeannonce'] : $GLOBALS['params']['idtypeannonce']
        );
        break;
}
