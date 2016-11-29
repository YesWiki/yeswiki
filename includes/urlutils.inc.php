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
 * Computes the absolute path contained in an URL. For example in
 * http://hostname/path/to/site/file.php?param=value
 * the absolute path is '/path/to/site/'.
 * @param string $url The url from which extract the absolute path.
 * You might give partial URLs, for example just "/path/to/site/file.php".
 * If no argument is given, $_SERVER['REQUEST_URI'] will be used.
 * @return string The absolute path extracted from $url
 */
function getURLAbsolutePath($url = null)
{
    if (!$url) $url = $_SERVER['REQUEST_URI'];

    $pieces = @parse_url($url);
    if ($pieces === false) return false;

    if (empty($pieces['path'])) return '/';

    $path = $pieces['path'];
    $path_len = strlen($path);

    if ($path[$path_len - 1] == '/') return $path;

    $expl = explode('/', $path); // here $expl[0] should be the empty string
    $expl[count($expl) - 1] = ''; // this makes the path /look/like/this/

    return implode('/', $expl);
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

    $urlPieces = parse_url($_SERVER['REQUEST_URI']);

    $port = '';
    if ($_SERVER["SERVER_PORT"] != 80
        and $_SERVER["SERVER_PORT"] != 443) {
        $port = ':' . $_SERVER["SERVER_PORT"];
    }

    $urlParam = '';
    if (!$rewrite_mode) {
        $urlParam = '?wiki=';
    }

    return $protocol
        . $_SERVER["HTTP_HOST"]
        . $port
        . $urlPieces['path']
        . $urlParam;
}

/**
 * Automatically detects the rewrite mode
 * @return boolean True if the rewrite mode has been detected as activated,
 * false otherwise.
 */
function detectRewriteMode()
{
    $pieces = parse_url($_SERVER['REQUEST_URI']);
    return substr($pieces['path'], - strlen(WAKKA_ENGINE)) != WAKKA_ENGINE;
    // return !preg_match("/".preg_quote(WAKKA_ENGINE)."$/", $_SERVER["REQUEST_URI"]);
}

?>
