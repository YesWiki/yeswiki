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

        /**
         * Where a reader's choice of language is kept.
         *
         * A cookie rather than the URL: `?lang=` only reaches the pages whose links YesWiki
         * itself generated, so a reader who followed a link somebody wrote by hand fell back
         * to the wiki's own language halfway through reading it. A year, because choosing
         * which language you read a wiki in is not a decision anybody wants to make twice.
         */
        public const COOKIE = 'yw-lang';
        private const COOKIE_LIFETIME = 31536000;

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

            $wiki = $GLOBALS['wiki'] ?? '';
            // what YesWiki is translated into HERE, and what this wiki chooses to offer of it:
            // two different questions, and the language switcher and the installer ask the
            // first while everything else asks the second
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
         * `other_languages` is the choice, made at install time and on the configuration
         * screen. **Its absence means the wiki offers the language it was installed in and no
         * other** -- which is what a wiki that has never been asked the question is actually
         * doing, and it is why a monolingual wiki shows no language switcher at all rather
         * than seven options nobody chose to publish.
         *
         * A `default_language` naming no installed language falls back to offering all of
         * them. That is not a feature: `auto` is no longer offered by either screen and a
         * migration rewrote the wikis that had it. It is what a configuration nobody can read
         * should do -- offer everything rather than nothing.
         *
         * @param \YesWiki\YesWikiRuntime|object|string $wiki      the runtime, an object exposing ->config, or '' before boot
         * @param string[]                              $installed what is available to offer
         *
         * @return string[]
         */
        public function offeredLanguages($wiki, array $installed): array
        {
            // Pre-boot, and the installer: everything YesWiki speaks is on the table. The
            // installer is where the choice is MADE -- restricting it there would stop its own
            // language select from working, since switching it asks for a `?lang=` the wiki
            // does not offer yet. Which is why this reads the runtime rather than any object
            // carrying a config: the installer passes the latter, deliberately.
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
         * Automatically detects the languages installed in the lang dir,
         * filtered by officially supported languages.
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
         * In order, and the order is the whole design:
         *
         *  1. **`?lang=`** -- somebody just asked for it. This is what the language switcher
         *     links to, and it is also what writes the cookie below.
         *  2. **The cookie** -- somebody asked for it before. A choice made once holds for
         *     every page afterwards, including pages reached by a link written by hand,
         *     which is what carrying the language in the URL could never do.
         *  3. The installer's posted configuration, which exists for one screen.
         *  4. **The page's own `lang` metadata** -- a page declaring what it is written in,
         *     which loses to a reader who has said what they read in.
         *  5. **The browser** (`Accept-Language`), negotiated over what this wiki OFFERS: a
         *     visitor arriving for the first time gets their own language when the wiki has
         *     it. It beats `default_language`, which is the answer for everyone else.
         *  6. **`default_language`**, which is a real language and not `auto`: a wiki says
         *     what it is written in, and "let the browser decide" is what step 5 already is.
         *
         * The last two swapped places here: the configured default used to win, so a wiki
         * offering English to an English reader still greeted them in its own language and
         * the offer meant nothing until they found the switcher.
         *
         * @param \YesWiki\YesWikiRuntime|object|string $wiki               the runtime, an object exposing ->config, or '' before boot
         * @param array                                 $availableLanguages language-tag-strings (must be lowercase) that are available
         * @param string                                $httpAcceptLanguage a HTTP_ACCEPT_LANGUAGE string ('auto' reads $_SERVER)
         * @param string                                $page               name of WikiPage to check for informations on language
         */
        public function detectPreferredLanguage($wiki, array $availableLanguages, string $httpAcceptLanguage = 'auto', ?string $page = ''): string
        {
            // sanitize parameters
            $getLang = (isset($_GET['lang']) && in_array($_GET['lang'], $availableLanguages)) ? $_GET['lang'] : '';
            $cookieLang = (isset($_COOKIE[self::COOKIE]) && in_array($_COOKIE[self::COOKIE], $availableLanguages))
                ? (string)$_COOKIE[self::COOKIE]
                : '';

            $pageMetadataLang = '';
            if ($page != '' && $wiki instanceof \YesWiki\YesWikiRuntime) {
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

            // second: the choice they made last time
            if (!empty($cookieLang)) {
                return $cookieLang;
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

            if (!empty($postConfigLang)) {
                return $postConfigLang;
            }

            // default language from config file
            // pre-boot tolerant: $wiki may be '' or an un-booted Wiki here, so read the raw property
            $configLang = !empty($wiki) && isset($wiki->config['default_language']) && in_array($wiki->config['default_language'], $availableLanguages)
                ? $wiki->config['default_language'] : '';

            $httpAcceptLang = ($httpAcceptLanguage !== 'auto') ? $httpAcceptLanguage : ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');

            // the page says what it is written in, and a reader who has said nothing follows it
            if (!empty($pageMetadataLang)) {
                return $pageMetadataLang;
            }

            // then the browser, over what this wiki offers -- and `default_language` after it,
            // at the bottom of this function, for the visitor whose language is not on offer

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

            // What the wiki says it is written in, for a visitor whose browser asks for
            // something this wiki does not have. `'fr'` behind it is the last resort of a
            // configuration that names no language at all -- YesWiki's historical answer,
            // kept because something has to be returned and the devs speak French.
            $bestLang = $configLang ?: 'fr';
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
         * @param \YesWiki\YesWikiRuntime|object|string $wiki the runtime, an object exposing ->config, or '' before boot
         * @param string                                $page name of current WikiPage to check for informations on language
         */
        public function loadPreferredLanguage($wiki, ?string $page = ''): void
        {
            // Recomputed here, not only in initialize(): that one runs at file-load time,
            // before there is a configuration to read the wiki's choice from, so it can only
            // answer "everything installed". This is the first point at which the wiki can
            // say which of them it actually offers.
            $GLOBALS['installed_languages'] ??= $this->installedLanguages();
            $GLOBALS['available_languages'] = $this->offeredLanguages($wiki, $GLOBALS['installed_languages']);

            $lang = $this->detectPreferredLanguage($wiki, $GLOBALS['available_languages'], 'auto', $page);
            $GLOBALS['prefered_language'] = $lang;
            $this->rememberChoice($lang, $GLOBALS['available_languages']);

            if ($lang != 'fr' && file_exists($this->langDir() . '/yeswiki_' . $lang . '.php')) {
                // this will overwrite the values of $GLOBALS['translations'] in the selected language
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
         * Only when it was asked for in the URL: that is the language switcher's link, and it
         * is the one moment a reader has said which language they want. A cookie written from
         * anything else -- the browser's own header, the wiki's default -- would freeze the
         * first answer and stop both from ever being consulted again.
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
                return; // already what it says
            }
            // CLI has no headers to send, and a page that has started printing cannot grow one
            if (\PHP_SAPI === 'cli' || headers_sent()) {
                return;
            }

            $_COOKIE[self::COOKIE] = $lang;
            setcookie(self::COOKIE, $lang, [
                'expires' => time() + self::COOKIE_LIFETIME,
                'path' => '/',
                'samesite' => 'Lax',
                // readable by nothing but the wiki: no script needs it, and it is sent with
                // every request anyway
                'httponly' => true,
                'secure' => !empty($_SERVER['HTTPS']),
            ]);
        }

        /**
         * Copy the keys the shipped scripts ask for from the PHP catalog into the
         * javascript one.
         *
         * `_t()` in the browser reads `wiki.lang`, which is the javascript catalog and
         * nothing else -- so a key that lives only in the PHP catalog renders as its own
         * name. Two hand-written catalogs did not stay in step: 255 of the 335 keys the
         * scripts ask for were missing, which is how BAZ_ADJUST_MARKER_POSITION and
         * BAZ_FORM_INVALID_URL reached real pages as raw key names.
         *
         * The javascript catalog stays authoritative for the 131 keys that are its own;
         * this only fills what it does not define. The key list is generated -- see
         * src/build-js-lang-keys.php.
         */
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

        /**
         * Copy named keys from the PHP catalog into the javascript one.
         *
         * `_t()` in the browser reads `wiki.lang`, which holds `yeswikijs_*.php` and
         * nothing else -- a key that lives only in the PHP catalog renders as its own
         * name. Anything shipping a script with labels has to say which keys it needs;
         * this is how (the form designer does the same for its own).
         */
        public function loadJavascriptTranslations(string ...$keys): void
        {
            $wanted = array_intersect_key($GLOBALS['translations'] ?? [], array_flip($keys));
            $this->loadTranslations($wanted, true);
        }

        private function langDir(): string
        {
            // Anchored on the source root rather than counted up from __DIR__: the depth of
            // this file changed when ticket 05 moved it from src/services/ to
            // src/Kernel/Service/, and a dirname(__DIR__, 2) silently started resolving to
            // src/lang instead of lang/. Relative __DIR__ arithmetic breaks on any move.
            return YESWIKI_SOURCE_DIR . '/src/lang';
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
