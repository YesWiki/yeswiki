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
* English translation for the  extension syndication
*
*@package 		syndication
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2013 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

// actions/syndication.php
'SYNDICATION_ACTION_SYNDICATION' => 'Action {{syndication ...}}',
'SYNDICATION_PARAM_URL_REQUIRED' => 'the parameter "url" is required for RSS feed',

// actions/twitter.php
'SYNDICATION_ACTION_TWITTER' => 'Action {{twitter ...}}',
'SYNDICATION_PARAM_USER_REQUIRED' => 'the parameter "user" is missing, it is required to enter the twitter username',

// actions/twittersearch.php
'SYNDICATION_ACTION_TWITTERSEARCH' => 'Action {{twittersearch ...}}',
'SYNDICATION_PARAM_QUERY_REQUIRED' => 'parameter "query" is missing, it is required specify your search',
'SYNDICATION_TEMPLATE_DOESNT_EXISTS' => 'The template file',
'SYNDICATION_USE_OF_DEFAULT_TEMPLATE' => 'doesn\'t exist, the defaut template will be used',

));