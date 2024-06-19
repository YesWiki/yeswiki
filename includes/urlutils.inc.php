<?php
/*
Some usefull functions to deal with URLs
*/

/**
 * Return the root url of the current page. Specify the http or https protocol according to which is activated,
 * and a specific port if used.
 * Per example, http://myhost.net:81/mywiki/?PagePrincipale returns http://myhost.net:81/.
 *
 * @return string the root url
 */
function getRootUrl()
{
    $protocol = 'http://';
    if (!empty($_SERVER['HTTPS'])) {
        $protocol = 'https://';
    }

    return $protocol . $_SERVER['HTTP_HOST'];
}

/**
 * Return the absolute url of the current page. Specify the http or https protocol according to which is activated,
 * and a specific port if used.
 *
 * @return string the absolute url
 */
function getAbsoluteUrl()
{
    return getRootUrl() . $_SERVER['REQUEST_URI'];
}

/**
 * Computes the base url of the wiki, used as default configuration value.
 * This function works with https sites two.
 *
 * @param bool $rewrite_mode Indicates whether the rewrite mode is activated
 *                           as it affects the resulting url. Defaults to false.
 *
 * @return string The base url of the wiki
 */
function computeBaseURL($rewrite_mode = false)
{
    $scriptlocation = str_replace(['/index.php', '/wakka.php'], '', $_SERVER['SCRIPT_NAME']);

    return getRootUrl()
        . $scriptlocation
        . ($rewrite_mode ? '/' : '/?');
}

/**
 * Automatically detects the rewrite mode.
 *
 * @return bool true if the rewrite mode has been detected as activated,
 *              false otherwise
 */
function detectRewriteMode()
{
    $pieces = parse_url($_SERVER['REQUEST_URI']);
    $scriptlocation = str_replace(['/index.php', '/wakka.php'], '', $_SERVER['SCRIPT_NAME']);
    $path = preg_replace('/\/$/', '', $pieces['path']);
    if ($path == $scriptlocation or $pieces['path'] == '/' or $pieces['path'] == '/index.php' or $pieces['path'] == '/wakka.php') {
        return false;
    }

    return substr($pieces['path'], -strlen(WAKKA_ENGINE)) != WAKKA_ENGINE;
}

/**
 * Replace links with the /iframe handler when not opened in a new window.
 *
 * @param string $body the body page as source
 *
 * @return string the body page with the link replacements
 */
function replaceLinksWithIframe(string $body): string
{
    // pattern qui rajoute le /iframe pour les liens au bon endroit, merci raphael@tela-botanica.org
    $pattern = '~(<a.*?href.*?)' . preg_quote($GLOBALS['wiki']->config['base_url'], '~') . '([\w\-_]+)(\/(?:edit|show))?([&#?].*?)?(["\'])([^>]*?>)~i';
    $NEW_WINDOW_PATTERN = "~^(.*target=[\"']\s*_?blank\s*[\"'].*)|(.*class=[\"'].*?new-window.*?[\"'].*)$~i";
    if (preg_match_all($pattern, $body, $matches)) {
        foreach ($matches[0] as $key => $match) {
            if (!preg_match($NEW_WINDOW_PATTERN, $matches[1][$key]) && !preg_match(
                $NEW_WINDOW_PATTERN,
                $matches[6][$key]
            )) {
                $replacement =
                    $matches[1][$key] .
                    $GLOBALS['wiki']->config['base_url'] .
                    $matches[2][$key] .
                    ($matches[3][$key] == '/edit' ? '/editiframe' : '/iframe') .
                    $matches[4][$key] .
                    $matches[5][$key] .
                    $matches[6][$key];
                $body = str_replace($match, $replacement, $body);
            }
        }
    }

    return $body;
}

function testUrlInIframe($url = '')
{
    if (empty($url)) {
        // test si on est dans une iframe
        $url = getAbsoluteUrl();
    }
    $iframe = preg_match('/(?:\/|%2F)(edit)?iframe/Ui', $url);

    return $iframe ? 'iframe' : '';
}

function testRefererUrlInIframe()
{
    $url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $iframe = preg_match('/\/(edit)?iframe/Ui', $url);

    return $iframe ? 'iframe' : '';
}
