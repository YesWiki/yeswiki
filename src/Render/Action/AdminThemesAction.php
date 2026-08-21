<?php

namespace YesWiki\Render\Action;

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Render\Service\ThemeManager;
use YesWiki\Render\Service\ThemeSelectorRenderer;

class AdminThemesAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{adminthemes}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'adminthemes';
    }

    public function components(): array
    {
        return [
            Component::for('adminthemes')
                ->category(Category::Admin)
                ->label(_t('AB_management_gererthemes_label'))
                ->icon('brush')
                ->previewHeight('200px')
                ->adminOnly(),
        ];
    }

    protected PageManager $pageManager;
    protected ThemeSelectorRenderer $themeSelectorRenderer;
    protected ThemeManager $themeManager;

    /**
     * @return string the theme administration screen, or an admins-only message
     */
    public function run()
    {
        if (!$this->getService(AclService::class)->isAdmin()) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ACLS_RESERVED_FOR_ADMINS'),
            ]);
        }

        $this->pageManager = $this->getService(PageManager::class);
        $this->themeSelectorRenderer = $this->getService(ThemeSelectorRenderer::class);
        $this->themeManager = $this->getService(ThemeManager::class);

        $errorMessage = '';
        if ($this->getRequest()->request->has('theme_modifier')) {
            try {
                $this->modifyTheme();
            } catch (\Exception $th) {
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

        return $this->themeSelectorRenderer->renderWithThemeSelector(
            '@core/gerer-themes-action.twig',
            compact([
                'errorMessage',
                'pagesThemes',
            ])
        );
    }

    /**
     * @throws \Exception with code 1 when no page was selected
     */
    protected function modifyTheme(): void
    {
        $post = $this->getRequest()->request;
        if (!$post->has('selectpage')) {
            throw new \Exception(_t('ACLS_NO_SELECTED_PAGE'), 1);
        }
        $pagesTags = array_filter($post->all('selectpage'), 'is_string');
        foreach ($pagesTags as $pageTag) {
            if ($post->get('updatetype') === 'reinitialiser') {
                $this->pageManager->setMetadata($pageTag, [
                    'theme' => null,
                    'style' => null,
                    'squelette' => null,
                    'favorite_preset' => null,
                ]);
            } else {
                $theme = $this->sanitizePost('theme_select');
                $style = $this->sanitizePost('style_select') ?? '';
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
                    'squelette' => ThemeManager::squeletteFileName((string)$squelette),
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

    /** sanitize string from POST or return null. */
    protected function sanitizePost(string $key): ?string
    {
        $val = $this->getRequest()->request->get($key);

        return !empty($val) && is_string($val) ? $val : null;
    }
}
