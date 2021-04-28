<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

require_once 'tools/templates/libs/setwikidefaulttheme.functions.php';

if (!is_writable('wakka.config.php')) {
    echo '<div class="alert alert-danger">'
        . _t('ERROR_NO_ACCESS')
        . " setwikidefaulttheme, le fichier de configuration est protégé en écriture</div>\n";
} else {
    if ($this->UserIsAdmin()) {
        $themes = getTemplatesList();
        include_once 'tools/templates/libs/Configuration.php';
        $config = new Configuration('wakka.config.php');
        $config->load();

        // load defaut params from config after LoadExtensions
        $config->favorite_theme = $GLOBALS['wiki']->config['favorite_theme'] ?? $config->favorite_theme ?? null;
        $config->favorite_squelette = $GLOBALS['wiki']->config['favorite_squelette'] ?? $config->favorite_squelette ?? null;
        $config->favorite_style = $GLOBALS['wiki']->config['favorite_style'] ?? $config->favorite_style ?? null;

        if (isset($_POST['action']) and $_POST['action'] === 'setTemplate') {
            $params = checkParamActionSetTemplate($_POST, $themes);
            if ($params !== false) {
                $config->favorite_theme = $params['theme'];
                $config->favorite_squelette = $params['squelette'];
                $config->favorite_style = $params['style'];
                unset($config->hide_action_template);
                if ($params['forceTheme']) {
                    $config->hide_action_template = '1';
                }
                $config->write();
                $this->Redirect($this->href("", $this->tag));
            }
        }
        showSelectTemplateForm($themes, $config);
    } else {
        echo '<div class="alert alert-danger">'
            . _t('ERROR_NO_ACCESS')
            . " setwikidefaulttheme</div>\n";
    }
}
