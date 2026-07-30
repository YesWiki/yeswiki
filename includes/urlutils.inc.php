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

    $pattern = '~(<a[[:blank:]]*[^>]*[[:blank:]]*href[[:blank:]]*=[[:blank:]]*)(["\'])((?:' . preg_quote($GLOBALS['wiki']->config['base_url'], '~') . '|\?))([\w\-_]+)(\/(?:edit|show|translate))?([&#?].*?)?(\2)([^>]*>)~i';

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
                    ($matches[5][$key] == '/edit' ? '/editiframe' : ('/translate' ? '/translateiframe' : '/iframe')) .
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
        // test si on est dans une iframe
        $url = getAbsoluteUrl();
    }
    $iframe = preg_match('/(?:\/|%2F)(edit|translate)?iframe/Ui', $url);

    return $iframe ? 'iframe' : '';
}

function testRefererUrlInIframe()
{
    $url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $iframe = preg_match('/\/(edit|translate)?iframe/Ui', $url);

    return $iframe ? 'iframe' : '';
}

/**
 * Test si une URL est locale.
 *
 * @param string $pURL l'url à tester
 *
 * @return bool true if URL is local, false otherwise
 */
function isLocalUrl($pURL)
{
    $vParsed = parse_url($pURL);

    if ($vParsed === false) {
        return true;
    } elseif ($vParsed['scheme'] . '://' . $vParsed['host'] . ':' . $vParsed['port'] == getRootUrl()) {
        return true;
    } else {
        return false;
    }
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
    // Si $pLink est déjà absolu, on le retourne tel quel.
    if (parse_url($pLink, PHP_URL_SCHEME) !== null) {
        return $pLink;
    }

    // Parse l'url absolue de la page
    $vPageParts = parse_url($pPageAbsoluteURL);
    if ($vPageParts === false || !isset($vPageParts['scheme'], $vPageParts['host'])) {
        throw new InvalidArgumentException("URL de base invalide : $pPageAbsoluteURL");
    }

    // Construction du chemin de base
    $vBasePath = $vPageParts['vPath'] ?? '/';
    // Si la base finit par un fichier, on enlève tout après le dernier '/'
    if (substr($vBasePath, -1) !== '/') {
        $vBasePath = substr($vBasePath, 0, strrpos($vBasePath, '/') + 1);
    }

    // Si le relatif commence par '/', c'est à partir du root
    if (str_starts_with($pLink, '/')) {
        $vPath = $pLink;
    } else {
        $vPath = $vBasePath . $pLink;
    }

    // Normalisation des vSegments (./, ../)
    $vSegments = explode('/', $vPath);
    $vResolved = [];
    foreach ($vSegments as $vSegment) {
        if ($vSegment === '' || $vSegment === '.') {
            // ignore
            if ($vSegment === '' && empty($vResolved)) {
                // conserver les premières slashs pour les cas comme "/foo"
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
    // Gérer le cas où on veut un slash final si le dernier vSegment était vide
    if (str_ends_with($vPath, '/') && !str_ends_with($vNormalizedPath, '/')) {
        $vNormalizedPath .= '/';
    }

    // Reconstruire l'URL
    $vAbsolutePath = $vPageParts['scheme'] . '://' . $vPageParts['host'];
    if (isset($vPageParts['port'])) {
        $vAbsolutePath .= ':' . $vPageParts['port'];
    }

    $vAbsolutePath .= $vNormalizedPath;

    return $vAbsolutePath;
}
