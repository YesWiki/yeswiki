<?php

namespace YesWiki\Kernel\Service {
    /** Language detection and translation loading. */
    class LanguageService
    {
        public const SUPPORTED_LANGUAGES = ['ca', 'en', 'es', 'fr', 'nl', 'pt', 'ro'];

        /** Where a reader's choice of language is kept. */
        public const COOKIE = 'yw-lang';
        private const COOKIE_LIFETIME = 31536000;

        private static $instance;

        public static function getInstance(): self
        {
            return self::$instance ?? self::$instance = new self();
        }

        /**
         * Initialize the translation state: constants, languages list, base (french) catalogs, available languages and preferred language.
         */
        public function initialize(): void
        {
            if (!defined('YW_CHARSET')) {
                define('YW_CHARSET', $GLOBALS['wiki']->config['charset'] ?? 'UTF-8');
            }
            if (!defined('SUPPORTED_LANGS')) {
                define('SUPPORTED_LANGS', self::SUPPORTED_LANGUAGES);
            }

            require_once $this->langDir() . '/languages_list.php';

            $this->loadTranslations(require_once $this->langDir() . '/yeswiki_fr.php');
            if (file_exists($this->langDir() . '/yeswikijs_fr.php')) {
                $this->loadTranslations(require_once $this->langDir() . '/yeswikijs_fr.php', true);
            }

            $wiki = $GLOBALS['wiki'] ?? '';

            $GLOBALS['installed_languages'] = $this->installedLanguages();
            $GLOBALS['available_languages'] = $this->offeredLanguages($wiki, $GLOBALS['installed_languages']);
            $GLOBALS['prefered_language'] = $this->detectPreferredLanguage($wiki, $GLOBALS['available_languages']);
        }

        /**
         * Translate a text key in the page's language.
         *
         * @param string $textKey key for the text
         * @param array  $params  values replacing the %{name} placeholders of the translation
         *
         * @return string the translated text, or the key itself if not found
         */
        public function translate($textKey, array $params = []): string
        {
            $result = $GLOBALS['translations'][$textKey] ?? $textKey;
            foreach ($params as $transKey => $value) {
                $result = str_replace('%{' . $transKey . '}', $value, $result);
            }

            return $result;
        }

        /**
         * The languages this wiki offers its readers: its own, plus any it has turned on.
         *
         * @param \YesWiki\YesWikiRuntime|object|string $wiki      the runtime, an object exposing ->config, or '' before boot
         * @param string[]                              $installed what is available to offer
         *
         * @return string[]
         */
        public function offeredLanguages($wiki, array $installed): array
        {
            if (!($wiki instanceof \YesWiki\YesWikiRuntime) || !is_array($wiki->config)) {
                return $installed;
            }

            $offered = [];
            $default = $wiki->config['default_language'] ?? '';
            if (is_string($default) && in_array($default, $installed, true)) {
                $offered[] = $default;
            }
            foreach ((array)($wiki->config['other_languages'] ?? []) as $language) {
                if (is_string($language) && in_array($language, $installed, true) && !in_array($language, $offered, true)) {
                    $offered[] = $language;
                }
            }

            return $offered === [] ? $installed : $offered;
        }

        /**
         * Automatically detects the languages installed in the lang dir, filtered by officially supported languages.
         *
         * @return string[] installed languages
         */
        public function installedLanguages(): array
        {
            $availableLanguages = [];
            if ($d = @opendir($this->langDir())) {
                while (($f = readdir($d)) !== false) {
                    if (preg_match(',^yeswiki_([a-z_]+)\.php[3]?$,', $f, $regs)) {
                        if (in_array($regs[1], self::SUPPORTED_LANGUAGES)) {
                            $availableLanguages[] = $regs[1];
                        }
                    }
                }
                closedir($d);
                sort($availableLanguages);
            }

            return $availableLanguages;
        }

