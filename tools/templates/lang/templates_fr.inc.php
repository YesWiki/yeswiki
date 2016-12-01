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
    'TEMPLATE_CANCEL' => 'Annuler',
    'TEMPLATE_THEME' => 'Th&egrave;me',
    'TEMPLATE_SQUELETTE' => 'Squelette',
    'TEMPLATE_STYLE' => 'Style',
    'TEMPLATE_BG_IMAGE' => 'Image de fond',
    'TEMPLATE_ERROR_NO_DATA' => 'ERREUR : rien &agrave; ajouter dans les meta-donn&eacute;es.',
    'TEMPLATE_ERROR_NO_ACCESS' => 'ERREUR : pas les droits d\'acc&eacute;s.',

    // barre de redaction
    'TEMPLATE_VIEW_PAGE' => 'Voir la page',
    'TEMPLATE_EDIT' => '&Eacute;diter',
    'TEMPLATE_EDIT_THIS_PAGE' => '&Eacute;diter la page',
    'TEMPLATE_CLICK_TO_SEE_REVISIONS' => 'Les derni&egrave;res modifications de la page',
    'TEMPLATE_LAST_UPDATE' => 'Modifi&eacute;e le',
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
    'TEMPLATE_DATE_FORMAT' => 'd.m.Y \&\a\g\r\a\v\e; H:i:s',

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

    // acion/accordion.php
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
    )
);
