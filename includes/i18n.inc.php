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
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// Compatibility with php versions without json functions
if (!function_exists('json_decode')) {
    function json_decode($content, $assoc = false)
    {
        require_once 'includes/JSON.php';
        if ($assoc) {
            $json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
        } else {
            $json = new Services_JSON;
        }
        return $json->decode($content);
    }
}
if (!function_exists('json_encode')) {
    function json_encode($content)
    {
        require_once 'includes/JSON.php';
        $json = new Services_JSON;
        return $json->encode($content);
    }
}

/**
 * Translate the text in the page's language
 *
 * @param string array key for the text or false if doesn't exists
 * @return string the translated text or the key if not found
 */
function _t($textkey)
{
    if (isset($GLOBALS['translations'][$textkey])) {
        return $GLOBALS['translations'][$textkey];
    } else {
        return $textkey;
    }
}

/**
 * Convert the text in the page's charset, or in the datadase's charset
 *
 * @param mixed the text
 * @param string the page's encoding
 * @param bool is it for the database ?
 * @return string the encoded text
 */
function _convert($text, $fromencoding, $database = false)
{
    if (is_array($text)) {
        $arraytext = array();
        foreach ($text as $key => $value) {
            $arraytext[$key] = _convert($value, $fromencoding, $database);
        }
        return $arraytext;
    } else {
        /*
        $cp1252_map = array(
            "\xc2\x80" => "\xe2\x82\xac", // EURO SIGN 
            "\xc2\x82" => "\xe2\x80\x9a", // SINGLE LOW-9 QUOTATION MARK 
            "\xc2\x83" => "\xc6\x92",    // LATIN SMALL LETTER F WITH HOOK 
            "\xc2\x84" => "\xe2\x80\x9e", // DOUBLE LOW-9 QUOTATION MARK 
            "\xc2\x85" => "\xe2\x80\xa6", // HORIZONTAL ELLIPSIS 
            "\xc2\x86" => "\xe2\x80\xa0", // DAGGER 
            "\xc2\x87" => "\xe2\x80\xa1", // DOUBLE DAGGER 
            "\xc2\x88" => "\xcb\x86",    // MODIFIER LETTER CIRCUMFLEX ACCENT 
            "\xc2\x89" => "\xe2\x80\xb0", // PER MILLE SIGN 
            "\xc2\x8a" => "\xc5\xa0",    // LATIN CAPITAL LETTER S WITH CARON 
            "\xc2\x8b" => "\xe2\x80\xb9", // SINGLE LEFT-POINTING ANGLE QUOTATION 
            "\xc2\x8c" => "\xc5\x92",    // LATIN CAPITAL LIGATURE OE 
            "\xc2\x8e" => "\xc5\xbd",    // LATIN CAPITAL LETTER Z WITH CARON 
            "\xc2\x91" => "\xe2\x80\x98", // LEFT SINGLE QUOTATION MARK 
            "\xc2\x92" => "\xe2\x80\x99", // RIGHT SINGLE QUOTATION MARK 
            "\xc2\x93" => "\xe2\x80\x9c", // LEFT DOUBLE QUOTATION MARK 
            "\xc2\x94" => "\xe2\x80\x9d", // RIGHT DOUBLE QUOTATION MARK 
            "\xc2\x95" => "\xe2\x80\xa2", // BULLET 
            "\xc2\x96" => "\xe2\x80\x93", // EN DASH 
            "\xc2\x97" => "\xe2\x80\x94", // EM DASH 
            "\xc2\x98" => "\xcb\x9c",    // SMALL TILDE 
            "\xc2\x99" => "\xe2\x84\xa2", // TRADE MARK SIGN 
            "\xc2\x9a" => "\xc5\xa1",    // LATIN SMALL LETTER S WITH CARON 
            "\xc2\x9b" => "\xe2\x80\xba", // SINGLE RIGHT-POINTING ANGLE QUOTATION
            "\xc2\x9c" => "\xc5\x93",    // LATIN SMALL LIGATURE OE 
            "\xc2\x9e" => "\xc5\xbe",    // LATIN SMALL LETTER Z WITH CARON 
            "\xc2\x9f" => "\xc5\xb8"      // LATIN CAPITAL LETTER Y WITH DIAERESIS
        );
        */

        if ($database) {
            if ($fromencoding != "ISO-8859-1" && $fromencoding != "ISO-8859-15") {
                return mb_convert_encoding(
                    $text,
                    YW_CHARSET,
                    mb_detect_encoding($text, "UTF-8, ISO-8859-1, ISO-8859-15", true)
                );
            } else {
                return $text;
            }
        } else {
            if ($fromencoding != YW_CHARSET) {
                return mb_convert_encoding(
                    $text,
                    "UTF-8",
                    mb_detect_encoding($text, "UTF-8, ".YW_CHARSET.", ISO-8859-1, ISO-8859-15", true)
                );
            } else {
                return $text;
            }
        }
    }
}

/**
 * Automatically detects the languages available in the lang dir
 * @return array available languages
 */
function detectAvailableLanguages()
{
    $available_languages = array();
    if ($d = @opendir('lang')) {
        while (($f = readdir($d)) !== false) {
            if (preg_match(',^yeswiki_([a-z_]+)\.php[3]?$,', $f, $regs)) {
                $available_languages[] = $regs[1];
            }
        }
        closedir($d);
        sort($available_languages);
    }
    return $available_languages;
}

