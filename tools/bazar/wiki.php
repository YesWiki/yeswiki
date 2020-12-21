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
require_once BAZ_CHEMIN.'libs/bazar.fonct.retrocompatibility.php';

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
define('BAZ_VOIR_IMPORTER', 'importer');
define('BAZ_VOIR_EXPORTER', 'exporter');

// Second : actions du choix de premier niveau.

define('BAZ_MOTEUR_RECHERCHE', 'recherche');
define('BAZ_CHOISIR_TYPE_FICHE', 'choisir_type_fiche');
 //
 // Modifier le formulaire de creation des fiches
define('BAZ_VOIR_FICHE', 'voir_fiche');
define('BAZ_ACTION_NOUVEAU', 'saisir_fiche');
define('BAZ_ACTION_NOUVEAU_V', 'sauver_fiche');
 // Creation apres validation
define('BAZ_ACTION_MODIFIER', 'modif_fiche');
define('BAZ_ACTION_MODIFIER_V', 'modif_sauver_fiche');
 // Modification apres validation
define('BAZ_ACTION_NOUVELLE_LISTE', 'saisir_liste');
 // Creation apres validation
define('BAZ_ACTION_MODIFIER_LISTE', 'modif_liste');
 // Modification apres validation
define('BAZ_ACTION_SUPPRIMER_LISTE', 'supprimer_liste');
define('BAZ_ACTION_SUPPRESSION', 'supprimer');
define('BAZ_ACTION_PUBLIER', 'publier');
 // Valider la fiche
define('BAZ_ACTION_PAS_PUBLIER', 'pas_publier');
 // Invalider la fiche
