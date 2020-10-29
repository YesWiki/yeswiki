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

// TODO mettre ça ailleurs car les services ne sont pas encore prêts au chargement des fichiers wiki.php
////on récupère les metadonnées de la page
//$metadatas = $wiki->GetTripleValue(
//    $page,
//    'http://outils-reseaux.org/_vocabulary/metadata',
//    '',
//    '',
//    ''
//);

if (!empty($metadatas)) {
    if (YW_CHARSET != 'UTF-8') {
        $metadatas = array_map('utf8_decode', json_decode($metadatas, true));
    } else {
        $metadatas = json_decode($metadatas, true);
    }
}

if (isset($metadatas['charset'])) {
    $wakkaConfig['charset'] = $metadatas['charset'];
} elseif (!isset($wakkaConfig['charset'])) {
    $wakkaConfig['charset'] = YW_CHARSET;
}

// Premier cas le template par défaut est forcé : on ajoute ce qui est présent dans le fichier de configuration, ou le theme par defaut précisé ci dessus
if (isset($wakkaConfig['hide_action_template']) && $wakkaConfig['hide_action_template'] == '1') {
    if (!isset($wakkaConfig['favorite_theme'])) {
        $wakkaConfig['favorite_theme'] = THEME_PAR_DEFAUT;
    }
    if (!isset($wakkaConfig['favorite_style'])) {
        $wakkaConfig['favorite_style'] = CSS_PAR_DEFAUT;
    }
    if (!isset($wakkaConfig['favorite_squelette'])) {
        $wakkaConfig['favorite_squelette'] = SQUELETTE_PAR_DEFAUT;
    }
    if (!isset($wakkaConfig['favorite_background_image'])) {
        $wakkaConfig['favorite_background_image'] = BACKGROUND_IMAGE_PAR_DEFAUT;
    }
} else {
    // Sinon, on récupère premièrement les valeurs passées en REQUEST, ou deuxièmement les métasdonnées présentes pour la page, ou troisièmement les valeurs du fichier de configuration
    if (isset($_REQUEST['theme']) && (is_dir('custom/themes/'.$_REQUEST['theme']) || is_dir('themes/'.$_REQUEST['theme'])) &&
        isset($_REQUEST['style']) && (is_file('custom/themes/'.$_REQUEST['theme'].'/styles/'.$_REQUEST['style']) || is_file('themes/'.$_REQUEST['theme'].'/styles/'.$_REQUEST['style'])) &&
        isset($_REQUEST['squelette']) && (is_file('custom/themes/'.$_REQUEST['theme'].'/squelettes/'.$_REQUEST['squelette']) || is_file('themes/'.$_REQUEST['theme'].'/squelettes/'.$_REQUEST['squelette']))
    ) {
        $wakkaConfig['favorite_theme'] = $_REQUEST['theme'];
        $wakkaConfig['favorite_style'] = $_REQUEST['style'];
        $wakkaConfig['favorite_squelette'] = $_REQUEST['squelette'];

        if (isset($_REQUEST['bgimg']) && (is_file('files/backgrounds/'.$_REQUEST['bgimg']))) {
            $wakkaConfig['favorite_background_image'] = $_REQUEST['bgimg'];
        } else {
            $wakkaConfig['favorite_background_image'] = BACKGROUND_IMAGE_PAR_DEFAUT;
        }
    } else {
        // si les metas sont présentes on les utilise
        if (isset($metadatas['theme']) && isset($metadatas['style']) && isset($metadatas['squelette'])) {
            $wakkaConfig['favorite_theme'] = $metadatas['theme'];
            $wakkaConfig['favorite_style'] = $metadatas['style'];
            $wakkaConfig['favorite_squelette'] = $metadatas['squelette'];
            if (isset($metadatas['bgimg'])) {
                $wakkaConfig['favorite_background_image'] = $metadatas['bgimg'];
            } else {
                $wakkaConfig['favorite_background_image'] = '';
            }
        } else {
            if (!isset($wakkaConfig['favorite_theme'])) {
                $wakkaConfig['favorite_theme'] = THEME_PAR_DEFAUT;
            }
            if (!isset($wakkaConfig['favorite_style'])) {
                $wakkaConfig['favorite_style'] = CSS_PAR_DEFAUT;
            }
            if (!isset($wakkaConfig['favorite_squelette'])) {
                $wakkaConfig['favorite_squelette'] = SQUELETTE_PAR_DEFAUT;
            }
            if (!isset($wakkaConfig['favorite_background_image'])) {
                $wakkaConfig['favorite_background_image'] = BACKGROUND_IMAGE_PAR_DEFAUT;
            }
        }
    }
}

// Test existence du template, on utilise le template par defaut sinon==============================
if (
    (!file_exists('custom/themes/'.$wakkaConfig['favorite_theme'].'/squelettes/'.$wakkaConfig['favorite_squelette'])
    and !file_exists('themes/'.$wakkaConfig['favorite_theme'].'/squelettes/'.$wakkaConfig['favorite_squelette']))
    || (!file_exists('custom/themes/'.$wakkaConfig['favorite_theme'].'/styles/'.$wakkaConfig['favorite_style'])
    && !file_exists('themes/'.$wakkaConfig['favorite_theme'].'/styles/'.$wakkaConfig['favorite_style']))
) {
    if (
        $wakkaConfig['favorite_theme'] != THEME_PAR_DEFAUT || 
        (
            $wakkaConfig['favorite_theme'] == THEME_PAR_DEFAUT && (!file_exists('themes/'.THEME_PAR_DEFAUT.'/squelettes/'.$wakkaConfig['favorite_squelette'])  or
        !file_exists('themes/'.THEME_PAR_DEFAUT.'/styles/'.$wakkaConfig['favorite_style']))
        )
    ) {
        if (
            file_exists('themes/'.THEME_PAR_DEFAUT.'/squelettes/'.SQUELETTE_PAR_DEFAUT)
            && file_exists('themes/'.THEME_PAR_DEFAUT.'/styles/'.CSS_PAR_DEFAUT)
        ) {  
            $GLOBALS['template-error']['type'] = 'theme-not-found';
            $GLOBALS['template-error']['theme'] = $wakkaConfig['favorite_theme'];
            $GLOBALS['template-error']['style'] = $wakkaConfig['favorite_style'];
            $GLOBALS['template-error']['squelette'] = $wakkaConfig['favorite_squelette'];
            $wakkaConfig['favorite_theme'] = THEME_PAR_DEFAUT;
            $wakkaConfig['favorite_style'] = CSS_PAR_DEFAUT;
            $wakkaConfig['favorite_squelette'] = SQUELETTE_PAR_DEFAUT;
            $wakkaConfig['favorite_background_image'] = BACKGROUND_IMAGE_PAR_DEFAUT;
        } else {
            exit('<div class="alert alert-danger">'._t('TEMPLATE_NO_DEFAULT_THEME').'.</div>');
        }
    }
    $wakkaConfig['use_fallback_theme'] = true;
}

// themes folder (used by {{update}})
$wakkaConfig['templates'] = [];
if (is_dir('themes')) {
    $wakkaConfig['templates'] = array_merge($wakkaConfig['templates'], search_template_files('themes'));
}
// custom themes folder
if (is_dir('custom/themes')) {
    $wakkaConfig['templates'] = array_merge($wakkaConfig['templates'], search_template_files('custom/themes'));
}
ksort($wakkaConfig['templates']);