        /**
         * Determine which language out of an available set the user prefers most.
         *
         * @param \YesWiki\YesWikiRuntime|object|string $wiki               the runtime, an object exposing ->config, or '' before boot
         * @param array                                 $availableLanguages language-tag-strings (must be lowercase) that are available
         * @param string                                $httpAcceptLanguage a HTTP_ACCEPT_LANGUAGE string ('auto' reads $_SERVER)
         * @param string                                $page               name of WikiPage to check for informations on language
         */
        public function detectPreferredLanguage($wiki, array $availableLanguages, string $httpAcceptLanguage = 'auto', ?string $page = ''): string
        {
            $getLang = (isset($_GET['lang']) && in_array($_GET['lang'], $availableLanguages)) ? $_GET['lang'] : '';
            $cookieLang = (isset($_COOKIE[self::COOKIE]) && in_array($_COOKIE[self::COOKIE], $availableLanguages))
                ? (string)$_COOKIE[self::COOKIE]
                : '';

            $pageMetadataLang = '';
            if ($page != '' && $wiki instanceof \YesWiki\YesWikiRuntime) {
                $metadata = $wiki->services->get(\YesWiki\Content\Service\PageManager::class)->getMetadata($page);
                $wiki->services->get(PageContext::class)->setMetadata(is_array($metadata) ? $metadata : []);
                if (isset($metadata['lang']) && in_array($metadata['lang'], $availableLanguages)) {
                    $pageMetadataLang = $metadata['lang'];
                }
            }

            if (!empty($getLang)) {
                return $getLang;
            }

            if (!empty($cookieLang)) {
                return $cookieLang;
            }

            $postConfigLang = '';
            if (isset($_POST['config'])) {
                $conf = $_POST['config'];
                if (is_string($conf)) {
                    $conf = json_decode(html_entity_decode($conf), true) ?? [];
                }
                if (isset($conf['default_language']) && in_array($conf['default_language'], $availableLanguages)) {
                    $postConfigLang = $conf['default_language'];
                }
            }

            if (!empty($postConfigLang)) {
                return $postConfigLang;
            }

            $configLang = !empty($wiki) && isset($wiki->config['default_language']) && in_array($wiki->config['default_language'], $availableLanguages)
                ? $wiki->config['default_language'] : '';

            $httpAcceptLang = ($httpAcceptLanguage !== 'auto') ? $httpAcceptLanguage : ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');

            if (!empty($pageMetadataLang)) {
                return $pageMetadataLang;
            }

            preg_match_all(
                '/([[:alpha:]]{1,8})(-([[:alpha:]|-]{1,8}))?'
                    . "(\s*;\s*q\s*=\s*(1\.0{0,3}|0\.\d{0,3}))?\s*(,|$)/i",
                $httpAcceptLang,
                $hits,
                PREG_SET_ORDER
            );

            $bestLang = $configLang ?: 'fr';
            $bestQval = 0;

            foreach ($hits as $arr) {
                $langPrefix = strtolower($arr[1]);
                if (!empty($arr[3])) {
                    $langRange = strtolower($arr[3]);
                    $language = $langPrefix . '-' . $langRange;
                } else {
                    $language = $langPrefix;
                }
                $qValue = 1.0;
                if (!empty($arr[5])) {
                    $qValue = floatval($arr[5]);
                }

                if (in_array($language, $availableLanguages) && ($qValue > $bestQval)) {
                    $bestLang = $language;
                    $bestQval = $qValue;
                } elseif (in_array($langPrefix, $availableLanguages) && (($qValue * 0.9) > $bestQval)) {
                    $bestLang = $langPrefix;
                    $bestQval = $qValue * 0.9;
                }
            }

            return $bestLang;
        }

