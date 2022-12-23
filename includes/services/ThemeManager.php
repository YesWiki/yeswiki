<?php

namespace YesWiki\Core\Service;

use YesWiki\Wiki;
use YesWiki\Core\Service\Performer;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Security\Controller\SecurityController;

class ThemeManager
{
    public const CUSTOM_CSS_PRESETS_PATH = 'custom/css-presets';
    public const CUSTOM_CSS_PRESETS_PREFIX = 'custom/';
    public const CUSTOM_FONT_PATH = 'custom/fonts';
    public const SPECIAL_METADATA = [
        'PageFooter',
        'PageHeader',
        'PageTitre',
        'PageRapideHaut',
        'PageMenuHaut',
        'PageMenu',
        'favorite_preset'
    ];
    public const USER_AGENTS = [
        'eot' => 'Mozilla/2.0 (compatible; MSIE 3.01; Windows 98)',
        'woff' => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; fr; rv:1.9.2) Gecko/20100115 Firefox/3.6',
        'woff2' => 'Mozilla/5.0 (Windows NT 6.3; Win64; x64; rv:105.0) Gecko/20100101 Firefox/105.0',
        'truetype' => '',
    ];

    private const POST_DATA_KEYS = [
        'primary-color',
        'secondary-color-1',
        'secondary-color-2',
        'neutral-color',
        'neutral-soft-color',
        'neutral-light-color',
        'main-text-fontsize',
        'main-text-fontfamily',
        'main-title-fontfamily'
    ];

    protected $wiki;
    protected $config;
    protected $fileLoaded;
    protected $theme;
    protected $squelette;
    protected $fileContent;
    protected $errorMessage;
    protected $twig;
    protected $templateHeader;
    protected $templateFooter;
    protected $Performer;
    protected $securityController;

    public function __construct(Wiki $wiki, TemplateEngine $twig, Performer $Performer, SecurityController $securityController)
    {
        $this->wiki = $wiki;
        $this->twig = $twig;
        $this->Performer = $Performer;
        $this->securityController = $securityController;
        $this->config = $wiki->config;
        $this->fileLoaded = false;
        $this->theme = null;
        $this->squelette = null;
        $this->fileContent = null;
        $this->errorMessage = '';
        $this->templateHeader = '';
        $this->templateFooter = '';
    }

    /* function imported from tooles/templates/libs/templates.functions.php
     * to load templates and generate an error if needed
     *
     * @param $metadata metadata fr the current Page
     * @return array of templates
     */
    public function loadTemplates($metadata = []): ?array
    {
        // Premier cas le template par défaut est forcé : on ajoute ce qui est présent dans le fichier de configuration, ou le theme par defaut précisé ci dessus
        if (isset($this->config['hide_action_template']) && $this->config['hide_action_template'] == '1') {
            if (!isset($this->config['favorite_theme'])) {
                $this->config['favorite_theme'] = THEME_PAR_DEFAUT;
            }
            if (!isset($this->config['favorite_style'])) {
                $this->config['favorite_style'] = CSS_PAR_DEFAUT;
            }
            if (!isset($this->config['favorite_squelette'])) {
                $this->config['favorite_squelette'] = SQUELETTE_PAR_DEFAUT;
            }
            if (!isset($this->config['favorite_background_image'])) {
                $this->config['favorite_background_image'] = BACKGROUND_IMAGE_PAR_DEFAUT;
            }
        } else {
            // Sinon, on récupère premièrement les valeurs passées en REQUEST, ou deuxièmement les métasdonnées présentes pour la page, ou troisièmement les valeurs du fichier de configuration
            if (isset($_REQUEST['theme']) && (is_dir('custom/themes/'.$_REQUEST['theme']) || is_dir('themes/'.$_REQUEST['theme'])) &&
                isset($_REQUEST['style']) && (is_file('custom/themes/'.$_REQUEST['theme'].'/styles/'.$_REQUEST['style']) || is_file('themes/'.$_REQUEST['theme'].'/styles/'.$_REQUEST['style'])) &&
                isset($_REQUEST['squelette']) && (is_file('custom/themes/'.$_REQUEST['theme'].'/squelettes/'.$_REQUEST['squelette']) || is_file('themes/'.$_REQUEST['theme'].'/squelettes/'.$_REQUEST['squelette']))
            ) {
                $this->config['favorite_theme'] = $_REQUEST['theme'];
                $this->config['favorite_style'] = $_REQUEST['style'];
                $this->config['favorite_squelette'] = $_REQUEST['squelette'];

                // presets
                if (isset($_REQUEST['preset']) &&
                        (
                            (
                                ($isCustom = (substr($_REQUEST['preset'], 0, strlen(self::CUSTOM_CSS_PRESETS_PREFIX)) == self::CUSTOM_CSS_PRESETS_PREFIX))
                                && is_file(self::CUSTOM_CSS_PRESETS_PATH.'/'.substr($_REQUEST['preset'], strlen(self::CUSTOM_CSS_PRESETS_PREFIX)))
                            )
                            ||
                            (
                                !$isCustom &&
                                (
                                    is_file('custom/themes/'.$_REQUEST['theme'].'/presets/'.$_REQUEST['preset'])
                                    || is_file('themes/'.$_REQUEST['theme'].'/presets/'.$_REQUEST['preset'])
                                )
                            )
                        )
                ) {
                    $this->config['favorite_preset'] = $_REQUEST['preset'];
                }

                if (isset($_REQUEST['bgimg']) && (is_file('files/backgrounds/'.$_REQUEST['bgimg']))) {
                    $this->config['favorite_background_image'] = $_REQUEST['bgimg'];
                } else {
                    $this->config['favorite_background_image'] = BACKGROUND_IMAGE_PAR_DEFAUT;
                }
            } else {
                // si les metas sont présentes on les utilise
                if (isset($metadata['theme']) && isset($metadata['style']) && isset($metadata['squelette'])) {
                    $this->config['favorite_theme'] = $metadata['theme'];
                    $this->config['favorite_style'] = $metadata['style'];
                    $this->config['favorite_squelette'] = $metadata['squelette'];
                    if (!empty($metadata['favorite_preset'])) {
                        $this->config['favorite_preset'] = $metadata['favorite_preset'];
                    }
                    if (isset($metadata['bgimg'])) {
                        $this->config['favorite_background_image'] = $metadata['bgimg'];
                    } else {
                        $this->config['favorite_background_image'] = '';
                    }
                } else {
                    if (!isset($this->config['favorite_theme'])) {
                        $this->config['favorite_theme'] = THEME_PAR_DEFAUT;
                    }
                    if (!isset($this->config['favorite_style'])) {
                        $this->config['favorite_style'] = CSS_PAR_DEFAUT;
                    }
                    if (!isset($this->config['favorite_squelette'])) {
                        $this->config['favorite_squelette'] = SQUELETTE_PAR_DEFAUT;
                    }
                    if (!isset($this->config['favorite_background_image'])) {
                        $this->config['favorite_background_image'] = BACKGROUND_IMAGE_PAR_DEFAUT;
                    }
                }
            }
        }

        // Test existence du template, on utilise le template par defaut sinon==============================
        if (
            (!file_exists('custom/themes/'.$this->config['favorite_theme'].'/squelettes/'.$this->config['favorite_squelette'])
                and !file_exists('themes/'.$this->config['favorite_theme'].'/squelettes/'.$this->config['favorite_squelette']))
            || (!file_exists('custom/themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style'])
                && !file_exists('themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style']))
        ) {
            if (
                $this->config['favorite_theme'] != THEME_PAR_DEFAUT ||
                (
                    $this->config['favorite_theme'] == THEME_PAR_DEFAUT && (!file_exists('themes/'.THEME_PAR_DEFAUT.'/squelettes/'.$this->config['favorite_squelette'])  or
                        !file_exists('themes/'.THEME_PAR_DEFAUT.'/styles/'.$this->config['favorite_style']))
                )
            ) {
                if (
                    file_exists('themes/'.THEME_PAR_DEFAUT.'/squelettes/'.SQUELETTE_PAR_DEFAUT)
                    && file_exists('themes/'.THEME_PAR_DEFAUT.'/styles/'.CSS_PAR_DEFAUT)
                ) {
                    $GLOBALS['template-error']['type'] = 'theme-not-found';
                    $GLOBALS['template-error']['theme'] = $this->config['favorite_theme'];
                    $GLOBALS['template-error']['style'] = $this->config['favorite_style'];
                    $GLOBALS['template-error']['squelette'] = $this->config['favorite_squelette'];
                    $this->config['favorite_theme'] = THEME_PAR_DEFAUT;
                    $this->config['favorite_style'] = CSS_PAR_DEFAUT;
                    $this->config['favorite_squelette'] = SQUELETTE_PAR_DEFAUT;
                    $this->config['favorite_background_image'] = BACKGROUND_IMAGE_PAR_DEFAUT;
                } else {
                    return [];
                }
            }
            $this->config['use_fallback_theme'] = true;
        }
        // test l'existence du preset
        if (isset($this->config['favorite_preset'])
            &&
            (
                (
                    ($isCutom = substr($this->config['favorite_preset'], 0, strlen(self::CUSTOM_CSS_PRESETS_PREFIX)) == self::CUSTOM_CSS_PRESETS_PREFIX)
                    && !file_exists(self::CUSTOM_CSS_PRESETS_PATH.DIRECTORY_SEPARATOR
                        .substr($this->config['favorite_preset'], strlen(self::CUSTOM_CSS_PRESETS_PREFIX)))
                )
            )
        ) {
            unset($this->config['favorite_preset']);
        }

        $templates = [];

        // themes folder (used by {{update}})
        if (is_dir('themes')) {
            $templates = array_merge($templates, search_template_files('themes'));
        }
        // custom themes folder
        if (is_dir('custom/themes')) {
            $templates = array_replace_recursive($templates, search_template_files('custom/themes'));
        }
        ksort($templates);

        // update config
        $this->wiki->config = $this->config;

        return $templates;
    }

    public function loadTheme(): bool
    {
        // get theme
        $theme = $this->config['favorite_theme'] ?? THEME_PAR_DEFAUT ;

        // get squelette
        $squelette = $this->config['favorite_squelette'] ?? SQUELETTE_PAR_DEFAUT ;

        // do not load the file if already loaded
        $fileAlreadyLoaded = $this->fileLoaded &&
            ($this->theme == $theme) &&
            ($this->squelette == $squelette) ;
        if ($fileAlreadyLoaded) {
            return true;
        }
        $this->theme = $theme;
        $this->squelette = $squelette;

        // test folder
        $themePath = 'themes/'.$this->theme;
        $filePath = $themePath . '/squelettes/' . $this->squelette;

        $useFallbackTheme = !empty($this->config['use_fallback_theme']) ;

        if (!((!$useFallbackTheme && file_exists('custom/'.$themePath)) || file_exists($themePath))) {
            $this->errorMessage = $this->twig->render('@templates\alert-message.twig', [
                    'type' => 'danger',
                    'message' => _t('THEME_MANAGER_THEME_FOLDER') .$this->theme. _t('THEME_MANAGER_NOT_FOUND'),
                ]);
            return false;
        }

        if (!((!$useFallbackTheme &&file_exists('custom/'.$filePath)) || file_exists($filePath))) {
            $this->errorMessage = $this->twig->render('@templates\alert-message.twig', [
                    'type' => 'danger',
                    'message' => _t('THEME_MANAGER_SQUELETTE_FILE') .$this->squelette. _t('THEME_MANAGER_NOT_FOUND'),
                ]);
            return false;
        }
        $filePath = (!$useFallbackTheme && file_exists('custom/'.$filePath)) ? 'custom/'. $filePath : $filePath;

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            $this->errorMessage = $this->twig->render('@templates\alert-message.twig', [
                'type' => 'danger',
                'message' => _t('THEME_MANAGER_ERROR_GETTING_FILE') .$filePath,
            ]);
            return false;
        }
        $this->fileContent = $fileContent;
        $this->fileLoaded = true;

        // On recupere la partie haut du template et on execute les actions wikini
        $templateCut = explode("{WIKINI_PAGE}", $fileContent);
        $this->templateHeader = $templateCut[0] ?? '';
        // ADD flash message just before page content
        $this->templateHeader .= flash()->display();
        $this->templateFooter = (count($templateCut) > 0) ? $templateCut[1] : '';

        return true;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function renderHeader(): string
    {
        if ($this->fileLoaded || $this->loadTheme()) {
            return $this->renderActions($this->templateHeader);
        } else {
            return '';
        }
    }

    public function renderFooter(): string
    {
        if ($this->fileLoaded || $this->loadTheme()) {
            return $this->renderActions($this->templateFooter);
        } else {
            return '';
        }
    }

    private function renderActions(string $text): ?string
    {
        if ($act = preg_match_all("/".'(\\{\\{)'.'(.*?)'.'(\\}\\})'."/is", $text, $matches)) {
            $i = 0;
            $j = 0;
            foreach ($matches as $valeur) {
                foreach ($valeur as $val) {
                    if (isset($matches[2][$j]) && $matches[2][$j]!='') {
                        $action = $matches[2][$j];
                        $text = str_replace('{{'.$action.'}}', $this->Performer->run('action', 'formatter', ['text'=>'{{'.$action.'}}']), $text);
                    }
                    $j++;
                }
                $i++;
            }
        }

        return $text;
    }

    /**
     * get custom css-presets
     * @return array $template = [$filename=>$css]
     */
    public function getCustomCSSPresets(): array
    {
        $path = self::CUSTOM_CSS_PRESETS_PATH;
        $tab = [];
        $cssFiles = glob($path.DIRECTORY_SEPARATOR.'*.css');
        foreach ($cssFiles as $filepath) {
            $filename = pathinfo($filepath)['filename'].'.css';
            $css = file_get_contents($filepath);
            if (!empty($css)) {
                $tab[$filename] = $css;
            }
        }
        return $tab;
    }

    /**
     * delete a css custom preset
     * @param string $filename
     * @return array ['status' => bool, 'message' => '...']
     */
    public function deleteCustomCSSPreset(string $filename): array
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $path = self::CUSTOM_CSS_PRESETS_PATH;
        if (!$this->wiki->UserIsAdmin()) {
            return ['status'=>false,'message'=>'User is not admin'];
        }
        if (!file_exists($path.DIRECTORY_SEPARATOR.$filename)) {
            return ['status'=>false,'message'=>'File '.$filename.' is not existing !'];
        }
        unlink($path.DIRECTORY_SEPARATOR.$filename);
        if (!file_exists($path.DIRECTORY_SEPARATOR.$filename)) {
            return ['status'=>true,'message'=>''];
        }
        return ['status'=>false,'message'=>'Not possible to delete '.$filename];
    }



    /**
     * add a css custom preset (only admins can change a file)
     * @param string $filename
     * @param array $post
     * @return array ['status' => bool, 'message' => '...','errorCode'=>0]
     *   errorCode : 0 : not connected user
     *               1 : bad post data
     *               2 : file already existing but user not admin
     *               3 : custom/css-presets not existing and not possible to create it
     *               4 : file not created
     */
    public function addCustomCSSPreset(string $filename, array $post): array
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$this->wiki->getUser()) {
            return ['status'=>false,'message'=>'Not connected user','errorCode'=>0];
        }

        if (!$this->checkPOSTToAddCustomCSSPreset($post)) {
            return ['status'=>false,'message'=>'Bad post data','errorCode'=>1];
        }
        $path = self::CUSTOM_CSS_PRESETS_PATH;

        $fileContent = ":root {\r\n";
        foreach (self::POST_DATA_KEYS as $key) {
            $fileContent .= '  --'.$key.': '.$post[$key].";\r\n";
        }
        $fileContent .= "}\r\n";

        if (file_exists($path.DIRECTORY_SEPARATOR.$filename) && !$this->wiki->UserIsAdmin()) {
            return ['status'=>false,'message'=>'File already existing but user not admin','errorCode'=>2];
        }
        // check if folder exists
        if (!is_dir($path)) {
            if (!mkdir($path)) {
                return ['status'=>false,'message'=>$path.' not existing and not possible to create it','errorCode'=>3];
            }
        }
        // create or update
        file_put_contents($path.DIRECTORY_SEPARATOR.$filename, $fileContent);
        $data = (file_exists($path.DIRECTORY_SEPARATOR.$filename))
            ? ['status'=>true,'message'=>$filename.' created/updated','errorCode'=>null]
            : ['status'=>false,'message'=>$filename.' not created','errorCode'=>4];

        $filePath = self::CUSTOM_CSS_PRESETS_PATH.DIRECTORY_SEPARATOR.$filename;
        if ($data['status'] && file_exists($filePath)) {
            // append font data

            $mainTextFontFamily = (empty($post['main-text-fontfamily']) || !is_string($post['main-text-fontfamily'])) ? '' : $post['main-text-fontfamily'];
            $fontString = empty($mainTextFontFamily) ? '' : $this->installAndGetCSSForFont($mainTextFontFamily);

            $mainTitleFontFamily = (empty($post['main-title-fontfamily']) || !is_string($post['main-title-fontfamily'])) ? '' : $post['main-title-fontfamily'];
            if ($mainTitleFontFamily != $mainTextFontFamily) {
                $newFontString = empty($mainTitleFontFamily) ? '' : $this->installAndGetCSSForFont($mainTitleFontFamily);
                if (!empty($newFontString)) {
                    $fontString .= "\n$newFontString";
                }
            }

            if (!empty($fontString)) {
                file_put_contents($filePath, "\n$fontString\n", FILE_APPEND);
            }
        }
        return $data;
    }

    /**
     * check post to add custom CSS Preset
     * @param array $post
     * @return bool
     */
    private function checkPOSTToAddCustomCSSPreset(array $post): bool
    {
        foreach (self::POST_DATA_KEYS as $key) {
            if (empty($post[$key])) {
                return false;
            }
        }
        return true;
    }

    /**
     * get presets data
     * @return array
     *      [
     *          'themePresets' => [],
     *          'dataHtmlForPresets' => [],
     *          'selectedPresetName' => ''/null,
     *          'customCSSPresets' => [],
     *          'dataHtmlForCustomCSSPresets' => [],
     *          'selectedCustomPresetName' => ''/null,
     *          'currentCSSValues' => [],
     *      ]
     */
    public function getPresetsData(): ?array
    {
        $themePresets = $this->wiki->config['templates'][$this->wiki->config['favorite_theme']]['presets'] ?? [];
        $dataHtmlForPresets = array_map(function ($value) {
            return $this->extractDataFromPreset($value);
        }, $themePresets);
        $customCSSPresets = $this->getCustomCSSPresets() ;
        $dataHtmlForCustomCSSPresets = array_map(function ($value) {
            return $this->extractDataFromPreset($value);
        }, $customCSSPresets);
        if (!empty($this->wiki->config['favorite_preset'])) {
            $presetName = $this->wiki->config['favorite_preset'];
            if (substr($presetName, 0, strlen(self::CUSTOM_CSS_PRESETS_PREFIX)) == self::CUSTOM_CSS_PRESETS_PREFIX) {
                $presetName = substr($presetName, strlen(self::CUSTOM_CSS_PRESETS_PREFIX));
                if (in_array($presetName, array_keys($customCSSPresets))) {
                    $currentCSSValues = $this->extractPropValuesFromPreset($customCSSPresets[$presetName]);
                    $selectedCustomPresetName = $presetName;
                }
            } else {
                if (in_array($presetName, array_keys($themePresets))) {
                    $currentCSSValues = $this->extractPropValuesFromPreset($themePresets[$presetName]);
                    $selectedPresetName = $presetName;
                }
            }
        }

        return [
            'themePresets' => $themePresets,
            'dataHtmlForPresets' => $dataHtmlForPresets,
            'selectedPresetName' => $selectedPresetName ?? null,
            'customCSSPresets' => $customCSSPresets,
            'dataHtmlForCustomCSSPresets' => $dataHtmlForCustomCSSPresets,
            'selectedCustomPresetName' => $selectedCustomPresetName ?? null,
            'currentCSSValues' => $currentCSSValues ?? [],
        ];
    }

    /**
     * extract data from preset
     * @param string $presetContent
     * @return string data to put in html
     */
    private function extractDataFromPreset(string $presetContent): string
    {
        $data = '';
        $values = $this->extractPropValuesFromPreset($presetContent);
        foreach ($values as $prop => $value) {
            $data .= ' data-'.$prop.'="'.str_replace('"', '\'', $value).'"';
        }
        if (
            !empty($data)
            && !empty($values['primary-color'])
            && !empty($values['main-text-fontsize']
            && !empty($values['main-text-fontfamily']))
        ) {
            $data .= ' style="';
            $data .= 'color:'.$values['primary-color'].';';
            $data .= 'font-family:'.str_replace('"', '\'', $values['main-text-fontfamily']).';';
            $data .= 'font-size:'.$values['main-text-fontsize'].';';
            $data .= '"';
        }
        return $data;
    }

    /**
     * extract properties values from preset contents
     * @param string $presetContent
     * @return array
     */
    private function extractPropValuesFromPreset(string $presetContent): array
    {
        // extract root part
        $matches = [];
        $results = [];
        $error = false;
        if (preg_match('/^:root\s*{((?:.|\n)*)}\s*[^{]*/', $presetContent, $matches)) {
            $vars = $matches[1];


            if (preg_match_all('/\s*--([0-9a-z\-]*):\s*([^;]*);\s*/', $vars, $matches)) {
                foreach ($matches[0] as $index => $val) {
                    $newmatch = [];
                    if (preg_match('/[a-z\-]*color[a-z0-9\-]*/', $matches[1][$index], $newmatch)) {
                        if (!preg_match('/^#[A-Fa-f0-9]*$/', $matches[2][$index], $newmatch)) {
                            $error = true;
                        }
                    }
                    $results[$matches[1][$index]] = $matches[2][$index];
                }
            }
        }
        return $error ? [] : $results;
    }


    /**
     * install font and get css
     * @param string $fontFamily
     * @return string $css
     */
    private function installAndGetCSSForFont(string $fontFamily): string
    {
        $css = '';
        $fontFamily = $this->cleanFont($fontFamily);
        if (!empty($fontFamily)) {
            $newCss = $this->getFontFiles($fontFamily);
            if (!empty($newCss)) {
                $css .= "\n$newCss";
            }
        }
        return $css;
    }

    protected function getFontFiles(string $fontFamily): string
    {
        $css = '';

        $fontFamilyForUrl = $this->convertFamilyToUrl($fontFamily);
        if (!empty($fontFamilyForUrl)) {
            $data = [];
            foreach (self::USER_AGENTS as $name => $value) {
                $data[$name] = $this->getFontDescription($fontFamilyForUrl, $name);
                if (empty($data[$name])) {
                    unset($data[$name]);
                }
            }
            $css = $this->formatCSS($data);
        }
        return $css;
    }

    protected function getFontDescription(string $fontFamily, string $userAgent): array
    {
        $data = [];
        $ch =  curl_init("https://fonts.googleapis.com/css?family=$fontFamily&subset=latin-ext");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $headers = ['Accept: text/css,*/*;q=0.1'];
        if (!empty(self::USER_AGENTS[$userAgent])) {
            $headers[] = 'User-Agent: '.self::USER_AGENTS[$userAgent];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        $errorNb = curl_errno($ch);
        curl_close($ch);
        if (!$errorNb) {
            $data = $this->parseCSS($result);
        }
        return $data;
    }

    protected function cleanFont(string $fontFamily): string
    {
        $fontFamily = explode(',', $fontFamily)[0];
        return str_replace(
            ['\''],
            [''],
            $fontFamily
        );
    }

    protected function convertFamilyToUrl(string $fontFamily): string
    {
        $fontFamily = $this->cleanFont($fontFamily);
        return str_replace(
            [' '],
            ['+'],
            $fontFamily
        );
    }

    protected function parseCSS(string $css): array
    {
        $data = [];
        $this->parseFontFace($css, "", $data);
        $this->parseFontFace($css, "latin", $data);
        $this->parseFontFace($css, "latin-ext", $data);

        return $data;
    }

    protected function parseFontFace(string $css, string $subset, array &$data)
    {
        $formattedSubSet = empty($subset) ? '' : "\/\*\s*".preg_quote($subset, "/")."\s*\*\/\s*";
        if (preg_match("/$formattedSubSet@font-face \{([^}]*)src: url\((https:\/\/fonts\.gstatic\.com\/[A-Za-z0-9_\-.\/]+)\)(?: format\('([A-Za-z0-9 \-_]+)'\))?([^}]*)\}/", $css, $match)) {
            $format = empty($match[3]) ? 'eot' : $match[3];
            $data[$subset] = [
                'url' => [
                    $format => $match[2]
                ]
            ];
            if (preg_match("/font-family: '([A-Za-z0-9 ]+)';/", $match[1], $familyMatch)) {
                $data[$subset]['family'] = $familyMatch[1];
            }
            if (preg_match("/font-style: ([A-Za-z0-9 ]+);/", $match[1], $styleMatch)) {
                $data[$subset]['style'] = $styleMatch[1];
            }
            if (preg_match("/font-weight: ([A-Za-z0-9 ]+);/", $match[1], $weightMatch)) {
                $data[$subset]['weight'] = $weightMatch[1];
            }
            if (preg_match("/unicode-range: ([A-Za-z0-9 \+,\-]+);/", $match[4], $rangeMatch)) {
                $data[$subset]['unicode-range'] = $rangeMatch[1];
            }
        }
    }

    protected function formatCSS(array $data): string
    {
        $css= "";
        $formattedData = [];
        foreach ($data as $userAgent => $values) {
            foreach ($values as $charset => $raw) {
                if (!empty($raw['family']) && !empty($raw['style']) && !empty($raw['weight'])) {
                    $key = "{$raw['family']}-{$raw['style']}-{$raw['weight']}";
                    if (!isset($formattedData[$key])) {
                        $formattedData[$key] = [
                            'family' => $raw['family'],
                            'style' => $raw['style'],
                            'weight' => $raw['weight'],
                            'charsets' => []
                        ];
                    }
                    foreach ($raw['url'] as $format => $url) {
                        if (!isset($formattedData[$key]['charsets'][$charset])) {
                            $formattedData[$key]['charsets'][$charset] = [
                                'url' => []
                            ];
                        }
                        if (isset($raw['unicode-range']) &&
                            !isset($formattedData[$key]['charsets'][$charset]['unicode-range'])) {
                            $formattedData[$key]['charsets'][$charset]['unicode-range'] = $raw['unicode-range'];
                        }
                        if (!isset($formattedData[$key]['charsets'][$charset]['url'][$format])) {
                            $formattedData[$key]['charsets'][$charset]['url'][$format] = $url;
                        }
                    }
                }
            }
        }
        if (!empty($formattedData)) {
            foreach ($formattedData as $raw) {
                foreach ($raw['charsets'] as $charset => $val) {
                    $eotUrl = $val['url']['eot'] ?? '';
                    $woff2Url = $val['url']['woff2'] ?? '';
                    $woffUrl = $val['url']['woff'] ?? '';
                    $truetypeUrl = $val['url']['truetype'] ?? '';
                    if (!empty($eotUrl)) {
                        $eotUrl = $this->importFontFile(
                            $raw['family'],
                            $raw['style'],
                            $raw['weight'],
                            $charset,
                            'eot',
                            $eotUrl
                        );
                        $eotUrl =  "\n  src: url('$eotUrl');";
                    }
                    foreach (['woff2','woff','truetype'] as $name) {
                        $varName = "{$name}Url";
                        $var = ${$varName};
                        if (!empty($var)) {
                            $var = $this->importFontFile(
                                $raw['family'],
                                $raw['style'],
                                $raw['weight'],
                                $charset,
                                $name,
                                $var
                            );
                            ${$varName} = ",\n        url('$var') format('$name')";
                        }
                    }
                    $unicodeRange = $val['unicode-range'] ?? '';
                    if (!empty($unicodeRange)) {
                        $unicodeRange = "\n  unicode-range: $unicodeRange;";
                    }

                    if (!empty($charset)) {
                        $css .=
                        <<<CSS

                        /* $charset */

                        CSS;
                    }

                    $css .=
                    <<<CSS
                    @font-face {
                      font-family: '{$raw['family']}';
                      font-style: {$raw['style']};
                      font-weight: {$raw['weight']};$eotUrl
                      src: local('')$woff2Url$woffUrl$truetypeUrl;$unicodeRange
                    }
                    CSS;
                }
            }
        }
        return $css;
    }

    protected function importFontFile(string $family, string $style, string $weight, string $charset, string $format, string $url): string
    {
        $folderSystemName = sanitizeFilename($family);
        if (!is_dir(self::CUSTOM_FONT_PATH."/$folderSystemName")) {
            mkdir(self::CUSTOM_FONT_PATH."/$folderSystemName", 0777, true);
        }

        switch ($format) {
            case 'eot':
                $ext=".eot";
                break;
            case 'woff2':
                $ext=".woff2";
                break;
            case 'woff':
                $ext=".woff";
                break;
            case 'truetype':
                $ext=".ttf";
                break;

            default:
                $ext="";
                break;
        }
        $fileName = sanitizeFilename("$family-$style-$weight-$charset").$ext;

        $ch =  curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $result = curl_exec($ch);
        $errorNb = curl_errno($ch);
        curl_close($ch);
        if (!$errorNb && !empty($result)) {
            if (file_put_contents(self::CUSTOM_FONT_PATH."/$folderSystemName/$fileName", $result) &&
                file_exists(self::CUSTOM_FONT_PATH."/$folderSystemName/$fileName")) {
                return "../../".self::CUSTOM_FONT_PATH."/$folderSystemName/$fileName";
            }
        }

        return $url;
    }
}
