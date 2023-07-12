<?php
/**
 * Fichier de lancement et de configuration de l'extension Templates.
 */
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

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
