<?php

use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Core\Service\ThemeManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Templates\Controller\ThemeController;

class SetWikiDefaultThemeAction extends YesWikiAction
{
    protected $securityController;
    protected $themeController;
    protected $themeManager;

    public function run()
    {
        if (!$this->wiki->UserIsAdmin()) {
            return $this->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ERROR_NO_ACCESS') . ' setwikidefaulttheme',
            ]);
        }
        if (!is_writable('wakka.config.php')) {
            return $this->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ERROR_NO_ACCESS') . ' setwikidefaulttheme, ' . _t('FILE_WRITE_PROTECTED'),
            ]);
        }

        $this->securityController = $this->getService(SecurityController::class);
        $this->themeController = $this->getService(ThemeController::class);
        $this->themeManager = $this->getService(ThemeManager::class);

        $themes = $this->getTemplatesList();
        $config = $this->getService(ConfigurationService::class)->getConfiguration('wakka.config.php');
        $config->load();

        if (isset($_POST['action']) and $_POST['action'] === 'setTemplate') {
            if ($this->securityController->isWikiHibernated()) {
                return $this->securityController->getMessageWhenHibernated();
            }
            $params = $this->checkParamActionSetTemplate($themes);

            if (!is_null($params)) {
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
                $this->wiki->Redirect($this->wiki->href('', $this->wiki->tag));
            }
        }

        $params = [
            'forceTheme' => isset($config->hide_action_template) && $config->hide_action_template === '1',
        ];
        // load defaut params from config after LoadExtensions
        if (isset($config->favorite_theme)) {
            $params['favoriteTheme'] = $config->favorite_theme;
        }
        if (isset($config->favorite_squelette)) {
            $params['favoriteSquelette'] = $config->favorite_squelette;
        }
        if (isset($config->favorite_style)) {
            $params['favoriteStyle'] = $config->favorite_style;
        }

        return $this->themeController->renderWithThemeSelector(
            '@templates/set-default-theme.twig',
            $params
        );
    }

    protected function getTemplatesList(): array
    {
        $themes = [];
        foreach ($this->themeManager->getTemplates() as $templateName => $templateValues) {
            $themes[$templateName] = [
                'styles' => array_keys($templateValues['style']),
                'squelettes' => array_keys($templateValues['squelette']),
            ] + (
                (empty($templateValues['presets']))
                ? []
                : ['presets' => $templateValues['presets']]
            );
        }

        return $themes;
    }

    protected function checkParamActionSetTemplate($availableThemes): ?array
    {
        if (!isset($_POST['theme_select']) || !isset($_POST['style_select']) || !isset($_POST['squelette_select'])) {
            return null;
        }

        $values = [
            'squelette' => $this->sanitizePost('squelette_select'),
            'style' => $this->sanitizePost('style_select'),
            'theme' => $this->sanitizePost('theme_select'),
            'preset' => $this->sanitizePost('preset_select'),
        ];
        if (!empty($values['squelette']) && substr($values['squelette'], -strlen('.tpl.html')) !== '.tpl.html') {
            $values['squelette'] .= '.tpl.html';
        }
        if (!empty($values['style']) && substr($values['style'], -4) !== '.css') {
            $values['style'] .= '.css';
        }
        if (!empty($values['preset']) && substr($values['preset'], -4) !== '.css') {
            $values['preset'] .= '.css';
        }

        if (!array_key_exists($values['theme'], $availableThemes)
            || !in_array($values['style'], $availableThemes[$values['theme']]['styles'])
            || !in_array($values['squelette'], $availableThemes[$values['theme']]['squelettes'])) {
            return null;
        }

        return [
            'theme' => $values['theme'],
            'style' => $values['style'],
            'squelette' => $values['squelette'],
            'preset' => (!array_key_exists('presets', $availableThemes[$values['theme']]) || empty($values['preset'])) ? null : $values['preset'],
            'forceTheme' => (isset($_POST['forceTheme']) && $_POST['forceTheme'] === 'on'),
        ];
    }

    /**
     * sanitize string from POST or return null.
     */
    protected function sanitizePost(string $key): ?string
    {
        if (empty($_POST[$key]) || !is_string($_POST[$key])) {
            return '';
        }
        $val = filter_var($_POST[$key], FILTER_UNSAFE_RAW);

        return in_array($val, [false, null], true) ? '' : htmlspecialchars(strip_tags($val));
    }
}
