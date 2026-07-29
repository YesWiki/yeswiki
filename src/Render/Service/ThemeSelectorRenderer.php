<?php

namespace YesWiki\Render\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;

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

    /**
     * render a template with theme-selector appending the right vars to params.
     */
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
        // en mode edition on recupere aussi les images de fond
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

        // page list
        $tablistWikinames = $this->getService(DbService::class)->loadAll(
            'SELECT DISTINCT tag FROM ' . $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)->getValue('table_prefix') . "pages WHERE latest='Y'"
        );
        $listWikinames = [];
        foreach ($tablistWikinames as $tag) {
            $listWikinames[] = $tag['tag'];
        }
        $listWikinames = '["' . implode('","', $listWikinames) . '"]';

        $ts = [
            'TEMPLATE_CHOOSE_FONT',
            'TEMPLATE_SEARCH_POINTS',
            'TEMPLATE_APPLY',
            'TEMPLATE_CANCEL',
            'TEMPLATE_THEME_NOT_SAVE',
            'TEMPLATE_FILE_NOT_ADDED',
            'TEMPLATE_FILE_NOT_DELETED',
            'TEMPLATE_FILE_ALREADY_EXISTING',
            'TEMPLATE_PRESET_ERROR',
        ];
        $ts = array_combine($ts, $ts);

        return $this->renderWithThemeSelector('@core/theme-selector-with-form.twig', [
            'mode' => $mode,
            'id' => $id,
            'class' => $formclass,
            'bgselector' => $bgselector,
            'listWikinames' => $listWikinames,
            'showAdminActions' => $this->getService(AclService::class)->isAdmin(),
            'themeSelectorTranslation' => array_map('_t', $ts),
            'customCSSPresetsPath' => ThemeManager::CUSTOM_CSS_PRESETS_PATH,
            'customCSSPresetsPrefix' => ThemeManager::CUSTOM_CSS_PRESETS_PREFIX,
            'availableLanguages' => $GLOBALS['available_languages'],
            'preferedLanguage' => $GLOBALS['prefered_language'],
            'languagesList' => $GLOBALS['languages_list'],
            'page' => $this->getService(\YesWiki\Kernel\Service\PageContext::class)->getPage(),
            'updateUrl' => ($mode !== 'edit'),
        ]);
    }

    /**
     * prepare backgrounds.
     */
    protected function prepareBackgrounds(): array
    {
        $backgrounds = [];
        $backgroundsdir = 'files/backgrounds';
        $dir = (is_dir($backgroundsdir) ? opendir($backgroundsdir) : false);
        while ($dir && ($file = readdir($dir)) !== false) {
            $imgextension = strtolower(substr($file, -4, 4));
            // les jpg sont les fonds d'ecrans, ils doivent etre mis en miniature
            if ($imgextension == '.jpg') {
                if (!is_file($backgroundsdir . '/thumbs/' . $file)) {
                    $imgTrans = new \Zebra_Image();
                    $imgTrans->auto_handle_exif_orientation = true;
                    $imgTrans->preserve_aspect_ratio = true;
                    $imgTrans->enlarge_smaller_images = true;
                    $imgTrans->preserve_time = true;
                    $imgTrans->handle_exif_orientation_tag = true;
                    $imgTrans->source_path = $backgroundsdir . '/' . $file;
                    $imgTrans->target_path = $backgroundsdir . '/thumbs/' . $file;
                    if ($imgTrans->resize(intval(100), intval(75), ZEBRA_IMAGE_NOT_BOXED, '#FFFFFF')) {
                        $backgrounds[] = $imgTrans->target_path;
                    }
                } else {
                    $backgrounds[] = $backgroundsdir . '/thumbs/' . $file;
                }
            } elseif ($imgextension == '.png') {
                // les png sont les images a repeter en mosaique
                $backgrounds[] = $backgroundsdir . '/' . $file;
            }
        }
        if ($dir) {
            closedir($dir);
        }

        return $backgrounds;
    }
}
