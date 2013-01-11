<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5.1                                                                                      |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 1999-2006 outils-reseaux.org                                                           |
// +------------------------------------------------------------------------------------------------------+
// | This file is part of wkfarm.                                                                         |
// |                                                                                                      |
// | Foobar is free software; you can redistribute it and/or modify                                       |
// | it under the terms of the GNU General Public License as published by                                 |
// | the Free Software Foundation; either version 2 of the License, or                                    |
// | (at your option) any later version.                                                                  |
// |                                                                                                      |
// | Foobar is distributed in the hope that it will be useful,                                            |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                                        |
// | GNU General Public License for more details.                                                         |
// |                                                                                                      |
// | You should have received a copy of the GNU General Public License                                    |
// | along with Foobar; if not, write to the Free Software                                                |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
// CVS : $Id: wiki.php,v 1.16 2011-07-13 10:33:23 mrflos Exp $
/**
* wiki.php
*
* Description : fichier de configuration de la ferme
*
*@package wkfarm
*@author        Florestan BREDOW <florestan.bredow@supagro.inra.fr>
*@copyright     yeswiki.net 2012
*@version       $Revision: 1.16 $ $Date: 2011-07-13 10:33:23 $
*
*/

if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

// on prend le dossier du wiki comme chemin pour la ferme
define ('FERME_PATH', getcwd().DIRECTORY_SEPARATOR);

// Necessite chemin absolu
define ('FERME_SOURCE_PATH', realpath(FERME_PATH));

// on prend l'url du wiki, moins le wakka.php et consorts comme url de base
$url = explode('wakka.php', $wakkaConfig['base_url']);
define ('FERME_BASE_URL', $url[0]);

// template d'affichage de l'interface d'ajout de wikis
define ('FERME_TEMPLATE', "yeswiki.phtml");

//base de données
define ('FERME_DB_HOST', $wakkaConfig['mysql_host']);
define ('FERME_DB_NAME', $wakkaConfig['mysql_database']);
define ('FERME_DB_USER', $wakkaConfig['mysql_user']);
define ('FERME_DB_PASSWORD', $wakkaConfig['mysql_password']);



// scanne les themes
$GLOBALS['themesyeswiki'] = array(
	'SupAgro' => array(
		'theme' => 'yeswiki',
		'style' => 'yeswiki-green.css',
		'squelette' => 'yeswiki.tpl.html',
		'thumb' => 'img/YesWiki1.png',
	),
	'SupAgro + menu gauche' => array(
		'theme' => 'yeswiki',
		'style' => 'yeswiki-green.css',
		'squelette' => 'yeswiki-2cols-left.tpl.html',
		'thumb' => 'img/YesWiki2.png',
	),
	'YesWiki' => array(
		'theme' => 'yeswiki',
		'style' => 'yeswiki.css',
		'squelette' => 'yeswiki.tpl.html',
		'thumb' => 'img/YesWiki3.png',
	),

		);

?>
