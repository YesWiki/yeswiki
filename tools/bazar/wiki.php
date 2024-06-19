<?php

//chemin relatif d'acces au bazar
define('BAZ_CHEMIN', 'tools/bazar/');
define('BAZ_CHEMIN_UPLOAD', 'files/');

//principales fonctions de bazar
require_once BAZ_CHEMIN . 'libs/bazar.fonct.php';
require_once BAZ_CHEMIN . 'libs/bazar.fonct.misc.php';
require_once BAZ_CHEMIN . 'libs/bazar.fonct.retrocompatibility.php';

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
