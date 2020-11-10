<?php

# namespace YesWiki;

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
 * Fichier de lancement et de configuration de l'extension Templates.
 *
 *@author        Florian Schmitt <florian@outils-reseaux.org>
 *@copyright     2012 Outils-Réseaux
 */
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

require_once 'libs/templates.functions.php';

// Theme par défaut
define('THEME_PAR_DEFAUT', 'margot');

// Style par défaut
define('CSS_PAR_DEFAUT', 'margot.css');

// Squelette par défaut
define('SQUELETTE_PAR_DEFAUT', '1col.tpl.html');

// Preset CSS par défaut
define('PRESET_PAR_DEFAUT', '');

// Image de fond par défaut
define('BACKGROUND_IMAGE_PAR_DEFAUT', '');

// Pour que seul le propriétaire et l'admin puissent changer de theme
define('SEUL_ADMIN_ET_PROPRIO_CHANGENT_THEME', false);
