<?php

/**
 * Return the root url of the current page.
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
 * Return the absolute url of the current page.
 *
 * @return string the absolute url
 */
function getAbsoluteUrl()
{
    return getRootUrl() . $_SERVER['REQUEST_URI'];
}

/**
 * Computes the base url of the wiki, used as default configuration value.
 *
 * @param bool $rewrite_mode Indicates whether the rewrite mode is activated
 *                           as it affects the resulting url. Defaults to false.
 *
 * @return string The base url of the wiki
 */
function computeBaseURL($rewrite_mode = false)
{
    return getRootUrl()
        . wikiMountPath()
        . ($rewrite_mode ? '/' : '/?');
}

/**
 * The path the wiki is reached under, without its trailing slash: '' at a docroot root, '/mywiki' in a subdirectory, '/ecto' behind an alias.
 *
 * @return string mount path with no trailing slash, '' at the root
 */
function wikiMountPath(): string
{
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');

    $scriptLocation = str_ends_with($script, '/index.php')
        ? substr($script, 0, -strlen('/index.php'))
        : '';
    $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

    if ($scriptLocation !== '' && str_starts_with(rtrim($path, '/') . '/', $scriptLocation . '/')) {
        return $scriptLocation;
    }

    if (str_ends_with($path, '/index.php')) {
        $path = substr($path, 0, -strlen('/index.php')) . '/';
    }
    if (!str_ends_with($path, '/')) {
        return $scriptLocation;
    }

    return rtrim($path, '/');
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
    $scriptlocation = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']);
    $path = preg_replace('/\/$/', '', $pieces['path']);
    if ($path == $scriptlocation or $pieces['path'] == '/' or $pieces['path'] == '/index.php') {
        return false;
    }

    return true;
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
    $pattern = '~(<a[[:blank:]]*[^>]*[[:blank:]]*href[[:blank:]]*=[[:blank:]]*)(["\'])((?:' . preg_quote($GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\RuntimeConfig::class)['base_url'], '~') . '|\?))([\w\-_]+)(\/(?:edit|show))?([&#?].*?)?(\2)([^>]*>)~i';

    $NEW_WINDOW_PATTERN = "~^(.*target=[\"']\s*_?blank\s*[\"'].*)|(.*class=[\"'].*?new-window.*?[\"'].*)$~i";

    if (preg_match_all($pattern, $body, $matches)) {
        foreach ($matches[0] as $key => $match) {
            if (!preg_match($NEW_WINDOW_PATTERN, $matches[1][$key]) && !preg_match(
                $NEW_WINDOW_PATTERN,
                $matches[8][$key]
            )) {
                $replacement =
                    $matches[1][$key] .
                    $matches[2][$key] .
                    $matches[3][$key] .
                    $matches[4][$key] .
                    ($matches[5][$key] == '/edit' ? '/editiframe' : '/iframe') .
                    $matches[6][$key] .
                    $matches[7][$key] .
                    $matches[8][$key];
                $body = str_replace($match, $replacement, $body);
            }
        }
    }

    return $body;
}

function testUrlInIframe($url = '')
{
    if (empty($url)) {
        $url = getAbsoluteUrl();
    }
    $iframe = preg_match('/(?:\/|%2F)(edit)?iframe/Ui', $url);

    return $iframe ? 'iframe' : '';
}

/**
 * Résout une URL en une URL absolue en se basant sur une URL de référence.
 *
 * @param string $pPageAbsoluteURL L'URL absolue du document contenant le lien (ex: "https://exemple.com/chemin/page.html")
 * @param string $pLink            Le href trouvé dans le fichier (ex: "../img/photo.jpg" ou "/css/style.css" ou "https://autre.com/")
 *
 * @return string L'URL absolue résolue
 */
function getAbsoluteURLForLinkInAPage($pPageAbsoluteURL, $pLink)
{
    if (parse_url($pLink, PHP_URL_SCHEME) !== null) {
        return $pLink;
    }

    $vPageParts = parse_url($pPageAbsoluteURL);
    if ($vPageParts === false || !isset($vPageParts['scheme'], $vPageParts['host'])) {
        throw new InvalidArgumentException("URL de base invalide : $pPageAbsoluteURL");
    }

    $vBasePath = $vPageParts['vPath'] ?? '/';

    if (substr($vBasePath, -1) !== '/') {
        $vBasePath = substr($vBasePath, 0, strrpos($vBasePath, '/') + 1);
    }

    if (str_starts_with($pLink, '/')) {
        $vPath = $pLink;
    } else {
        $vPath = $vBasePath . $pLink;
    }

    $vSegments = explode('/', $vPath);
    $vResolved = [];
    foreach ($vSegments as $vSegment) {
        if ($vSegment === '' || $vSegment === '.') {
            if ($vSegment === '' && empty($vResolved)) {
                $vResolved[] = '';
            }
            continue;
        }
        if ($vSegment === '..') {
            if (count($vResolved) > 1 || (count($vResolved) === 1 && $vResolved[0] !== '')) {
                array_pop($vResolved);
            }
            continue;
        }
        $vResolved[] = $vSegment;
    }

    $vNormalizedPath = implode('/', $vResolved);

    if (str_ends_with($vPath, '/') && !str_ends_with($vNormalizedPath, '/')) {
        $vNormalizedPath .= '/';
    }

    $vAbsolutePath = $vPageParts['scheme'] . '://' . $vPageParts['host'];
    if (isset($vPageParts['port'])) {
        $vAbsolutePath .= ':' . $vPageParts['port'];
    }

    $vAbsolutePath .= $vNormalizedPath;

    return $vAbsolutePath;
}
