<?php

/*
Internationalisation of YesWiki

Copyright 2013 Florian Schmitt <florian@outils-reseaux.org>
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

namespace YesWiki\Kernel\Service {
    /**
     * Language detection and translation loading.
     *
     * Runs from the very start of the bootstrap (before the configuration file, the
     * database or the service container exist), so it has no constructor dependencies;
     * getInstance() gives the pre-container callers and the global _t() function the
     * same instance the container serves (see the factory in services.yaml).
     *
     * The loaded catalogs stay in $GLOBALS ('translations', 'translations_js',
     * 'prefered_language', 'available_languages', 'languages_list'): the extensions'
     * lang/*.inc.php files merge into those arrays directly and many templates and
     * actions read them, so they are the shared state, not this class.
     */
    class LanguageService
    {
        public const SUPPORTED_LANGUAGES = ['ca', 'en', 'es', 'fr', 'nl', 'pt', 'ro'];

        private static $instance;

        public static function getInstance(): self
        {
            return self::$instance ?? self::$instance = new self();
        }

        /**
         * Initialize the translation state: constants, languages list, base (french)
         * catalogs, available languages and preferred language.
         *
         * Historical behavior kept: french is loaded first as the fallback catalog,
         * being the most updated because we are a french dev team ;)
         */
        public function initialize(): void
        {
            if (!defined('YW_CHARSET')) {
                // pre-boot: reads the raw property, the container does not exist yet
                define('YW_CHARSET', $GLOBALS['wiki']->config['charset'] ?? 'UTF-8');
            }
            if (!defined('SUPPORTED_LANGS')) {
                define('SUPPORTED_LANGS', self::SUPPORTED_LANGUAGES);
            }

            // get the language list ($GLOBALS['languages_list'])
            require_once $this->langDir() . '/languages_list.php';

            $this->loadTranslations(require_once $this->langDir() . '/yeswiki_fr.php');
            if (file_exists($this->langDir() . '/yeswikijs_fr.php')) {
                $this->loadTranslations(require_once $this->langDir() . '/yeswikijs_fr.php', true);
            }

            $GLOBALS['available_languages'] = $this->detectAvailableLanguages();
            $wiki = $GLOBALS['wiki'] ?? '';
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
         * Automatically detects the languages available in the lang dir,
         * filtered by officially supported languages.
         *
         * @return string[] available languages
         */
        public function detectAvailableLanguages(): array
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
         * Priority: lang= GET parameter, then the (posted) installer configuration, then
         * the page's metadata, then the configured default_language, then content
         * negotiation on the Accept-Language header (based on
         * http://php.net/manual/en/function.http-negotiate-language.php#example-4353).
         *
         * @param \YesWiki\Wiki|object|string $wiki               the wiki, an object exposing ->config, or '' before boot
         * @param array                       $availableLanguages language-tag-strings (must be lowercase) that are available
         * @param string                      $httpAcceptLanguage a HTTP_ACCEPT_LANGUAGE string ('auto' reads $_SERVER)
         * @param string                      $page               name of WikiPage to check for informations on language
         */
        public function detectPreferredLanguage($wiki, array $availableLanguages, string $httpAcceptLanguage = 'auto', ?string $page = ''): string
        {
            // sanitize parameters
            $getLang = (isset($_GET['lang']) && in_array($_GET['lang'], $availableLanguages)) ? $_GET['lang'] : '';

            $pageMetadataLang = '';
            if ($page != '' && $wiki instanceof \YesWiki\Wiki) {
                // page's metadata lang
                $metadata = $wiki->services->get(\YesWiki\Content\Service\PageManager::class)->getMetadata($page);
                $wiki->services->get(PageContext::class)->setMetadata(is_array($metadata) ? $metadata : []);
                if (isset($metadata['lang']) && in_array($metadata['lang'], $availableLanguages)) {
                    $pageMetadataLang = $metadata['lang'];
                }
            }

            // first priority
            if (!empty($getLang)) {
                return $getLang;
            }

            $postConfigLang = '';
            if (isset($_POST['config'])) {
                // just for installation
                $conf = $_POST['config'];
                if (is_string($conf)) {
                    // the installer retry form posts the whole config as a JSON string
                    $conf = json_decode(html_entity_decode($conf), true) ?? [];
                }
                if (isset($conf['default_language']) && in_array($conf['default_language'], $availableLanguages)) {
                    $postConfigLang = $conf['default_language'];
                }
            }

            // second priority
            if (!empty($postConfigLang)) {
                return $postConfigLang;
            }

            // default language from config file
            // pre-boot tolerant: $wiki may be '' or an un-booted Wiki here, so read the raw property
            $configLang = !empty($wiki) && isset($wiki->config['default_language']) && in_array($wiki->config['default_language'], $availableLanguages)
                ? $wiki->config['default_language'] : '';

            $httpAcceptLang = ($httpAcceptLanguage !== 'auto') ? $httpAcceptLanguage : ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');

            // third priority
            if (!empty($pageMetadataLang)) {
                return $pageMetadataLang;
            }

            // fourth priority if 'auto' or other word not representing an available lang, allow usage of http_accept_language
            if (!empty($configLang)) {
                return $configLang;
            }

            // fifth priority 'httpAcceptLang'

            // standard  for HTTP_ACCEPT_LANGUAGE is defined under
            // http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.4
            // pattern to find is therefore something like this:
            //    1#( language-range [ ";" "q" "=" qvalue ] )
            // where:
            //    language-range  = ( ( 1*8ALPHA *( "-" 1*8ALPHA ) ) | "*" )
            //    qvalue         = ( "0" [ "." 0*3DIGIT ] )
            //            | ( "1" [ "." 0*3("0") ] )
            preg_match_all(
                '/([[:alpha:]]{1,8})(-([[:alpha:]|-]{1,8}))?'
                    . "(\s*;\s*q\s*=\s*(1\.0{0,3}|0\.\d{0,3}))?\s*(,|$)/i",
                $httpAcceptLang,
                $hits,
                PREG_SET_ORDER
            );

            // default language (in case of no hits) is french, like the devs speak
            $bestLang = 'fr';
            $bestQval = 0;

            foreach ($hits as $arr) {
                // read data from the array of this hit
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

                // find q-maximal language
                if (in_array($language, $availableLanguages) && ($qValue > $bestQval)) {
                    $bestLang = $language;
                    $bestQval = $qValue;
                } elseif (in_array($langPrefix, $availableLanguages) && (($qValue * 0.9) > $bestQval)) {
                    // if no direct hit, try the prefix only but decrease q-value by 10% (as http_negotiate_language does)
                    $bestLang = $langPrefix;
                    $bestQval = $qValue * 0.9;
                }
            }

            return $bestLang;
        }

        /**
         * Update the translations, based on the information from current page.
         * Must be run once initialize() was.
         *
         * @param \YesWiki\Wiki|object|string $wiki the wiki, an object exposing ->config, or '' before boot
         * @param string                      $page name of current WikiPage to check for informations on language
         */
        public function loadPreferredLanguage($wiki, ?string $page = ''): void
        {
            $lang = $this->detectPreferredLanguage($wiki, $GLOBALS['available_languages'], 'auto', $page);
            $GLOBALS['prefered_language'] = $lang;

            if ($lang != 'fr' && file_exists($this->langDir() . '/yeswiki_' . $lang . '.php')) {
                // this will overwrite the values of $GLOBALS['translations'] in the selected language
                $this->loadTranslations(include_once $this->langDir() . '/yeswiki_' . $lang . '.php');
            }
            if ($lang != 'fr' && file_exists($this->langDir() . '/yeswikijs_' . $lang . '.php')) {
                $this->loadTranslations(include_once $this->langDir() . '/yeswikijs_' . $lang . '.php', true);
            }
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

        private function langDir(): string
        {
            // Anchored on the source root rather than counted up from __DIR__: the depth of
            // this file changed when ticket 05 moved it from src/services/ to
            // src/Kernel/Service/, and a dirname(__DIR__, 2) silently started resolving to
            // src/lang instead of lang/. Relative __DIR__ arithmetic breaks on any move.
            return YESWIKI_SOURCE_DIR . '/lang';
        }
    }
}

namespace {
    use YesWiki\Kernel\Service\LanguageService;

    /**
     * Translate the text in the page's language.
     *
     * The one global i18n function: it is the translation API of the whole ecosystem
     * (actions, handlers, extensions, lang files), so it stays a plain function.
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
