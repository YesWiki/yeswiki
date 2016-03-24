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
* Fichier de traduction en espagnol de l'extension syndication
*
*@package 		syndication
*@author        Louise Didier <louise@quincaillere.org>
*@copyright     2016 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

// actions/syndication.php
'SYNDICATION_ACTION_SYNDICATION' => 'Acción {{syndication ...}}',
'SYNDICATION_PARAM_URL_REQUIRED' => 'hay que entrar el parámetro "url" para sindicar un flujo RSS',
'SYNDICATION_WRITE_ACCESS_TO_CACHE_FOLDER' => 'el repertorio "cache" no tiene los derechos de accesso para escribir',
'SYNDICATION_CREATE_CACHE_FOLDER' => 'hay que crear un repertorio "cache" en el repertorio principal',
'SYNDICATION_TEMPLATE_NOT_FOUND' => 'no existe, se usa el archivo patrón por defecto.',
'SYNDICATION_READ_MORE' => 'Leer más',

// actions/twitter.php
'SYNDICATION_ACTION_TWITTER' => 'Acción {{twitter ...}}',
'SYNDICATION_PARAM_USER_REQUIRED' => 'falta el parámetro "user". Es obligatorio para precisar el usuario twitter elegido',

// actions/twittersearch.php
'SYNDICATION_ACTION_TWITTERSEARCH' => 'Acción {{twittersearch ...}}',
'SYNDICATION_PARAM_QUERY_REQUIRED' => 'falta el parámetro "query". Es obligatorio para precisar tu búsqueda',
'SYNDICATION_TEMPLATE_DOESNT_EXISTS' => 'El archivo template',
'SYNDICATION_USE_OF_DEFAULT_TEMPLATE' => 'no existe, se usa el patrón por defecto.'

));
