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
'SYNDICATION_ACTION_SYNDICATION' => 'Action {{syndication ...}}',
'SYNDICATION_PARAM_URL_REQUIRED' => 'il faut entrer obligatoirement le param&egrave;tre "url" pour syndiquer un flux RSS',
'SYNDICATION_WRITE_ACCESS_TO_CACHE_FOLDER' => 'le r&eacute;pertoire "cache" n\'a pas les droits d\'acc&egrave;s en &eacute;criture',
'SYNDICATION_CREATE_CACHE_FOLDER' => 'il faut cr&eacute;er un r&eacute;pertoire "cache" dans le r&eacute;pertoire principal',
'SYNDICATION_TEMPLATE_NOT_FOUND' => 'n\'existe pas, on utilise le fichier template par d&eacute;faut.',
'SYNDICATION_READ_MORE' => 'Lire la suite',

// actions/twitter.php
'SYNDICATION_ACTION_TWITTER' => 'Action {{twitter ...}}',
'SYNDICATION_PARAM_USER_REQUIRED' => 'param&egrave;tre "user" manquant, il est obligatoire pour sp&eacute;fifier l\'utilisateur twitter choisi',

// actions/twittersearch.php
'SYNDICATION_ACTION_TWITTERSEARCH' => 'Action {{twittersearch ...}}',
'SYNDICATION_PARAM_QUERY_REQUIRED' => 'param&egrave;tre "query" manquant, il est obligatoire pour sp&eacute;cifier votre recherche',
'SYNDICATION_TEMPLATE_DOESNT_EXISTS' => 'Le fichier template',
'SYNDICATION_USE_OF_DEFAULT_TEMPLATE' => 'n\'existe pas, on utilise le template par d&eacute;faut'

));