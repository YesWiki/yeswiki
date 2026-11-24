<?php

namespace YesWiki\Render\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\YesWikiController;
use YesWiki\Files\Service\ImageResizer;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\LanguageService;

class ThemeSelectorRenderer extends YesWikiController
{
    protected ParameterBagInterface $params;
    protected HibernationService $hibernationService;
    protected ThemeManager $themeManager;

    public function __construct(
        ParameterBagInterface $params,
        HibernationService $hibernationService,
        ThemeManager $themeManager
    ) {
        $this->params = $params;
        $this->hibernationService = $hibernationService;
        $this->themeManager = $themeManager;
    }

    /** @param array<string, mixed> $params */
    public function renderWithThemeSelector(string $templateName, array $params): string
    {
        $templates = $this->themeManager->getTemplates();
        $favoriteTheme = $this->themeManager->getFavoriteTheme();
        $favoriteSquelette = $this->themeManager->getFavoriteSquelette();
        $favoriteStyle = $this->themeManager->getFavoriteStyle();
        $favoritePreset = $this->themeManager->getFavoritePreset();
        $squelettes = $templates[$favoriteTheme]['squelette'];
        $styles = $templates[$favoriteTheme]['style'];
        $presetsData = $this->themeManager->getPresetsData() ?? [
            'themePresets' => [],
            'selectedPresetName' => null,
            'customCSSPresets' => [],
            'selectedCustomPresetName' => null,
        ];
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
     * @param string $mode
     * @param string $formclass
     */
    public function showFormThemeSelector($mode = 'selector', $formclass = ''): string
    {
        if ($mode == 'edit') {
            $id = 'form_graphical_options';
            $bgselector = $this->backgroundSelector();
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
            'availableLanguages' => $this->getService(LanguageService::class)->availableLanguages(),
            'preferedLanguage' => $this->getService(LanguageService::class)->preferredLanguage(),
            'languagesList' => $this->getService(LanguageService::class)->languagesList(),
            'page' => $this->getService(\YesWiki\Kernel\Service\PageContext::class)->getPage(),
            'updateUrl' => ($mode !== 'edit'),
        ] + $this->pagesOwnThemeNotice($mode));
    }

    /** The carousel of background images a page can pick from, empty when the wiki has none. */
    public function backgroundSelector(): string
    {
        $backgrounds = $this->prepareBackgrounds();

        return empty($backgrounds) ? '' : $this->render('@core/background-selector.twig', [
            'backgrounds' => $backgrounds,
            'favoriteBackgroundImage' => $this->themeManager->getFavoriteBackgroundImage(),
        ]);
    }

    /** @return array<string, mixed> this page's own theme and how many pages keep one, for an admin choosing the site theme */
    private function pagesOwnThemeNotice(string $mode): array
    {
        if ($mode === 'edit' || !$this->getService(AclService::class)->isAdmin() || ($this->params->has('hide_action_template') && $this->params->get('hide_action_template') == '1')) {
            return [];
        }
        $tag = (string)$this->getService(\YesWiki\Kernel\Service\PageContext::class)->getTag();
        $ownTheme = $tag === '' ? null : $this->themeManager->getOwnTheme($tag);

        return [
            'pageOwnTheme' => $ownTheme === null ? '' : implode(' / ', [
                $ownTheme['theme'],
                preg_replace('/\\.css$/', '', $ownTheme['style']),
                preg_replace('/\\.tpl\\.html$/', '', $ownTheme['squelette']),
            ]),
            'pagesWithOwnTheme' => $this->themeManager->countPagesWithOwnTheme(),
        ];
    }

    /** @return list<array{file: string, path: string, kind: string}> every background on offer, its file name, the image shown in the selector (a thumbnail for a photo) and how it is laid out */
    protected function prepareBackgrounds(): array
    {
        $backgrounds = [];
        $backgroundsdir = 'files/backgrounds';
        $storage = $this->getService(Storage::class);
        foreach ($storage->files($backgroundsdir) as $path) {
            $file = basename($path);
            $kind = ThemeManager::backgroundKind($file);
            if ($kind === 'tile') {
                $backgrounds[] = ['file' => $file, 'path' => $backgroundsdir . '/' . $file, 'kind' => $kind];
            } elseif ($kind === 'cover') {
                $thumbnail = $backgroundsdir . '/thumbs/' . $file;
                if ($storage->fileExists($thumbnail) || $this->getService(ImageResizer::class)->resize($backgroundsdir . '/' . $file, $thumbnail, 100, 75)) {
                    $backgrounds[] = ['file' => $file, 'path' => $thumbnail, 'kind' => $kind];
                }
            }
        }
        usort($backgrounds, static fn (array $a, array $b): int => strcmp($a['file'], $b['file']));

        return $backgrounds;
    }
}
