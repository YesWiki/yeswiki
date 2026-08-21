<?php

namespace YesWiki\Render\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\YesWikiController;
use YesWiki\Files\Service\ImageResizer;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\LanguageService;

class ThemeSelectorRenderer extends YesWikiController
{
    protected $params;
    protected $hibernationService;
    protected $themeManager;

    public function __construct(
        ParameterBagInterface $params,
        HibernationService $hibernationService,
        ThemeManager $themeManager
    ) {
        $this->params = $params;
        $this->hibernationService = $hibernationService;
        $this->themeManager = $themeManager;
    }

    /** render a template with theme-selector appending the right vars to params. */
    public function renderWithThemeSelector(string $templateName, array $params): string
    {
        $templates = $this->themeManager->getTemplates();
        $favoriteTheme = $this->themeManager->getFavoriteTheme();
        $favoriteSquelette = $this->themeManager->getFavoriteSquelette();
        $favoriteStyle = $this->themeManager->getFavoriteStyle();
        $favoritePreset = $this->themeManager->getFavoritePreset();
        $squelettes = $templates[$favoriteTheme]['squelette'];
        $styles = $templates[$favoriteTheme]['style'];
        $presetsData = $this->themeManager->getPresetsData();
        $presets = [];
        foreach ($presetsData['themePresets'] as $key => $content) {
            $presets[$key] = $key;
        }
        foreach ($presetsData['customCSSPresets'] as $key => $content) {
            $presets[ThemeManager::CUSTOM_CSS_PRESETS_PREFIX . $key] = $key;
        }
        $dataTemplates = array_map(function ($t) {
            return array_key_exists('presets', $t)
                ? array_merge($t, [
                    'presets' => array_keys($t['presets']),
                ])
                : $t;
        }, $templates);
        $hibernated = $this->hibernationService->isWikiHibernated();

        return $this->render(
            $templateName,
            array_merge(
                compact([
                    'templates',
                    'favoriteTheme',
                    'favoriteSquelette',
                    'favoriteStyle',
                    'favoritePreset',
                    'squelettes',
                    'styles',
                    'presets',
                    'dataTemplates',
                    'hibernated',
                    'presetsData',
                ]),
                $params
            )
        );
    }

    /**
     * render form theme selector.
     *
     * @param string $mode
     * @param string $formclass
     */
    public function showFormThemeSelector($mode = 'selector', $formclass = ''): string
    {
        if ($mode == 'edit') {
            $id = 'form_graphical_options';
            $backgrounds = $this->prepareBackgrounds();
            $bgselector =
            !empty($backgrounds)
            ? $this->render('@core/background-selector.twig', [
                'backgrounds' => $backgrounds,
                'favoriteBackgroundImage' => $this->themeManager->getFavoriteBackgroundImage(),
            ])
            : '';
        } else {
            $id = 'form_theme_selector';
            $bgselector = '';
        }

        $tablistWikinames = $this->getService(DbService::class)->loadAll(
            'SELECT DISTINCT tag FROM ' . $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)->getValue('table_prefix') . "pages WHERE latest='Y'"
        );
        $listWikinames = [];
        foreach ($tablistWikinames as $tag) {
            $listWikinames[] = $tag['tag'];
        }
        $listWikinames = '["' . implode('","', $listWikinames) . '"]';

        return $this->renderWithThemeSelector('@core/theme-selector-with-form.twig', [
            'mode' => $mode,
            'id' => $id,
            'class' => $formclass,
            'bgselector' => $bgselector,
            'listWikinames' => $listWikinames,
            'showAdminActions' => $this->getService(AclService::class)->isAdmin(),
            'availableLanguages' => $GLOBALS['available_languages'],
            'preferedLanguage' => $this->getService(LanguageService::class)->preferredLanguage(),
            'languagesList' => $GLOBALS['languages_list'],
            'page' => $this->getService(\YesWiki\Kernel\Service\PageContext::class)->getPage(),
            'updateUrl' => ($mode !== 'edit'),
        ]);
    }

    /** prepare backgrounds. */
    protected function prepareBackgrounds(): array
    {
        $backgrounds = [];
        $backgroundsdir = 'files/backgrounds';
        $dir = (is_dir($backgroundsdir) ? opendir($backgroundsdir) : false);
        while ($dir && ($file = readdir($dir)) !== false) {
            $imgextension = strtolower(substr($file, -4, 4));

            if ($imgextension == '.jpg') {
                $thumbnail = $backgroundsdir . '/thumbs/' . $file;
                if (!is_file($thumbnail)) {
                    if ($this->getService(ImageResizer::class)->resize($backgroundsdir . '/' . $file, $thumbnail, 100, 75)) {
                        $backgrounds[] = $thumbnail;
                    }
                } else {
                    $backgrounds[] = $thumbnail;
                }
            } elseif ($imgextension == '.png') {
                $backgrounds[] = $backgroundsdir . '/' . $file;
            }
        }
        if ($dir) {
            closedir($dir);
        }

        return $backgrounds;
    }
}