        /**
         * Update the translations, based on the information from current page.
         *
         * @param \YesWiki\YesWikiRuntime|object|string $wiki the runtime, an object exposing ->config, or '' before boot
         * @param string                                $page name of current WikiPage to check for informations on language
         */
        public function loadPreferredLanguage($wiki, ?string $page = ''): void
        {
            $GLOBALS['installed_languages'] ??= $this->installedLanguages();
            $GLOBALS['available_languages'] = $this->offeredLanguages($wiki, $GLOBALS['installed_languages']);

            $lang = $this->detectPreferredLanguage($wiki, $GLOBALS['available_languages'], 'auto', $page);
            $GLOBALS['prefered_language'] = $lang;
            $this->rememberChoice($lang, $GLOBALS['available_languages']);

            if ($lang != 'fr' && file_exists($this->langDir() . '/yeswiki_' . $lang . '.php')) {
                $this->loadTranslations(include_once $this->langDir() . '/yeswiki_' . $lang . '.php');
            }
            if ($lang != 'fr' && file_exists($this->langDir() . '/yeswikijs_' . $lang . '.php')) {
                $this->loadTranslations(include_once $this->langDir() . '/yeswikijs_' . $lang . '.php', true);
            }

            $this->projectJavascriptKeys();
        }

        /**
         * Keep an explicit `?lang=` choice, so the next page needs no parameter.
         *
         * @param string[] $available the languages this wiki offers
         */
        private function rememberChoice(string $lang, array $available): void
        {
            $asked = isset($_GET['lang']) ? (string)$_GET['lang'] : '';
            if ($asked === '' || $asked !== $lang || !in_array($lang, $available, true)) {
                return;
            }
            if (($_COOKIE[self::COOKIE] ?? '') === $lang) {
                return;
            }

            if (\PHP_SAPI === 'cli' || headers_sent()) {
                return;
            }

            $_COOKIE[self::COOKIE] = $lang;
            setcookie(self::COOKIE, $lang, [
                'expires' => time() + self::COOKIE_LIFETIME,
                'path' => '/',
                'samesite' => 'Lax',

                'httponly' => true,
                'secure' => !empty($_SERVER['HTTPS']),
            ]);
        }

        /** Copy the keys the shipped scripts ask for from the PHP catalog into the javascript one. */
        private function projectJavascriptKeys(): void
        {
            $keysFile = $this->langDir() . '/javascript-keys.php';
            if (!file_exists($keysFile)) {
                return;
            }

            $wanted = array_flip((array)require $keysFile);
            $fromPhp = array_intersect_key($GLOBALS['translations'] ?? [], $wanted);
            $GLOBALS['translations_js'] = array_merge($fromPhp, $GLOBALS['translations_js'] ?? []);
        }

        /**
         * Merge a catalog into the loaded translations.
         *
         * @param mixed $translations array of key => translation (anything else is ignored,
         *                            the lang files return true when already included once)
         * @param bool  $jsMode       merge into the javascript translations instead
         */
        public function loadTranslations($translations, bool $jsMode = false): void
        {
            $translationName = $jsMode ? 'translations_js' : 'translations';
            if (is_array($translations)) {
                $GLOBALS[$translationName] = array_merge($GLOBALS[$translationName] ?? [], $translations);
            }
        }

        /** Copy named keys from the PHP catalog into the javascript one. */
        public function loadJavascriptTranslations(string ...$keys): void
        {
            $wanted = array_intersect_key($GLOBALS['translations'] ?? [], array_flip($keys));
            $this->loadTranslations($wanted, true);
        }

        private function langDir(): string
        {
            return YESWIKI_SOURCE_DIR . '/src/lang';
        }
    }
}

namespace {
    use YesWiki\Kernel\Service\LanguageService;

    /**
     * Translate the text in the page's language.
     *
     * @param string $textkey array key for the text
     * @param array  $params  values replacing the %{name} placeholders
     *
     * @return string the translated text or the key if not found
     */
    function _t($textkey, $params = [])
    {
        return LanguageService::getInstance()->translate($textkey, $params);
    }
}
