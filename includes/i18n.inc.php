<?php
/*
Some usefull functions to deal with internationalisation

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

// Security check
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

/**
 * Translate the text in the page's language.
 *
 * @param string array key for the text or false if doesn't exists
 *
 * @return string the translated text or the key if not found
 */
function _t($textkey, $params = [])
{
    if (isset($GLOBALS['translations'][$textkey])) {
        $result = $GLOBALS['translations'][$textkey];
        foreach ($params as $transKey => $value) {
            $result = str_replace('%{' . $transKey . '}', $value, $result);
        }

        return $result;
    } else {
        return $textkey;
    }
}

/**
 * Convert the text in the page's charset, or in the datadase's charset
 * deprecated : now all is utf8mb4.
 *
 * @param mixed the text
 * @param string the page's encoding
 * @param bool is it for the database ?
 *
 * @return string the encoded text
 */
function _convert($text, $fromencoding, $database = false)
{
    include_once 'includes/Encoding.php';
    if (isset($GLOBALS['wiki']->config['db_charset']) and $GLOBALS['wiki']->config['db_charset'] == 'utf8mb4') {
        return $text;
    } elseif (is_array($text)) {
        $arraytext = [];
        foreach ($text as $key => $value) {
            $arraytext[$key] = _convert($value, $fromencoding, $database);
        }

        return $arraytext;
    } else {
        if ($database) {
            if ($fromencoding != 'ISO-8859-1' && $fromencoding != 'ISO-8859-15') {
                return mb_convert_encoding(
                    $text,
                    YW_CHARSET,
                    mb_detect_encoding($text, 'UTF-8, ISO-8859-1, ISO-8859-15', true)
                );
            //return \ForceUTF8\Encoding::toLatin1($text);
            } else {
                return $text;
            }
        } else {
            if (@iconv('utf-8', 'utf-8//IGNORE', $text) != $text) {
                $text = \ForceUTF8\Encoding::toUTF8($text);

                return \ForceUTF8\Encoding::fixUTF8($text);
            } else {
                //return $text;
                // if (strstr($text, 'disposition selon'))  {
                //   var_dump(strip_tags($text), \ForceUTF8\Encoding::fixUTF8(strip_tags($text)));
                //   exit;
                //
                // }

                return \ForceUTF8\Encoding::fixUTF8($text);
            }
        }
    }
}

/**
 * Automatically detects the languages available in the lang dir
 * But filtered by officially supported lang.
 *
 * @return array available languages
 */
function detectAvailableLanguages()
{
    $available_languages = [];
    if ($d = @opendir('lang')) {
        while (($f = readdir($d)) !== false) {
            if (preg_match(',^yeswiki_([a-z_]+)\.php[3]?$,', $f, $regs)) {
                if (in_array($regs[1], SUPPORTED_LANGS)) {
                    $available_languages[] = $regs[1];
                }
            }
        }
        closedir($d);
        sort($available_languages);
    }

    return $available_languages;
}

/**
 *  Determine which language out of an available set the user prefers most
 *  copied from http://php.net/manual/en/function.http-negotiate-language.php#example-4353.
 *
 *  @array $available_languages        array with language-tag-strings (must be lowercase) that are available
 *  @string $http_accept_language a HTTP_ACCEPT_LANGUAGE string (read from $_SERVER['HTTP_ACCEPT_LANGUAGE'] if left out)
 *  @string $page    name of WikiPage to check for informations on language
 */
