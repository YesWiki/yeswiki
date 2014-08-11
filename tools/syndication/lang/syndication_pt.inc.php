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
* Arquivo de tradução em português (do Brasil) da extensão syndication
*
*@package 		syndication
*@author        François Labastie <francois@outils-reseaux.org>
*@copyright     2014 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

// actions/syndication.php
'SYNDICATION_ACTION_SYNDICATION' => 'Ação {{syndication ...}}',
'SYNDICATION_PARAM_URL_REQUIRED' => 'você deve necessariamente introduzir o parâmetro "url" para distribuir um feed RSS',
'SYNDICATION_WRITE_ACCESS_TO_CACHE_FOLDER' => 'o diretório "cache" não tem direitos de acesso em gravação',
'SYNDICATION_CREATE_CACHE_FOLDER' => 'devemos criar um diretório "cache" no diretório principal',
'SYNDICATION_TEMPLATE_NOT_FOUND' => 'não existe, o arquivo template padrão é usado.',
'SYNDICATION_READ_MORE' => 'Leia mais',

// actions/twitter.php
'SYNDICATION_ACTION_TWITTER' => 'Ação {{twitter ...}}',
'SYNDICATION_PARAM_USER_REQUIRED' => 'parâmetro "usuário" faltando, é obrigatório para especificar o usuário do twitter selecionado',

// actions/twittersearch.php
'SYNDICATION_ACTION_TWITTERSEARCH' => 'Ação {{twittersearch ...}}',
'SYNDICATION_PARAM_QUERY_REQUIRED' => 'parâmetro "query" faltando, é obrigatório para especificar sua pesquisa',
'SYNDICATION_TEMPLATE_DOESNT_EXISTS' => 'O arquivo template',
'SYNDICATION_USE_OF_DEFAULT_TEMPLATE' => 'não existe, o arquivo template padrão é usado.'

));