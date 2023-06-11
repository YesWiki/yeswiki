<?php

use YesWiki\Core\Service\ThemeManager;

function getTemplatesList()
{
    // Réorganisation des données avant de les rendre.
    $themeManager = $GLOBALS['wiki']->services->get(ThemeManager::class);
    $themes = array();
    foreach ($themeManager->getTemplates() as $templateName => $templateValues) {
        $themes[$templateName] = array(
            'styles' => array_keys($templateValues['style']),
            'squelettes' => array_keys($templateValues['squelette']),
        ) + (
            (empty($templateValues['presets']))
             ? []
             : ['presets' => $templateValues['presets']]
        );
    }

    return $themes;
}

function showSelectTemplateForm($themes, $config)
{
    $defTheme = '';
    if (isset($config->favorite_theme)) {
        $defTheme = $config->favorite_theme;
    }
    // load defaut params from config after LoadExtensions
    $themeManager = $GLOBALS['wiki']->services->get(ThemeManager::class);
    $defTheme = $config->favorite_theme ?? $themeManager->getFavoriteTheme();
    $defSquelette = $config->favorite_squelette ?? $themeManager->getFavoriteSquelette();
    $defStyle = $config->favorite_style ?? $themeManager->getFavoriteStyle();

    $defForceTheme = false;
    if (isset($config->hide_action_template) and $config->hide_action_template === '1') {
        $defForceTheme = true;
    }

    // define vars for presets
    $wiki = $GLOBALS['wiki'];
    $presetsData = $wiki->services->get(ThemeManager::class)->getPresetsData();
    $customCSSPresets = $presetsData['customCSSPresets'];
    $selectedPresetName =  $presetsData['selectedPresetName'] ??  null;
    $selectedCustomPresetName =  $presetsData['selectedCustomPresetName'] ??  null;

    include('tools/templates/presentation/templates/setwikidefaulttheme.tpl.html');
}

function checkParamActionSetTemplate($post, $availableThemes)
{
    if (!isset($post['wdtTheme']) or !isset($post['wdtStyle']) or !isset($post['wdtSquelette'])) {
        return false;
    }

    $post['wdtTheme'] = filter_var($post['wdtTheme'], FILTER_UNSAFE_RAW);
    $post['wdtTheme']  = in_array($post['wdtTheme'], [false,null], true) ? "" : htmlspecialchars(strip_tags($post['wdtTheme']));
    $post['wdtStyle'] = filter_var($post['wdtStyle'], FILTER_UNSAFE_RAW);
    $post['wdtStyle']  = in_array($post['wdtStyle'], [false,null], true) ? "" : htmlspecialchars(strip_tags($post['wdtStyle']));
    $post['wdtSquelette'] = filter_var($post['wdtSquelette'], FILTER_UNSAFE_RAW);
    $post['wdtSquelette']  = in_array($post['wdtSquelette'], [false,null], true) ? "" : htmlspecialchars(strip_tags($post['wdtSquelette']));

    // Vérifie la validité du thème.
    if (!array_key_exists($post['wdtTheme'], $availableThemes)) {
        return false;
    }
    $params = array('theme' => $post['wdtTheme']);

    // Vérifie la validité du style.
    if (!in_array($post['wdtStyle'], $availableThemes[$params['theme']]['styles'])) {
        return false;
    }
    $params['style'] = $post['wdtStyle'];

    // Vérifie la validité du squelette.
    if (!in_array($post['wdtSquelette'], $availableThemes[$params['theme']]['squelettes'])) {
        return false;
    }
    $params['squelette'] = $post['wdtSquelette'];

    // Vérifie la validité du preset.
    $params['preset'] = $post['preset'] ?? null ;
    if (!empty($params['preset']) && substr($params['preset'], -4) != '.css') {
        return false;
    }

    $params['forceTheme'] = false;
    if (isset($post['wdtForceTheme']) and $post['wdtForceTheme'] === 'on') {
        $params['forceTheme'] = true;
    }

    return $params;
}
