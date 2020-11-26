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
 * Return the absolute url of the current page. Specify the http or https protocol according to which is activated,
 * and a specific port if used.
 * @return string the absolute url
 */
function getAbsoluteUrl()
{
    return $GLOBALS['wiki']->getBaseUrl() . $_SERVER['REQUEST_URI'];
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
    $protocol = 'http://';
    if (!empty($_SERVER['HTTPS'])) {
        $protocol = 'https://';
    }
    $port = '';
    if ($_SERVER["SERVER_PORT"] != 80
        and $_SERVER["SERVER_PORT"] != 443) {
        $port = ':' . $_SERVER["SERVER_PORT"];
    }
    $scriptlocation = str_replace(array('/index.php', '/wakka.php'), '', $_SERVER["SCRIPT_NAME"]);

    return $protocol
        . $_SERVER["HTTP_HOST"]
        . $port
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
