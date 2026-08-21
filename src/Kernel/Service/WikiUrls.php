<?php

namespace YesWiki\Kernel\Service;

/**
 * Where this wiki is, and where this request is, derived from the web server's own variables.
 *
 * Static, and deliberately: `YesWikiInit` computes `base_url` and the rewrite mode **before the
 * container exists**, so anything that answers those questions cannot be a service. That is the
 * same reason `bootstrap_paths.php` is allowed to reach the filesystem directly. What it must not
 * be is a set of global functions in `Kernel/urlutils.inc.php` reaching for the container to read
 * one configuration value (ticket 50).
 */
class WikiUrls
{
    /** Scheme and host, with no path: `https://example.org`. */
    public static function rootUrl(): string
    {
        $protocol = 'http://';
        if (!empty($_SERVER['HTTPS'])) {
            $protocol = 'https://';
        }

        return $protocol . $_SERVER['HTTP_HOST'];
    }

    /** The address of the request being served, in full. */
    public static function absoluteUrl(): string
    {
        return self::rootUrl() . $_SERVER['REQUEST_URI'];
    }

    /**
     * The wiki's own base address, which is what `base_url` is derived from at boot.
     *
     * @param bool $rewriteMode whether the wiki answers pretty URLs, which decides the `?`
     */
    public static function baseUrl(bool $rewriteMode = false): string
    {
        return self::rootUrl()
            . self::mountPath()
            . ($rewriteMode ? '/' : '/?');
    }

    /** The path the wiki is reached under, without its trailing slash: '' at a docroot root, '/mywiki' in a subdirectory. */
    public static function mountPath(): string
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

    /** Whether the web server is rewriting pretty URLs, read from the request it just routed. */
    public static function rewriteMode(): bool
    {
        $pieces = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'));
        if ($pieces === false || !isset($pieces['path'])) {
            return false;
        }
        $scriptlocation = str_replace('/index.php', '', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $path = preg_replace('/\/$/', '', $pieces['path']);
        if ($path == $scriptlocation or $pieces['path'] == '/' or $pieces['path'] == '/index.php') {
            return false;
        }

        return true;
    }

    /**
     * `'iframe'` when $url is one of the iframe handlers, `''` otherwise.
     *
     * Reads as a question and answers with a string because that is what its sixteen callers
     * pass straight into a template.
     */
    public static function iframeSuffixFor(string $url = ''): string
    {
        if (empty($url)) {
            $url = self::absoluteUrl();
        }
        $iframe = preg_match('/(?:\/|%2F)(edit)?iframe/Ui', $url);

        return $iframe ? 'iframe' : '';
    }

    /**
     * $link resolved against the address of the page that contains it.
     *
     * @throws \InvalidArgumentException when $pageAbsoluteUrl is not an absolute address
     */
    public static function absoluteUrlForLinkInPage(string $pageAbsoluteUrl, string $link): string
    {
        if (parse_url($link, PHP_URL_SCHEME) !== null) {
            return $link;
        }

        $vPageParts = parse_url($pageAbsoluteUrl);
        if ($vPageParts === false || !isset($vPageParts['scheme'], $vPageParts['host'])) {
            throw new \InvalidArgumentException("URL de base invalide : $pageAbsoluteUrl");
        }

        // was `$vPageParts['vPath']`, a key parse_url() never returns, so every relative
        // link resolved against the domain root instead of the page's own directory
        $vBasePath = $vPageParts['path'] ?? '/';

        if (substr($vBasePath, -1) !== '/') {
            $vBasePath = substr($vBasePath, 0, strrpos($vBasePath, '/') + 1);
        }

        if (str_starts_with($link, '/')) {
            $vPath = $link;
        } else {
            $vPath = $vBasePath . $link;
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
}
