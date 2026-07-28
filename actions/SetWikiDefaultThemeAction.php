<?php

use YesWiki\Render\Service\ThemeSelectorRenderer;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Render\Service\ThemeManager;
use YesWiki\Core\YesWikiAction;

class SetWikiDefaultThemeAction extends YesWikiAction
{
    protected $hibernationService;
    protected $themeSelectorRenderer;
    protected $themeManager;

    public function run()
    {
        if (!$this->wiki->UserIsAdmin()) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ERROR_NO_ACCESS') . ' setwikidefaulttheme',
            ]);
        }
        if (!is_writable(ConfigurationFileProvider::getConfigFileFromEnv())) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ERROR_NO_ACCESS') . ' setwikidefaulttheme, ' . _t('FILE_WRITE_PROTECTED'),
            ]);
        }

        $this->hibernationService = $this->getService(HibernationService::class);
        $this->themeSelectorRenderer = $this->getService(ThemeSelectorRenderer::class);
        $this->themeManager = $this->getService(ThemeManager::class);

        $themes = $this->getTemplatesList();
        $config = $this->getService(ConfigurationService::class)->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();

        if ($this->getRequest()->request->get('action') === 'setTemplate') {
            if ($this->hibernationService->isWikiHibernated()) {
                return $this->getMessageWhenHibernated();
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

        return $this->themeSelectorRenderer->renderWithThemeSelector(
            '@core/set-default-theme.twig',
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
        $post = $this->getRequest()->request;
        if (!$post->has('theme_select') || !$post->has('style_select') || !$post->has('squelette_select')) {
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
            'forceTheme' => ($this->getRequest()->request->get('forceTheme') === 'on'),
        ];
    }

    /**
     * sanitize string from POST or return null.
     */
    protected function sanitizePost(string $key): ?string
    {
        $raw = $this->getRequest()->request->get($key);
        if (empty($raw) || !is_string($raw)) {
            return '';
        }
        $val = filter_var($raw, FILTER_UNSAFE_RAW);

        return in_array($val, [false, null], true) ? '' : htmlspecialchars(strip_tags($val));
    }
}
