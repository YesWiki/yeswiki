<?php

function getTemplatesList()
{
    //on cherche tous les dossiers du repertoire themes et des sous dossier styles
    //et squelettes, et on les range dans le tableau $wakkaConfig['templates']
    $repertoire_initial = 'themes';
    $GLOBALS['wiki']->config['templates'] = search_template_files($repertoire_initial);

    //s'il y a un repertoire themes a la racine, on va aussi chercher les templates dedans
    if (is_dir('themes')) {
        $repertoire_racine = 'themes';
        $GLOBALS['wiki']->config['templates'] = array_merge(
            $GLOBALS['wiki']->config['templates'],
            search_template_files($repertoire_racine)
        );
        if (is_array($GLOBALS['wiki']->config['templates'])) {
            ksort($GLOBALS['wiki']->config['templates']);
        }
    }

    // Réorganisation des données avant de les rendre.
    $themes = array();
    foreach ($GLOBALS['wiki']->config['templates'] as $templateName => $templateValues) {
        $themes[$templateName] = array(
            'styles' => array_keys($templateValues['style']),
            'squelettes' => array_keys($templateValues['squelette']),
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

    $defStyle = '';
    if (isset($config->favorite_style)) {
        $defStyle = $config->favorite_style;
    }

    $defSquelette = '';
    if (isset($config->favorite_squelette)) {
        $defSquelette = $config->favorite_squelette;
    }

    $defForceTheme = false;
    if (isset($config->hide_action_template) and $config->hide_action_template === '1') {
        $defForceTheme = true;
    }

    include('tools/templates/presentation/templates/setwikidefaulttheme.tpl.html');
}

function checkParamActionSetTemplate($post, $availableThemes)
{
    if (!isset($post['wdtTheme']) or !isset($post['wdtStyle']) or !isset($post['wdtSquelette'])) {
        return false;
    }

    $post['wdtTheme'] = filter_var($post['wdtTheme'], FILTER_SANITIZE_STRING);
    $post['wdtStyle'] = filter_var($post['wdtStyle'], FILTER_SANITIZE_STRING);
    $post['wdtSquelette'] = filter_var($post['wdtSquelette'], FILTER_SANITIZE_STRING);

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

    $params['forceTheme'] = false;
    if (isset($post['wdtForceTheme']) and $post['wdtForceTheme'] === 'on') {
        $params['forceTheme'] = true;
    }

    return $params;
}
