<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5.1                                                                                      |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2011 Kaleidos-coop.org                                                                 |
// +------------------------------------------------------------------------------------------------------+
// | This file is part of wkgenerationusb.                                                                     |
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
// CVS : $Id: wiki.php,v 1.12 2010-12-01 17:01:38 mrflos Exp $
/**
* wiki.php
*
*
*@package wkgenerationusb
//Auteur original :
*@author        Florian SCHMITT <florian@outils-reseaux.org>
//Autres auteurs :
*@copyright     outils-reseaux.org 20
*@version       $Revision: 1.12 $ $Date: 2010-12-01 17:01:38 $
// +------------------------------------------------------------------------------------------------------+
*/

if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

// On parse le fichier de configuration, et on le met dans l'objet wiki, pour qu'on puisse y accèder dans tout le code
$wakkaConfig['generationusb'] = parse_ini_file("config.ini");

?>
