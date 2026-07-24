<?php

/**
 * Cette action à pour but de gérer massivement les droits sur les pages d'un wiki.
 * Les pages s'affichent et sont modifiées en fonction du squelette qu'elles utilisent (définis par l'utilisateur).
 */

use YesWiki\Core\Controller\ThemeController;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\ThemeManager;
use YesWiki\Core\YesWikiAction;

class GererThemesAction extends YesWikiAction
{
    protected $pageManager;
    protected $themeController;
    protected $themeManager;

    public function run()
    {
        if (!$this->wiki->UserIsAdmin()) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ACLS_RESERVED_FOR_ADMINS'),
            ]);
        }

        // get services
        $this->pageManager = $this->getService(PageManager::class);
        $this->themeController = $this->getService(ThemeController::class);
        $this->themeManager = $this->getService(ThemeManager::class);

        $errorMessage = '';
        if ($this->getRequest()->request->has('theme_modifier')) {
            try {
                $this->modifyTheme();
            } catch (Exception $th) {
                if ($th->getCode() === 1) {
                    $errorMessage .= $th->getMessage();
                } else {
                    throw $th;
                }
            }
        }

        $pagesThemes = [];
        foreach ($this->pageManager->getAll() as $page) {
            if (!empty($page['tag'])) {
                $pagesThemes[$page['tag']] = array_merge(
                    [
                        'theme' => '',
                        'squelette' => '',
                        'style' => '',
                        'favorite_preset' => '',
                    ],
                    $this->pageManager->getMetadata($page['tag']) ?? []
                );
            }
        }

        return $this->themeController->renderWithThemeSelector(
            '@core/gerer-themes-action.twig',
            compact([
                'errorMessage',
                'pagesThemes',
            ])
        );
    }

    /**
     * @throws Exception with code 1
     */
    protected function modifyTheme()
    {
        $post = $this->getRequest()->request;
        if (!$post->has('selectpage')) {
            throw new Exception(_t('ACLS_NO_SELECTED_PAGE'), 1);
        } elseif (!is_array($post->all('selectpage'))) {
            throw new Exception('select page should be an array', 1);
        } else {
            $pagesTags = array_filter($post->all('selectpage'), 'is_string');
            foreach ($pagesTags as $pageTag) {
                if ($post->get('typemaj') === 'reinitialiser') {
                    $this->pageManager->setMetadata($pageTag, [
                        'theme' => null,
                        'style' => null,
                        'squelette' => null,
                        'favorite_preset' => null,
                    ]);
                } else {
                    $theme = $this->sanitizePost('theme_select');
                    $style = $this->sanitizePost('style_select');
                    $squelette = $this->sanitizePost('squelette_select');
                    $presets = $this->sanitizePost('preset_select');
                    $themes = $this->themeManager->getTemplates();
                    if (!isset($themes[$theme]['presets'])) {
                        $presets = '';
                    }
                    if (!empty($presets) && (substr($presets, -4) !== '.css')) {
                        $presets .= '.css';
                    }
                    $this->pageManager->setMetadata($pageTag, [
                        'theme' => $theme,
                        'style' => $style . (substr($style, -4) === '.css' ? '' : '.css'),
                        'squelette' => $squelette . (substr($squelette, -strlen('.tpl.html')) === '.tpl.html' ? '' : '.tpl.html'),
                    ] + (
                        !empty($post->get('preset_select'))
                ? [
                    'favorite_preset' => $presets,
                ]
                : []
                    ));
                }
            }
        }
    }

    /**
     * sanitize string from POST or return null.
     */
    protected function sanitizePost(string $key): ?string
    {
        $val = $this->getRequest()->request->get($key);
        return !empty($val) && is_string($val) ? $val : null;
    }
}
