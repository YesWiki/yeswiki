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
*@package 		templates
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2012 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

// actions/button.php
'TEMPLATE_ACTION_BUTTON' => 'Actie {{button ...}}',
'TEMPLATE_LINK_PARAMETER_REQUIRED' => 'parameter "link" verplicht',

'TEMPLATE_RSS_LAST_CHANGES' => 'RSS-feed van laatst gewijzigde pagina’s',
'TEMPLATE_RSS_LAST_COMMENTS' => 'RSS-feed van laatste commentaren',

'TEMPLATE_DEFAULT_THEME_USED' => 'Het standaardsjabloon werd dus gebruikt',
'TEMPLATE_NO_THEME_FILES' => 'Sommige (of alle) bestanden van het sjabloon zijn verdwenen',
'TEMPLATE_NO_DEFAULT_THEME' => 'De bestanden van het standaardsjabloon zijn verdwenen, de sjablonen kunnen niet gebruikt worden. <br/>Herinstalleer het tools-sjablooon of neem contact op met de beheerder van de site',
'TEMPLATE_CUSTOM_GRAPHICS' => 'Uitzicht van de pagina',
'TEMPLATE_SAVE' => 'Opslaan',
'TEMPLATE_APPLY' => 'Toepassen',
'TEMPLATE_CANCEL' => 'Annuleren',
'TEMPLATE_THEME' => 'Thema',
'TEMPLATE_SQUELETTE' => 'Skelet',
'TEMPLATE_STYLE' => 'Stijl',
'TEMPLATE_BG_IMAGE' => 'Achtergrondafbeelding',
'TEMPLATE_ERROR_NO_DATA' => 'FOUT: niets toe te voegen aan metagegevens.',
'TEMPLATE_ERROR_NO_ACCESS' => 'FOUT: geen toegangsrechten.',

// barre de redaction
'TEMPLATE_VIEW_PAGE' => 'De pagina bekijken',
'TEMPLATE_EDIT' => 'bewerken',
'TEMPLATE_EDIT_THIS_PAGE' => 'de pagina bewerken',
'TEMPLATE_CLICK_TO_SEE_REVISIONS' => 'De jongste wijzigingen op de pagina',
'TEMPLATE_LAST_UPDATE' => 'Gewijzigd op',
'TEMPLATE_OWNER' => 'Eigenaar',
'TEMPLATE_YOU' => 'u',
'TEMPLATE_NO_OWNER' => 'Geen eigenaar',
'TEMPLATE_CLAIM' => 'Toe-eigening',
'TEMPLATE_CLICK_TO_CHANGE_PERMISSIONS' => 'de toelatingen van de pagina bewerken',
'TEMPLATE_PERMISSIONS' => 'Toelatingen',
'TEMPLATE_DELETE' => 'Wissen',
'TEMPLATE_DELETE_PAGE' => 'De pagina wissen',
'TEMPLATE_CLICK_TO_SEE_REFERENCES' => 'De URL’s die naar de pagina verwijzen',
'TEMPLATE_REFERENCES' => 'Verwijzingen',
'TEMPLATE_SLIDESHOW_MODE' => 'Deze pagina in de diamodus starten.',
'TEMPLATE_SLIDESHOW' => 'Dia',
'TEMPLATE_SEE_SHARING_OPTIONS' => 'De pagina delen',
'TEMPLATE_SHARE' => 'Delen',

// formatage des dates
'TEMPLATE_DATE_FORMAT' => 'd.m.J \&\a\g\r\a\v\e; H:i:s',

// recherche
'TEMPLATE_SEARCH_INPUT_TITLE' => 'Opzoeken in YesWiki [alt-shift-C]',
'TEMPLATE_SEARCH_BUTTON_TITLE' => 'Pagina’s met deze tekst opzoeken.',
'TEMPLATE_SEARCH_PLACEHOLDER' => 'Opzoeken...',

// handler widget
'TEMPLATE_WIDGET_TITLE' => 'Widget: de inhoud van deze pagina elders integreren',
'TEMPLATE_WIDGET_COPY_PASTE' => 'De HTML-code hierboven kopiëren/plakken om de inhoud te integreren zoals hij hieronder verschijnt.',

// handler share
'TEMPLATE_SHARE_INCLUDE_CODE' => 'Code om inhoud te integreren in een HTML-pagina',
'TEMPLATE_SHARE_MUST_READ' => 'Te lezen:',
'TEMPLATE_SHARE_FACEBOOK' => 'Delen op Facebook',
'TEMPLATE_SHARE_TWITTER' => 'Delen op Twitter',
'TEMPLATE_SHARE_NETVIBES' => 'Delen op Netvibes',
'TEMPLATE_SHARE_DELICIOUS' => 'Delen op Delicious',
'TEMPLATE_SHARE_GOOGLEREADER' => 'Delen op Google Reader',
'TEMPLATE_SHARE_MAIL' => 'De inhoud van deze pagina verzenden via e-mail',
'TEMPLATE_ADD_SHARE_BUTTON' => 'Rechts bovenaan de pagina een deeltoets toevoegen',
'TEMPLATE_ADD_EDIT_BAR' => 'De bewerkingsbalk onderaan de pagina toevoegen',

// handler diaporama
'TEMPLATE_NO_ACCESS_TO_PAGE' => 'U hebt geen toegangsrecht tot deze pagina.',
'TEMPLATE_PAGE_DOESNT_EXIST' => 'Pagina bestaat niet',
'PAGE_CANNOT_BE_SLIDESHOW' => 'De pagina kan niet worden opgesplitst in dia’s (geen titels op niveau 2)',

// handler edit
'TEMPLATE_CUSTOM_PAGE' => 'Paginavoorkeuren',
'TEMPLATE_PAGE_PREFERENCES' => 'Parameters van de pagina',
'PAGE_LANGUAGE' => 'Paginataal',
'CHOOSE_PAGE_FOR' => 'Een pagina kiezen voor',
'HORIZONTAL_MENU_PAGE' => 'horizontaal menu',
'FAST_ACCESS_RIGHT_PAGE' => 'snelkoppelingen rechts bovenaan',
'HEADER_PAGE' => 'de koptekst (band)',
'FOOTER_PAGE' => 'de voettekst',
'FOR_2_OR_3_COLUMN_THEMES' => 'Voor de thema’s met 2 of 3 kolommen',
'VERTICAL_MENU_PAGE' => 'het verticale menu',
'RIGHT_COLUMN_PAGE' => 'de rechter kolom',

// actions/yeswikiversion.php
'RUNNING_WITH' => 'Lopen met',

'TEMPLATE_NO_THEME_FILES' => 'Bestanden van ontbrekende thema',
'TEMPLATE_DEFAULT_THEME_USED' => 'Het standaardthema wordt gebruikt'

));