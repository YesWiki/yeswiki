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
* English translation for the Templates extension
*
*@package 		templates
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2014 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

// actions/button.php
'TEMPLATE_ACTION_BUTTON' => 'Action {{button ...}}',
'TEMPLATE_LINK_PARAMETER_REQUIRED' => '"link" parameter required',

'TEMPLATE_RSS_LAST_CHANGES' => 'Last modified pages RSS feed',
'TEMPLATE_RSS_LAST_COMMENTS' => 'Last comments RSS feed',

'TEMPLATE_DEFAULT_THEME_USED' => 'Default template will be used',
'TEMPLATE_NO_THEME_FILES' => 'Some (or all) of the template\'s files could not be found',
'TEMPLATE_NO_DEFAULT_THEME' => 'The files of template\'s default theme not found. <br />Please re-install the tools template or contact an administrator of this website',
'TEMPLATE_CUSTOM_GRAPHICS' => 'Appearance of page',
'TEMPLATE_SAVE' => 'Save',
'TEMPLATE_APPLY' => 'Apply',
'TEMPLATE_CANCEL' => 'Cancel',
'TEMPLATE_THEME' => 'Theme',
'TEMPLATE_SQUELETTE' => 'Template',
'TEMPLATE_STYLE' => 'Style',
'TEMPLATE_BG_IMAGE' => 'Background image',
'TEMPLATE_ERROR_NO_DATA' => 'ERROR : no metadatas to add.',
'TEMPLATE_ERROR_NO_ACCESS' => 'ERROR : no access rights.',

// barre de redaction
'TEMPLATE_VIEW_PAGE' => 'See the page',
'TEMPLATE_EDIT' => 'Edit',
'TEMPLATE_EDIT_THIS_PAGE' => 'Edit this page',
'TEMPLATE_CLICK_TO_SEE_REVISIONS' => 'Last changes on this page',
'TEMPLATE_LAST_UPDATE' => 'Modified on',
'TEMPLATE_OWNER' => 'Owner',
'TEMPLATE_YOU' => 'you',
'TEMPLATE_NO_OWNER' => 'No owner',
'TEMPLATE_CLAIM' => 'Claim',
'TEMPLATE_CLICK_TO_CHANGE_PERMISSIONS' => 'Change permissions',
'TEMPLATE_PERMISSIONS' => 'Permissions',
'TEMPLATE_DELETE' => 'Delete',
'TEMPLATE_DELETE_PAGE' => 'Delete this page',
'TEMPLATE_CLICK_TO_SEE_REFERENCES' => 'Incoming URLs to this page',
'TEMPLATE_REFERENCES' => 'Referrers',
'TEMPLATE_SLIDESHOW_MODE' => 'See this page as slideshow.',
'TEMPLATE_SLIDESHOW' => 'Slideshow',
'TEMPLATE_SEE_SHARING_OPTIONS' => 'Share this page',
'TEMPLATE_SHARE' => 'Share',

// formatage des dates
'TEMPLATE_DATE_FORMAT' => 'd.m.Y \a\t H:i:s',

// recherche
'TEMPLATE_SEARCH_INPUT_TITLE' => 'Search in YesWiki [alt-shift-C]',
'TEMPLATE_SEARCH_BUTTON_TITLE' => 'Search the page containing those words.',
'TEMPLATE_SEARCH_PLACEHOLDER' => 'Search...',

// handler widget
'TEMPLATE_WIDGET_TITLE' => 'Widget : integrate this page\'s content elsewhere',
'TEMPLATE_WIDGET_COPY_PASTE' => 'Copy/Paste this HTML code to integrate this page\'s content elsewhere.',

// handler share
'TEMPLATE_SHARE_INCLUDE_CODE' => 'Code to integrate in a HTML page',
'TEMPLATE_SHARE_MUST_READ' => 'To read : ',
'TEMPLATE_SHARE_FACEBOOK' => 'Share on Facebook',
'TEMPLATE_SHARE_TWITTER' => 'Share on Twitter',
'TEMPLATE_SHARE_NETVIBES' => 'Share on Netvibes',
'TEMPLATE_SHARE_DELICIOUS' => 'Share on Delicious',
'TEMPLATE_SHARE_GOOGLEREADER' => 'Share on Google Reader',
'TEMPLATE_SHARE_MAIL' => 'Send this page by mail',
'TEMPLATE_ADD_SHARE_BUTTON' => 'Add a share button on the top right of the included widget',
'TEMPLATE_ADD_EDIT_BAR' => 'Add the edition actions bar on the included widget',

// handler diaporama
'TEMPLATE_NO_ACCESS_TO_PAGE' => 'You have no access right to this page.',
'TEMPLATE_PAGE_DOESNT_EXIST' => 'Page doesn\'t exist',
'PAGE_CANNOT_BE_SLIDESHOW' => 'This page cannot be split in slides (no second level titles)',

// handler edit
'TEMPLATE_CUSTOM_PAGE' => 'Preferences for page',
'TEMPLATE_PAGE_PREFERENCES' => 'Page preferences',
'PAGE_LANGUAGE' => 'Page\'s language',
'CHOOSE_PAGE_FOR' => 'Choose a page for',
'HORIZONTAL_MENU_PAGE' => 'the horizontal menu',
'FAST_ACCESS_RIGHT_PAGE' => 'fast access on top right corner',
'HEADER_PAGE' => 'the header (banner)',
'FOOTER_PAGE' => 'the footer',
'FOR_2_OR_3_COLUMN_THEMES' => 'For themes with 2 or 3 columns',
'VERTICAL_MENU_PAGE' => 'the vertical menu',
'RIGHT_COLUMN_PAGE' => 'the right column'

));