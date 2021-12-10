<?php
use YesWiki\Security\Controller\SecurityController;

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

require_once 'tools/templates/libs/setwikidefaulttheme.functions.php';

if (!is_writable('wakka.config.php')) {
    echo '<div class="alert alert-danger">'
        . _t('ERROR_NO_ACCESS')
        . " setwikidefaulttheme, "._t('FILE_WRITE_PROTECTED')."</div>\n";
} else {
    if ($this->UserIsAdmin()) {
        $themes = getTemplatesList();
        include_once 'tools/templates/libs/Configuration.php';
        $config = new Configuration('wakka.config.php');
        $config->load();

        if (isset($_POST['action']) and $_POST['action'] === 'setTemplate') {
            $params = checkParamActionSetTemplate($_POST, $themes);
            if ($this->services->get(SecurityController::class)->isWikiHibernated()) {
                echo $this->services->get(SecurityController::class)->getMessageWhenHibernated();
            } elseif ($params !== false) {
                $config->favorite_theme = $params['theme'];
                $config->favorite_squelette = $params['squelette'];
                $config->favorite_style = $params['style'];
                if (!empty($config->favorite_preset) && empty($params['preset'])) {
                    unset($config->favorite_preset);
                } elseif (!empty($params['preset'])) {
                    $config->favorite_preset = $params['preset'];
                }
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
