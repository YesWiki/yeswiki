<?php
/*
Some usefull functions to deal with URLs

Copyright 2005  Didier Loiseau
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

/**
 * Return the root url of the current page. Specify the http or https protocol according to which is activated,
 * and a specific port if used.
 * Per example, http://myhost.net:81/mywiki/?PagePrincipale returns http://myhost.net:81/
 * @return string the root url
 */
function getRootUrl()
{
    $protocol = 'http://';
    if (!empty($_SERVER['HTTPS'])) {
        $protocol = 'https://';
    }
    return $protocol . $_SERVER["HTTP_HOST"];
}

/**
 * Return the absolute url of the current page. Specify the http or https protocol according to which is activated,
 * and a specific port if used.
 * @return string the absolute url
 */
function getAbsoluteUrl()
{
    return getRootUrl() . $_SERVER['REQUEST_URI'];
}

/**
 * Computes the base url of the wiki, used as default configuration value.
 * This function works with https sites two.
 * @param boolean $rewrite_mode Indicates whether the rewrite mode is activated
 * as it affects the resulting url. Defaults to false.
 * @return string The base url of the wiki
 */
function computeBaseURL($rewrite_mode = false)
{
    $scriptlocation = str_replace(array('/index.php', '/wakka.php'), '', $_SERVER["SCRIPT_NAME"]);

    return getRootUrl()
        . $scriptlocation
        . ($rewrite_mode ? '/' : '/?');
}

/**
 * Automatically detects the rewrite mode
 * @return boolean True if the rewrite mode has been detected as activated,
 * false otherwise.
 */
function detectRewriteMode()
{
    $pieces = parse_url($_SERVER['REQUEST_URI']);
    $scriptlocation = str_replace(array('/index.php', '/wakka.php'), '', $_SERVER["SCRIPT_NAME"]);
    $path = preg_replace('/\/$/', '', $pieces['path']);
    if ($path == $scriptlocation or $pieces['path'] == '/' or $pieces['path'] == '/index.php' or $pieces['path'] == '/wakka.php') {
        return false;
    }
    return substr($pieces['path'], - strlen(WAKKA_ENGINE)) != WAKKA_ENGINE;
}

/**
 * Replace links with the /iframe handler when not opened in a new window
 * @param string $body the body page as source
 * @return string the body page with the link replacements
 */
function replaceLinksWithIframe(string $body): string
{
    // pattern qui rajoute le /iframe pour les liens au bon endroit, merci raphael@tela-botanica.org
    $pattern = '~(<a.*?href.*?)' . preg_quote($GLOBALS['wiki']->config['base_url']) . '([\w\-_]+)([&#?].*?)?(["\'])([^>]*?>)~i';
    $pagebody = preg_replace_callback(
        $pattern,
        function ($matches) {
            // on vérifie si le lien ne s'ouvre pas dans une nouvelle fenêtre, c'est à dire s'il n'y a pas d'attribut
            // target="_blank" ou class="new window" avant ou après le href
            // et si le lien ne s'ouvre dans une autre fenêtre, on insère /iframe à l'url
            $NEW_WINDOW_PATTERN = "~^(.*target=[\"']\s*_blank\s*[\"'].*)|(.*class=[\"'].*?new-window.*?[\"'].*)$~i";
            if (preg_match($NEW_WINDOW_PATTERN, $matches[1]) || preg_match($NEW_WINDOW_PATTERN,
                    $matches[5])) {
                return $matches[1] . $GLOBALS['wiki']->config['base_url'] . $matches[2] . $matches[3] . $matches[4] .
                    $matches[5];
            } else {
                return $matches[1] . $GLOBALS['wiki']->config['base_url'] . $matches[2] . '/iframe' . $matches[3] .
                    $matches[4] . $matches[5];
            }
        },
        $body
    );

    // pattern qui rajoute le /editiframe pour les liens au bon endroit
    $pattern = '~(<a.*?href.*?)' . preg_quote($GLOBALS['wiki']->config['base_url']) . '([\w\-_]+)\/edit([&#?].*?)?(["\'])([^>]*?>)~i';
    $pagebody = preg_replace_callback(
        $pattern,
        function ($matches) {
            // on vérifie si le lien ne s'ouvre pas dans une nouvelle fenêtre, c'est à dire s'il n'y a pas d'attribut
            // target="_blank" ou class="new window" avant ou après le href
            // et si le lien ne s'ouvre dans une autre fenêtre, on insère /editiframe à l'url
            $NEW_WINDOW_PATTERN = "~^(.*target=[\"']\s*_blank\s*[\"'].*)|(.*class=[\"'].*?new-window.*?[\"'].*)$~i";
            if (preg_match($NEW_WINDOW_PATTERN, $matches[1]) || preg_match($NEW_WINDOW_PATTERN,
                    $matches[5])) {
                return $matches[1] . $GLOBALS['wiki']->config['base_url'] . $matches[2] . '/edit' . $matches[3]
                    . $matches[4] . $matches[5];
            } else {
                return $matches[1] . $GLOBALS['wiki']->config['base_url'] . $matches[2] . '/editiframe' . $matches[3]
                    . $matches[4] . $matches[5];
            }
        },
        $pagebody
    );
    return $pagebody;
}