/**
 *  Determine which language out of an available set the user prefers most
 *  copied from http://php.net/manual/en/function.http-negotiate-language.php#example-4353
 *
 *  @array $available_languages        array with language-tag-strings (must be lowercase) that are available
 *  @string $http_accept_language a HTTP_ACCEPT_LANGUAGE string (read from $_SERVER['HTTP_ACCEPT_LANGUAGE'] if left out)
 *  @string $page    name of WikiPage to check for informations on language
 */
function detectPreferedLanguage($available_languages, $http_accept_language = "auto", $page = '')
{
    // first choice : if lang changed in url
    if (isset($_GET['lang']) && in_array($_GET['lang'], $available_languages)) {
        return $_GET['lang'];
    } elseif (isset($_POST["config"])) {
        // just for installation
        if (count($_POST["config"])==1) {
            $conf = unserialize($_POST["config"]);
            if (isset($conf['default_language']) && in_array($conf['default_language'], $available_languages)) {
                return $conf['default_language'];
            }
        } elseif (isset($_POST["config"]['default_language'])
            && in_array($_POST["config"]['default_language'], $available_languages)) {
            return $_POST["config"]['default_language'];
        }
    } elseif ($page!='') {
        // page's metadata lang
        $GLOBALS['wiki']->metadatas = $GLOBALS['wiki']->GetTripleValue($page, 'http://outils-reseaux.org/_vocabulary/metadata', '', '', '');
        if (!empty($GLOBALS['wiki']->metadatas)) {
            $GLOBALS['wiki']->metadatas =  array_map('utf8_decode', json_decode($GLOBALS['wiki']->metadatas, true));
        }
        if (isset($GLOBALS['wiki']->metadatas['lang']) && in_array($GLOBALS['wiki']->metadatas['lang'], $available_languages)) {
            return $GLOBALS['wiki']->metadatas['lang'];
        }
        // default language from config file
        if (isset($GLOBALS['wiki']->config['default_language']) && in_array($GLOBALS['wiki']->config['default_language'], $available_languages)) {
            return $GLOBALS['wiki']->config['default_language'];
        }
    } elseif ($http_accept_language == "auto") {
        // if $http_accept_language was left out, read it from the HTTP-Header of the browser
        $http_accept_language = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
    }

    // standard  for HTTP_ACCEPT_LANGUAGE is defined under
    // http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.4
    // pattern to find is therefore something like this:
    //    1#( language-range [ ";" "q" "=" qvalue ] )
    // where:
    //    language-range  = ( ( 1*8ALPHA *( "-" 1*8ALPHA ) ) | "*" )
    //    qvalue         = ( "0" [ "." 0*3DIGIT ] )
    //            | ( "1" [ "." 0*3("0") ] )
    preg_match_all(
        "/([[:alpha:]]{1,8})(-([[:alpha:]|-]{1,8}))?"
        ."(\s*;\s*q\s*=\s*(1\.0{0,3}|0\.\d{0,3}))?\s*(,|$)/i",
        $http_accept_language,
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
            $language = $langprefix . "-" . $langrange;
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
        } else if (in_array($langprefix, $available_languages) && (($qvalue*0.9) > $bestqval)) {
            // if no direct hit, try the prefix only but decrease q-value by 10% (as http_negotiate_language does)
            $bestlang = $langprefix;
            $bestqval = $qvalue*0.9;
        }
    }
    return $bestlang;
}

/**
 * Initialize the table of translation, based on the information gathered in the page
 *
 */
function initI18n()
{
    // initialize charset
    define(
        'YW_CHARSET',
        isset($GLOBALS['wiki']->config['charset']) ? $GLOBALS['wiki']->config['charset'] : 'UTF-8'
    );

    // get the language list
    require_once 'lang/languages_list.php';

    // we initialise with french language, because it is the most beautiful ;) or maybe just the most updated because we are a french dev team
    require_once 'lang/yeswiki_fr.php';

    $GLOBALS['available_languages'] = detectAvailableLanguages();
    $GLOBALS['prefered_language'] = detectPreferedLanguage($GLOBALS['available_languages']);

    if ($GLOBALS['prefered_language'] != 'fr' && file_exists('lang/yeswiki_'.$GLOBALS['prefered_language'].'.php')) {
        // this will overwrite the values of $GLOBALS['translations'] in the selected language
        require_once 'lang/yeswiki_'.$GLOBALS['prefered_language'].'.php';
    }
    return;
}

/**
 * Update the table of translation, based on the information gathered in the page, eventually
 * Must be run once initI18n() was..
 *
 *  @string $page    name of current WikiPage to check for informations on language
 *
 */
function loadpreferredI18n($page = '')
{
    $GLOBALS['prefered_language'] = detectPreferedLanguage($GLOBALS['available_languages'], 'auto', $page);

    if ($GLOBALS['prefered_language'] != 'fr' && file_exists('lang/yeswiki_'.$GLOBALS['prefered_language'].'.php')) {
        // this will overwrite the values of $GLOBALS['translations'] in the selected language
        require_once 'lang/yeswiki_'.$GLOBALS['prefered_language'].'.php';
    }
    return;
}

// default init
initI18n();
