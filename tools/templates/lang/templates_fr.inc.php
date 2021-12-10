<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5                                                                                        |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2012 Outils-Réseaux (accueil@outils-reseaux.org)                                       |
// +------------------------------------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or                                        |
// | modify it under the terms of the GNU Lesser General Public                                           |
// | License as published by the Free Software Foundation; either                                         |
// | version 2.1 of the License, or (at your option) any later version.                                   |
// |                                                                                                      |
// | This library is distributed in the hope that it will be useful,                                      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
// | Lesser General Public License for more details.                                                      |
// |                                                                                                      |
// | You should have received a copy of the GNU Lesser General Public                                     |
// | License along with this library; if not, write to the Free Software                                  |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
//
/**
* Fichier de traduction en francais de l'extension Templates
*
*@package       templates
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2012 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge(
    $GLOBALS['translations'],
    array(
    'TEMPLATE_ACTION' => 'Action',
    'TEMPLATE_FILE_NOT_FOUND' => 'Template non trouvé',

    // actions/button.php
    'TEMPLATE_ACTION_BUTTON' => 'Action {{button ...}}',
    'TEMPLATE_LINK_PARAMETER_REQUIRED' => 'param&egrave;tre "link" obligatoire',
    'TEMPLATE_RSS_LAST_CHANGES' => 'Flux RSS des derni&egrave;res pages modifi&eacute;es',
    'TEMPLATE_RSS_LAST_COMMENTS' => 'Flux RSS des derniers commentaires',
    'TEMPLATE_NO_DEFAULT_THEME' => 'Les fichiers du template par d&eacute;faut ont disparu, l\'utilisation des templates est impossible.<br />Veuillez r&eacute;installer le tools template ou contacter l\'administrateur du site',
    'TEMPLATE_CUSTOM_GRAPHICS' => 'Apparence de la page',
    'TEMPLATE_SAVE' => 'Sauver',
    'TEMPLATE_APPLY' => 'Appliquer',
    'TEMPLATE_APPLY_ALL' => 'Appliquer pour tout le site',
    'TEMPLATE_CANCEL' => 'Annuler',
    'TEMPLATE_THEME' => 'Th&egrave;me',
    'TEMPLATE_SQUELETTE' => 'Squelette',
    'TEMPLATE_STYLE' => 'Style',
    'TEMPLATE_PRESET' => 'Preset',
    'TEMPLATE_DEFAULT_PRESET' => 'Preset par défaut',
    'TEMPLATE_BG_IMAGE' => 'Image de fond',
    'TEMPLATE_ERROR_NO_DATA' => 'ERREUR : rien &agrave; ajouter dans les meta-donn&eacute;es.',
    'TEMPLATE_ERROR_NO_ACCESS' => 'ERREUR : pas les droits d\'acc&eacute;s.',

    // barre de redaction
    'TEMPLATE_VIEW_PAGE' => 'Voir la page',
    'TEMPLATE_EDIT' => '&Eacute;diter',
    'TEMPLATE_EDIT_THIS_PAGE' => '&Eacute;diter la page',
    'TEMPLATE_WIKINAME_IS_NOT_A_PAGE' => 'Ce ChatMot n\'est pas une page',
    'TEMPLATE_CLICK_TO_SEE_REVISIONS' => 'Les derni&egrave;res modifications de la page',
    'TEMPLATE_LAST_UPDATE' => 'Dernière édition :',
    'TEMPLATE_OWNER' => 'Propri&eacute;taire',
    'TEMPLATE_YOU' => 'vous',
    'TEMPLATE_NO_OWNER' => 'Pas de propri&eacute;taire',
    'TEMPLATE_CLAIM' => 'Appropriation',
    'TEMPLATE_CLICK_TO_CHANGE_PERMISSIONS' => '&Eacute;diter les permissions de la page',
    'TEMPLATE_PERMISSIONS' => 'Permissions',
    'TEMPLATE_DELETE' => 'Supprimer',
    'TEMPLATE_DELETE_PAGE' => 'Supprimer la page',
    'TEMPLATE_CLICK_TO_SEE_REFERENCES' => 'Les URLs faisant r&eacute;f&eacute;rence &agrave; la page',
    'TEMPLATE_REFERENCES' => 'R&eacute;f&eacute;rences',
    'TEMPLATE_SLIDESHOW_MODE' => 'Lancer cette page en mode diaporama.',
    'TEMPLATE_SLIDESHOW' => 'Diaporama',
    'TEMPLATE_DYNAMIC_SLIDESHOW' => 'Diaporama dynamique',
    'TEMPLATE_CLASSIC_SLIDESHOW' => 'Diaporama classique',
    'TEMPLATE_SEE_SHARING_OPTIONS' => 'Partager la page',
    'TEMPLATE_SHARE' => 'Partager',

    // formatage des dates
    'TEMPLATE_DATE_FORMAT' => 'd M Y',

    // recherche
    'TEMPLATE_SEARCH_INPUT_TITLE' => 'Rechercher dans YesWiki [alt-shift-C]',
    'TEMPLATE_SEARCH_BUTTON_TITLE' => 'Rechercher les pages comportant ce texte.',
    'TEMPLATE_SEARCH_PLACEHOLDER' => 'Rechercher...',
    'TEMPLATE_SEARCH' => 'Rechercher',

    // handler widget
    'TEMPLATE_WIDGET_TITLE' => 'Widget : int&eacute;grer le contenu de cette page ailleurs',
    'TEMPLATE_WIDGET_COPY_PASTE' => 'Copier-collez le code HTML ci-dessus pour int&eacute;grer le contenu tel qu\'il apparait ci dessous.',

    // handler share
    'TEMPLATE_SHARE_INCLUDE_CODE' => 'Code d\'int&eacute;gration de contenu dans une page HTML',
    'TEMPLATE_SHARE_MUST_READ' => 'A lire : ',
    'TEMPLATE_SHARE_FACEBOOK' => 'Partager sur Facebook',
    'TEMPLATE_SHARE_TWITTER' => 'Partager sur Twitter',
    'TEMPLATE_SHARE_NETVIBES' => 'Partager sur Netvibes',
    'TEMPLATE_SHARE_DELICIOUS' => 'Partager sur Delicious',
    'TEMPLATE_SHARE_GOOGLEREADER' => 'Partager sur Google Reader',
    'TEMPLATE_SHARE_MAIL' => 'Envoyer le contenu de cette page par mail',
    'TEMPLATE_ADD_SHARE_BUTTON' => 'Ajouter un bouton de partage en haut &agrave; droite de la page',
    'TEMPLATE_ADD_EDIT_BAR' => 'Ajouter la barre d\'&eacute;dition en bas de page',

    // handler diaporama
    'TEMPLATE_NO_ACCESS_TO_PAGE' => 'Vous n\'avez pas le droit d\'acc&eacute;der &agrave; cette page.',
    'TEMPLATE_PAGE_DOESNT_EXIST' => 'Page non existante',
    'PAGE_CANNOT_BE_SLIDESHOW' => 'La page ne peut pas &ecirc;tre d&eacute;coup&eacute;e en diapositives (pas de titres niveau 2)',

    // handler edit
    'TEMPLATE_CUSTOM_PAGE' => 'Pr&eacute;f&eacute;rences de la page',
    'TEMPLATE_PAGE_PREFERENCES' => 'Param&egrave;tres de la page',
    'PAGE_LANGUAGE' => 'Langue de la page',
    'CHOOSE_PAGE_FOR' => 'Choisir une page pour',
    'HORIZONTAL_MENU_PAGE' => 'le menu horizontal',
    'FAST_ACCESS_RIGHT_PAGE' => 'les raccourcis en haut &agrave; droite',
    'HEADER_PAGE' => 'l\'ent&ecirc;te (bandeau)',
    'FOOTER_PAGE' => 'le pied de page',
    'FOR_2_OR_3_COLUMN_THEMES' => 'Pour les th&egrave;mes &agrave; 2 ou 3 colonnes',
    'VERTICAL_MENU_PAGE' => 'le menu vertical',
    'RIGHT_COLUMN_PAGE' => 'la colonne de droite',

    // actions/yeswikiversion.php
    'RUNNING_WITH' => 'Galope sous',

    'TEMPLATE_ACTION_SECTION' => 'Action {{section ...}}',
    'TEMPLATE_ELEM_SECTION_NOT_CLOSED' => 'l\'action {{section ...}} doit &ecirc;tre ferm&eacute;e par une action {{end elem="section"}}',


    'TEMPLATE_NO_THEME_FILES' => 'Fichiers du th&egrave;me manquants',
    'TEMPLATE_DEFAULT_THEME_USED' => 'Le th&egrave;me par défaut est donc utilisé',

    // actions/end.php
    'TEMPLATE_ACTION_END' => 'Action {{end ...}}',
    'TEMPLATE_ELEM_PARAMETER_REQUIRED' => 'param&egrave;tre "elem" obligatoire',

    // actions/col.php
    'TEMPLATE_ACTION_COL' => 'Action {{col ...}}',
    'TEMPLATE_SIZE_PARAMETER_REQUIRED' => 'param&egrave;tre "size" obligatoire',
    'TEMPLATE_SIZE_PARAMETER_MUST_BE_INTEGER_FROM_1_TO_12' => 'le param&egrave;tre "size" doit &ecirc;tre un entier compris entre 1 et 12',
    'TEMPLATE_ELEM_COL_NOT_CLOSED' => 'l\'action {{col ...}} doit &ecirc;tre ferm&eacute;e par une action {{end elem="col"}}',

    // actions/grid.php
    'TEMPLATE_ACTION_GRID' => 'Action {{grid ...}}',
    'TEMPLATE_ELEM_GRID_NOT_CLOSED' => 'l\'action {{grid ...}} doit &ecirc;tre ferm&eacute;e par une action {{end elem="grid"}}',

    // action/panel.php
    'TEMPLATE_ACTION_PANEL' => 'Action {{panel ...}}',
    'TEMPLATE_ELEM_PANEL_NOT_CLOSED' => 'l\'action {{panel ...}} doit &ecirc;tre ferm&eacute;e par une action {{end elem="panel"}}',
    'TEMPLATE_TITLE_PARAMETER_REQUIRED' => "Param&egrave;tre title obligatoire",

    // action/accordion.php
    'TEMPLATE_ACTION_ACCORDION' => 'Action {{accordion ...}}',
    'TEMPLATE_ELEM_ACORDION_NOT_CLOSED' => 'l\'action {{accordion ...}} doit &ecirc;tre ferm&eacute;e par une action {{end elem="accordion"}}',


    // actions/buttondropdown.php
    'TEMPLATE_ACTION_BUTTONDROPDOWN' => 'Action {{buttondropdown ...}}',
    'TEMPLATE_ELEM_BUTTONDROPDOWN_NOT_CLOSED' => 'l\'action {{buttondropdown ...}} doit &ecirc;tre ferm&eacute;e par une action {{end elem="buttondropdown"}}',

    // actions/adminpages.php
    'TEMPLATE_ACTION_FOR_ADMINS_ONLY' => 'action réservée aux administrateurs',
    'TEMPLATE_CONFIRM_DELETE_PAGE' => 'Etes vous sûr(e) de vouloir supprimer définitivement cette page ?',
    'TEMPLATE_PAGE' => 'Page',
    'TEMPLATE_LAST_MODIFIED' => 'Dernière modification',
    'TEMPLATE_OWNER' => 'Propriétaire',
    'TEMPLATE_ACTIONS' => 'Actions',
    'TEMPLATE_MODIFY' => 'Modifier',

    // actions/setwikidefaultheme.php
    'TEMPLATE_FORCE_TEMPLATE' => 'Forcer le choix pour tout le wiki.',

    // themeselector.tpl.html
    'TEMPLATE_PRIMARY_COLOR' => 'Couleur primaire',
    'TEMPLATE_SECONDARY_COLOR_1' => 'Couleur secondaire 1',
    'TEMPLATE_SECONDARY_COLOR_2' => 'Couleur secondaire 2',
    'TEMPLATE_NEUTRAL_COLOR' => 'Couleur de texte',
    'TEMPLATE_SOFT_COLOR' => 'Couleur neutre',
    'TEMPLATE_LIGHT_COLOR' => 'Couleur claire',
    'TEMPLATE_MAIN_TEXT_SIZE' => 'Taille de texte',
    'TEMPLATE_MAIN_TEXT_FONT' => 'Police des textes',
    'TEMPLATE_MAIN_TITLE_FONT' => 'Police des titres',
    'TEMPLATE_CHOOSE_FONT' => 'Choisir une police',
    'TEMPLATE_SEARCH_POINTS' => 'Rechercher...',
    'TEMPLATE_DELETE_CSS_PRESET' => 'Voulez-vous supprimer le preset personnalisé',
    'TEMPLATE_ADD_CSS_PRESET_API_HINT' => 'Sauvegarde un fichier preset personnalisé',
    'TEMPLATE_DELETE_CSS_PRESET_API_HINT' => 'Supprime un fichier preset personnalisé',
    'TEMPLATE_PRESET_FILENAME' => 'Nom du preset',
    'TEMPLATE_THEME_NOT_SAVE' => 'Thème non sauvegardé',
    'TEMPLATE_FILE_NOT_ADDED' => ' non ajouté !',
    'TEMPLATE_FILE_NOT_DELETED' => ' non supprimé !',
    'TEMPLATE_FILE_ALREADY_EXISTING' => "Le fichier est déjà existant ! Changez de nom de preset ou connectez-vous en admin !",
    'TEMPLATE_PRESET_ERROR' => "Impossible d'appliquer ce preset, il y a une erreur !",
    'TEMPLATE_PRESETS' => 'Configurations graphiques',
    'TEMPLATE_CREATE_PRESET' => 'Créer une nouvelle configuration graphique',
    'TEMPLATE_CUSTOMIZE_PRESET' => 'Configuration graphique',

    // actions-builder
    'AB_template_group_label' => 'Mise en forme',
    'AB_template_action_label_label' => 'Etiquette',
    'AB_template_action_label_example' => 'Texte de votre étiquette à changer par la suite',
    'AB_template_actions_class' => 'Classe',
    'AB_template_actions_color' => 'Couleur',
    'AB_template_actions_primary' => 'Primaire',
    'AB_template_actions_secondary_1' => 'Secondaire-1',
    'AB_template_actions_secondary_2' => 'Secondaire-2',
    'AB_template_actions_success' => 'Succès',
    'AB_template_actions_info' => 'Info',
    'AB_template_actions_warning' => 'Attention',
    'AB_template_actions_danger' => 'Danger',
    'AB_template_action_accordion_label' => 'Afficher des encadrés en accordéon',
    'AB_template_action_accordion_example' => "{{panel title=\"Titre 1\"}}\nTexte du panneau 1 à changer par la suite\n{{end elem=\"panel\"}}\n"
        ."{{panel title=\"Titre 2\"}}\nTexte du panneau 2 à changer par la suite\n{{end elem=\"panel\"}}\n",
    'AB_template_action_ariane_label' => 'Fil d\'ariane',
    'AB_template_action_col_label' => 'Colonne',
    'AB_template_action_col_example' => 'Texte de votre colonne à changer par la suite',
    'AB_template_col_size_label' => 'Largeur de la colonne',
    'AB_template_action_grid_label' => 'Afficher plusieurs colonnes',
    'AB_template_action_grid_example' => "{{col size=\"3\"}}\nTexte de la colonne 1 à changer par la suite\n{{end elem=\"col\"}}\n"
        ."{{col size=\"3\"}}\nTexte de la colonne 2 à changer par la suite\n{{end elem=\"col\"}}\n"
        ."{{col size=\"3\"}}\nTexte de la colonne 3 à changer par la suite\n{{end elem=\"col\"}}\n"
        ."{{col size=\"3\"}}\nTexte de la colonne 4 à changer par la suite\n{{end elem=\"col\"}}\n",

    // gererdroits
    'ACLS_SELECT_PAGES_FILTER' => 'Filtrer :',
    'ACLS_SELECT_PAGES_FILTER_ON_PAGES' => 'les pages uniquement',
    'ACLS_SELECT_PAGES_FILTER_ON_SPECIALPAGES' => 'les pages spéciales uniquement',
    'ACLS_SELECT_PAGES_FILTER_ON_LISTS' => 'les listes uniquement',
    'ACLS_SELECT_PAGES_FILTER_FORM' => 'les fiches du formulaire : {name} ({id})',

    // for edit config
    'EDIT_CONFIG_HINT_META_KEYWORDS' => 'Mots clés pour le référencement (séparés par des virgules, pas plus de 20-30)',
    'EDIT_CONFIG_HINT_META_DESCRIPTION' => 'Description du site en une phrase, pour le référencement (Attention : ne pas mettre de "." (point))',
    'EDIT_CONFIG_HINT_META[ROBOTS]' => 'Empêcher les robots à indexer le wiki (Mettre \'noindex,nofollow,noarchive,noimageindex\')',
    'EDIT_CONFIG_GROUP_TEMPLATES' => 'Balises meta pour l\'indexation web', // idéalement 'Mise en forme' mais templates est pour le moment uniquement utilisé pour meta

    )
);