function detectPreferedLanguage($wiki, $available_languages, $http_accept_language = 'auto', $page = '')
{
    // sanitize parameters
    $getLang = (isset($_GET['lang']) && in_array($_GET['lang'], $available_languages)) ? $_GET['lang'] : '';

    $pageMetadataLang = '';
    if ($page != '') {
        // page's metadata lang
        $wiki->metadatas = $wiki->GetMetaDatas($page);
        if (isset($wiki->metadatas['lang']) && in_array($wiki->metadatas['lang'], $available_languages)) {
            $pageMetadataLang = $wiki->metadatas['lang'];
        }
    }

    // first priority
    if (!empty($getLang)) {
        return $getLang;
    }

    $postConfigLang = '';
    if (isset($_POST['config'])) {
        // just for installation
        if (count($_POST['config']) == 1 && is_string($_POST['config'])) {
            if (version_compare(PHP_VERSION, '7.0.0') >= 0) {
                $conf = unserialize($_POST['config'], ['allowed_classes' => false]);
            } else {
                // workaround to avoid possibility of having classes
                $allowed_classes = false;
                $_POST['config'] = preg_replace_callback(
                    '/(?=^|:)(O|C):\d+:"([^"]*)":(\d+):{/',
                    function ($matches) use ($allowed_classes) {
                        if (is_array($allowed_classes) && in_array($matches[2], $allowed_classes)) {
                            return $matches[0];
                        } else {
                            return $matches[1] . ':22:"__PHP_Incomplete_Class":' .
                                ($matches[3] + 1) .
                                ':{s:27:"__PHP_Incomplete_Class_Name";' .
                                serialize($matches[2]);
                        }
                    },
                    $_POST['config']
                );
                $conf = unserialize($_POST['config']);
            }
            if (isset($conf['default_language']) && in_array($conf['default_language'], $available_languages)) {
                $postConfigLang = $conf['default_language'];
            }
        } elseif (
            isset($_POST['config']['default_language'])
            && in_array($_POST['config']['default_language'], $available_languages)
        ) {
            $postConfigLang = $_POST['config']['default_language'];
        }
    }

    // second priority
    if (!empty($postConfigLang)) {
        return $postConfigLang;
    }

    // default language from config file
    $configLang = !empty($wiki) && isset($wiki->config['default_language']) && in_array($wiki->config['default_language'], $available_languages)
        ? $wiki->config['default_language'] : '';

    $httpAcceptLang = ($http_accept_language !== 'auto') ? $http_accept_language : (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '');

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
    $bestlang = 'fr';
    $bestqval = 0;

    foreach ($hits as $arr) {
        // read data from the array of this hit
        $langprefix = strtolower($arr[1]);
        if (!empty($arr[3])) {
            $langrange = strtolower($arr[3]);
            $language = $langprefix . '-' . $langrange;
        } else {
            $language = $langprefix;
        }
        $qvalue = 1.0;
        if (!empty($arr[5])) {
            $qvalue = floatval($arr[5]);
        }

        // find q-maximal language
        if (in_array($language, $available_languages) && ($qvalue > $bestqval)) {
            $bestlang = $language;
            $bestqval = $qvalue;
        } elseif (in_array($langprefix, $available_languages) && (($qvalue * 0.9) > $bestqval)) {
            // if no direct hit, try the prefix only but decrease q-value by 10% (as http_negotiate_language does)
            $bestlang = $langprefix;
            $bestqval = $qvalue * 0.9;
        }
    }

    return $bestlang;
}

/**
 * Initialize the table of translation, based on the information gathered in the page.
 */
function initI18n()
{
    // initialize charset
    define(
        'YW_CHARSET',
        isset($GLOBALS['wiki']->config['charset']) ? $GLOBALS['wiki']->config['charset'] : 'UTF-8'
    );
    // supported languages
    define('SUPPORTED_LANGS', ['ca', 'en', 'es', 'fr', 'nl', 'pt']);

    // get the language list
    require_once 'lang/languages_list.php';

    // we initialise with french language, because it is the most beautiful ;) or maybe just the most updated because we are a french dev team
    $returnedArray = require_once 'lang/yeswiki_fr.php';
    load_translations($returnedArray);
    if (file_exists('lang/yeswikijs_fr.php')) {
        $returnedArray = require_once 'lang/yeswikijs_fr.php';
        load_translations($returnedArray, true);
    }

    $GLOBALS['available_languages'] = detectAvailableLanguages();
    $wiki = isset($GLOBALS['wiki']) ? $GLOBALS['wiki'] : '';
    $GLOBALS['prefered_language'] = detectPreferedLanguage($wiki, $GLOBALS['available_languages']);

    return;
}

/**
 * Update the table of translation, based on the information from current page
 * Must be run once initI18n() was..
 *
 *  @string $page    name of current WikiPage to check for informations on language
 */
function loadpreferredI18n($wiki, $page = '')
{
    $GLOBALS['prefered_language'] = detectPreferedLanguage($wiki, $GLOBALS['available_languages'], 'auto', $page);

    if ($GLOBALS['prefered_language'] != 'fr' && file_exists('lang/yeswiki_' . $GLOBALS['prefered_language'] . '.php')) {
        // this will overwrite the values of $GLOBALS['translations'] in the selected language
        $returnedArray = include_once 'lang/yeswiki_' . $GLOBALS['prefered_language'] . '.php';
        load_translations($returnedArray);
    }
    if ($GLOBALS['prefered_language'] != 'fr' && file_exists('lang/yeswikijs_' . $GLOBALS['prefered_language'] . '.php')) {
        $returnedArray = include_once 'lang/yeswikijs_' . $GLOBALS['prefered_language'] . '.php';
        load_translations($returnedArray, true);
    }

    return;
}

function load_translations($returnedArray, bool $jsmode = false)
{
    $translationName = $jsmode ? 'translations_js' : 'translations';
    if (is_array($returnedArray)) {
        $GLOBALS[$translationName] = array_merge(($GLOBALS[$translationName] ?? []), $returnedArray);
    }
}

// default init
initI18n();
