<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP versió 5                                                                                         |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2012 Outils-Réseaux (accueil@outils-reseaux.org)                                       |
// +------------------------------------------------------------------------------------------------------+
// | Aquesta llibreria és de programari lliure; podeu redistribuir-la i/o                                 |
// | modificar-la d'acord amb els termes del GNU Lesser General Public                                    |
// | License tal com ha estat publicada per la Free Software Foundation; sigui la                         |
// | versió 2.1 de la llicència o bé (opcionalment) qualsevol versió posterior.                           |
// |                                                                                                      |
// | Aquesta llibreria és distribuïda amb l'ànim que sigui útil                                           |
// | però SENSE CAP GARANTIA; fins i tot sense la garantia implícita de                                   |
// | MERCHANTABILITY o de FITNESS FOR A PARTICULAR PURPOSE. Vegeu la GNU                                  |
// | Lesser General Public License per a més detalls.                                                     |
// |                                                                                                      |
// | Amb aquesta llibreria heu d'haver rebut còpia de la GNU Lesser General Public                        |
// | License; altrament, escriviu a la Free Software Foundation,                                          |
// | Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                                        |
// +------------------------------------------------------------------------------------------------------+
// 
/**
* Fitxer de traducció al català de l'extensió Templates
*
*@package 		templates
*@author        Jordi Picart <jordi.picart@aposta.coop>
*@copyright     2014 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

// actions/button.php
'TEMPLATE_ACTION_BUTTON' => 'Acció {{button ...}}',
'TEMPLATE_LINK_PARAMETER_REQUIRED' => 'El paràmetre "link" és obligatori',

'TEMPLATE_RSS_LAST_CHANGES' => 'Flux RSS de les darreres pàgines modificades',
'TEMPLATE_RSS_LAST_COMMENTS' => 'Flux RSS dels darrers comentaris',

'TEMPLATE_DEFAULT_THEME_USED' => 'S\'està utilitzant el patró per defecte.',
'TEMPLATE_NO_THEME_FILES' => 'Alguns dels fitxers (o tots) del patró s\'han perdut',
'TEMPLATE_NO_DEFAULT_THEME' => 'Els fitxers del patró per defecte han desaparegut, no es poden utilitzar patrons.<br />Reinstal·leu el tools template o contacteu amb l\'administrador',
'TEMPLATE_CUSTOM_GRAPHICS' => 'Aparença de la pàgina',
'TEMPLATE_SAVE' => 'Desa',
'TEMPLATE_APPLY' => 'Aplica',
'TEMPLATE_CANCEL' => 'Descarta',
'TEMPLATE_THEME' => 'TTema',
'TEMPLATE_SQUELETTE' => 'Esquelet',
'TEMPLATE_STYLE' => 'Estil',
'TEMPLATE_BG_IMAGE' => 'Imatge de fons',
'TEMPLATE_ERROR_NO_DATA' => 'ERROR: no hi ha res que es pugui afegir a les metadades.',
'TEMPLATE_ERROR_NO_ACCESS' => 'ERROR: no teniu drets d\'accés.',

// barre de redaction
'TEMPLATE_VIEW_PAGE' => 'Mostra la pàgina',
'TEMPLATE_EDIT' => 'Edita',
'TEMPLATE_EDIT_THIS_PAGE' => 'Edita aquesta pàgina',
'TEMPLATE_CLICK_TO_SEE_REVISIONS' => 'Darreres modificacions de la pàgina.',
'TEMPLATE_LAST_UPDATE' => 'Darrera actualització',
'TEMPLATE_OWNER' => 'Propietari',
'TEMPLATE_YOU' => 'vós',
'TEMPLATE_NO_OWNER' => 'No hi ha propietari',
'TEMPLATE_CLAIM' => 'Reclama com a propi',
'TEMPLATE_CLICK_TO_CHANGE_PERMISSIONS' => 'Edita els permisos de la pàgina',
'TEMPLATE_PERMISSIONS' => 'Permisos',
'TEMPLATE_DELETE' => 'Elimina',
'TEMPLATE_DELETE_PAGE' => 'Elimina la pàgina',
'TEMPLATE_CLICK_TO_SEE_REFERENCES' => 'URLs que fan referència a la pàgina',
'TEMPLATE_REFERENCES' => 'Referències',
'TEMPLATE_SLIDESHOW_MODE' => 'Mostra aquesta pàgina en mode presentació de diapositives.',
'TEMPLATE_SLIDESHOW' => 'Presentació de diapositives',
'TEMPLATE_SEE_SHARING_OPTIONS' => 'Comparteix la pàgina',
'TEMPLATE_SHARE' => 'Comparteix',

// formatage des dates
'TEMPLATE_DATE_FORMAT' => 'd.m.Y \a \l\e\s H:i:s',

// recherche
'TEMPLATE_SEARCH_INPUT_TITLE' => 'Cerca a YesWiki [alt-shift-C]',
'TEMPLATE_SEARCH_BUTTON_TITLE' => 'Cerca les pàgines que contenen aquest text.',
'TEMPLATE_SEARCH_PLACEHOLDER' => 'Cerca...',

// handler widget
'TEMPLATE_WIDGET_TITLE' => 'Giny: integra el contingut d\'aquesta pàgina en una altra',
'TEMPLATE_WIDGET_COPY_PASTE' => 'Copieu i enganxeu el codi HTML següent per integrar el contingut tal i com apareix.',

// handler share
'TEMPLATE_SHARE_INCLUDE_CODE' => 'Codi d\'integració de contingut en una pàgina HTML',
'TEMPLATE_SHARE_MUST_READ' => 'Per llegir: ',
'TEMPLATE_SHARE_FACEBOOK' => 'Comparteix a Facebook',
'TEMPLATE_SHARE_TWITTER' => 'Comparteix a Twitter',
'TEMPLATE_SHARE_NETVIBES' => 'Comparteix a Netvibes',
'TEMPLATE_SHARE_DELICIOUS' => 'Comparteix a Delicious',
'TEMPLATE_SHARE_GOOGLEREADER' => 'Comparteix a Google Reader',
'TEMPLATE_SHARE_MAIL' => 'Envia el contingut d\'aquesta pàgina per mail',
'TEMPLATE_ADD_SHARE_BUTTON' => 'Afegeix a la part superior dreta un botó per compartir la pàgina',
'TEMPLATE_ADD_EDIT_BAR' => 'Afegeix la barra d\'edició al peu de la pàgina',

// handler diaporama
'TEMPLATE_NO_ACCESS_TO_PAGE' => 'No teniu drets per accedir a aquesta pàgina.',
'TEMPLATE_PAGE_DOESNT_EXIST' => 'Aquesta pàgina no existeix',
'PAGE_CANNOT_BE_SLIDESHOW' => 'La pàgina no es pot distribuir en diapositives (no hi ha títols de nivell 2)',

// handler edit
'TEMPLATE_CUSTOM_PAGE' => 'Preferències per la pàgina',
'TEMPLATE_PAGE_PREFERENCES' => 'Preferències de la pàgina',
'PAGE_LANGUAGE' => 'Llengua de la pàgina',
'CHOOSE_PAGE_FOR' => 'Trieu una pàgina per',
'HORIZONTAL_MENU_PAGE' => 'Menú horitzontal',
'FAST_ACCESS_RIGHT_PAGE' => 'Accés ràpid al racó superior dret',
'HEADER_PAGE' => 'L\'encapçalament (bàner)',
'FOOTER_PAGE' => 'Peu de pàgina',
'FOR_2_OR_3_COLUMN_THEMES' => 'Per a temes amb 2 o 3 columnes',
'VERTICAL_MENU_PAGE' => 'El menú vertical',
'RIGHT_COLUMN_PAGE' => 'La columna dreta'

));