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
* Fichier de traduction en francais de l'extension syndication
*
*@package 		syndication
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2013 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

// actions/syndication.php
'SYNDICATION_ACTION_SYNDICATION' => 'Actie {{syndication ...}}',
'SYNDICATION_PARAM_URL_REQUIRED' => 'de parameter "URL" moet verplicht worden ingegeven voor de syndicatie van een RSS-feed',

// actions/twitter.php
'SYNDICATION_ACTION_TWITTER' => 'Actie {{twitter ...}}',
'SYNDICATION_PARAM_USER_REQUIRED' => 'parameter "user" ontbreekt en is verplicht om de gekozen Twitter-gebruiker te specificeren',

// actions/twittersearch.php
'SYNDICATION_ACTION_TWITTERSEARCH' => 'Actie {{twittersearch ...}}',
'SYNDICATION_PARAM_QUERY_REQUIRED' => 'parameter "query" ontbreekt en is verplicht om uw opzoeking in te voeren',
'SYNDICATION_TEMPLATE_DOESNT_EXISTS' => 'Het sjabloonbestand',
'SYNDICATION_USE_OF_DEFAULT_TEMPLATE' => 'bestaat niet, we gebruiken het standaardsjabloon'



));