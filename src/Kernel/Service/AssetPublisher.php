<?php

namespace YesWiki\Kernel\Service;

/**
 * Publishes static assets (css/js/fonts/images...) from the YesWiki source tree into the
 * instance's own cache/assets/{version}/ folder, so that an instance whose docroot only
 * contains index.php + its data folders (yeswiki.config.php, files/, custom/, cache/, private/)
 * needs no symlinks or webserver aliases to the shared YesWiki sources.
 *
 * Two cooperating mechanisms:
 *  - AssetRegistry emits versioned URLs (cache/assets/{version}/{original path}) for every
 *    registered css/js file when running as a farm instance, eagerly copying the file on
 *    first emission (see publishedUrl()).
 *  - interceptAssetRequest() runs from index.php BEFORE the wiki boots (no DB, no session):
 *    the standard rewrite fallback (Apache rewrite mode / nginx try_files) sends any missing
 *    static file through index.php, and this materializes it from the source tree and streams
 *    it. Because published paths preserve the original directory layout, relative references
 *    inside CSS (@import, url(...) fonts/images) and companion files loaded relative to a
 *    script's URL resolve to sibling published paths and get materialized the same way on
 *    first request. Hardcoded template references to source paths (extensions/x/presentation/...)
 *    are covered by the direct-path branch.
 */
class AssetPublisher
{
    public const PUBLISHED_PREFIX = 'cache/assets/';

    protected const ALLOWED_PREFIXES = [
        'src/assets/',
        'styles/',
        'javascripts/',
        'themes/',
        'extensions/',
        'custom/',
        'docs/',
    ];

    protected const MIME_TYPES = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
        'mjs' => 'text/javascript; charset=UTF-8',
        'map' => 'application/json',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/vnd.microsoft.icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
    ];

    /**
     * Root of the shared YesWiki sources. Distinct from the instance dir (getcwd()) when
     * running as a farm instance; identical in a classic standalone install.
     */
    public static function sourceDir(): string
    {
        return defined('YESWIKI_SOURCE_DIR') ? constant('YESWIKI_SOURCE_DIR') : \dirname(__DIR__, 3); // src/<Module>/Service -> source root
    }

    /**
     * Called from index.php before anything else boots. Serves the request and exits if it
     * targets a publishable asset; returns silently otherwise so the wiki boots normally.
     * Kept dependency-free on purpose: no config, no DB, no session.
     */
    public static function interceptAssetRequest(): void
    {
        if (\PHP_SAPI === 'cli' || !in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
            return;
        }

        $uriPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
        // strip the instance base path (subdirectory installs) - only inferable when
        // SCRIPT_NAME really points at the entry script (some SAPIs, e.g. the built-in
        // server with a router, put the requested path in SCRIPT_NAME instead)
        if (preg_match('~^(.+)/index\.php$~', $_SERVER['SCRIPT_NAME'] ?? '', $m)
            && str_starts_with($uriPath, $m[1] . '/')) {
            $uriPath = substr($uriPath, strlen($m[1]));
        }
        $uriPath = ltrim($uriPath, '/');
        // nginx "try_files $uri /index.php$uri" fallback form
        if (str_starts_with($uriPath, 'index.php/')) {
            $uriPath = substr($uriPath, strlen('index.php/'));
        }

        // farm fallback: a docroot-wide rewrite (e.g. Caddy's php_server, Apache fallback in
        // the farm root) sends a missing /instance/asset to this shared index.php instead of
        // the instance's own one. Walk into the deepest instance dir the path traverses so
        // the asset is materialized into and served from that instance's cache/assets.
        $instanceDir = null;
        while (!str_starts_with($uriPath, self::PUBLISHED_PREFIX) && !self::isServablePath($uriPath)) {
            $slashPos = strpos($uriPath, '/');
            if ($slashPos === false) {
                break;
            }
            $segment = substr($uriPath, 0, $slashPos);
            if ($segment === '' || $segment[0] === '.' || str_contains($segment, '\\') || str_contains($segment, "\0")) {
                break;
            }
            $candidate = ($instanceDir ?? getcwd()) . '/' . $segment;
            if (!is_file($candidate . '/index.php')) {
                break;
            }
            $instanceDir = $candidate;
            $uriPath = substr($uriPath, $slashPos + 1);
        }
        if ($instanceDir !== null) {
            if (!str_starts_with($uriPath, self::PUBLISHED_PREFIX) && !self::isServablePath($uriPath)) {
                // instance page URL (not an asset): nothing to serve statically here
                return;
            }
            // serve on the instance's behalf: materialize() targets and custom/ overrides
            // resolve through getcwd()
            chdir($instanceDir);
        }

        // A wiki reached under a URL prefix that is not a directory here -- an `alias`, an
        // nginx `location`, a reverse proxy mounting it at /ecto -- leaves that prefix in the
        // path with nothing to match it against: SCRIPT_NAME says `/index.php`, and the walk
        // above finds no `ecto/index.php` because there is no such folder. The asset request
        // then fell through to the wiki, which answered a stylesheet with an HTML page
        // saying it had no handler by that name.
        //
        // So: drop leading segments until what is left is an asset these sources really
        // have. Nothing new becomes reachable -- the very same files already answer at the
        // unprefixed path -- and a path that resolves to no file is left alone, because the
        // wiki may well have a page by that name.
        if (!str_starts_with($uriPath, self::PUBLISHED_PREFIX) && !self::isServablePath($uriPath)) {
            $rest = $uriPath;
            while (($slash = strpos($rest, '/')) !== false) {
                $rest = substr($rest, $slash + 1);
                if (str_starts_with($rest, self::PUBLISHED_PREFIX)) {
                    $uriPath = $rest;
                    break;
                }
                if (self::isServablePath($rest) && self::resolveSourceFile($rest) !== null) {
                    $uriPath = $rest;
                    break;
                }
            }
        }

        if (str_starts_with($uriPath, self::PUBLISHED_PREFIX)) {
            // cache/assets/{version}/{relPath} : materialize from sources, serve immutable
            $rest = substr($uriPath, strlen(self::PUBLISHED_PREFIX));
            $parts = explode('/', $rest, 2);
            if (count($parts) !== 2 || !self::isValidVersion($parts[0]) || !self::isServablePath($parts[1])) {
                if ($instanceDir !== null) {
                    // never fall through to booting the shared root wiki with a changed cwd
                    self::notFound();
                }

                return;
            }
            $target = self::materialize($parts[0], $parts[1]);
            if ($target !== null) {
                self::serveFile($target, true);
            }
            self::notFound();
        }

        if (self::isServablePath($uriPath)) {
            // direct source path (hardcoded template references): the webserver only falls
            // back here when the file does not exist in the instance docroot, so stream it
            // from the sources with a moderate cache lifetime (no version in the URL)
            $sourceFile = self::resolveSourceFile($uriPath);
            if ($sourceFile !== null) {
                self::serveFile($sourceFile, false);
            }
            self::notFound();
        }
    }

    /**
     * Publish $relPath (eagerly copied if needed) and return its instance-relative URL path,
     * or null when the file exists nowhere. Used by AssetRegistry when it resolves a URL so first
     * page views don't pay one PHP fallback round-trip per registered asset.
     */
    public static function publishedUrl(string $relPath, string $version): ?string
    {
        $version = self::sanitizeVersion($version);
        if (!self::isServablePath($relPath)) {
            return null;
        }
        $target = self::materialize($version, $relPath);

        return $target === null ? null : self::PUBLISHED_PREFIX . $version . '/' . $relPath;
    }

    public static function sanitizeVersion(string $version): string
    {
        $version = preg_replace('/[^A-Za-z0-9._-]/', '-', $version);

        return self::isValidVersion($version) ? $version : 'dev';
    }

    private static function isValidVersion(string $version): bool
    {
        return (bool)preg_match('/^[A-Za-z0-9_-][A-Za-z0-9._-]{0,63}$/', $version);
    }

    /**
     * Only ever serve regular files with a whitelisted prefix and extension, no dot segments
     * ('..' traversal, hidden files) anywhere in the path.
     */
    private static function isServablePath(string $relPath): bool
    {
        if ($relPath === '' || str_contains($relPath, "\0") || str_contains($relPath, '\\')) {
            return false;
        }
        foreach (explode('/', $relPath) as $segment) {
            if ($segment === '' || $segment[0] === '.') {
                return false;
            }
        }
        $extension = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
        if (!isset(self::MIME_TYPES[$extension])) {
            return false;
        }
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($relPath, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Locate $relPath in the source tree, or in the instance dir as fallback (per-instance
     * custom/ assets). Containment double-checked with realpath.
     */
    private static function resolveSourceFile(string $relPath): ?string
    {
        $roots = [self::sourceDir()];
        $instanceDir = getcwd();
        if ($instanceDir !== false && $instanceDir !== $roots[0]) {
            $roots[] = $instanceDir;
        }
        foreach ($roots as $root) {
            $candidate = $root . '/' . $relPath;
            if (is_file($candidate)) {
                $real = realpath($candidate);
                $realRoot = realpath($root);
                if ($real !== false && $realRoot !== false && str_starts_with($real, $realRoot . DIRECTORY_SEPARATOR)) {
                    return $real;
                }
            }
        }

        return null;
    }

    /**
     * Ensure cache/assets/{version}/{relPath} exists in the instance dir and is up to date,
     * copying from the sources when needed. Returns the absolute target path, or null when
     * the asset exists nowhere. For the 'dev' version (no yeswiki_release set) freshness is
     * mtime-checked so source edits show up without clearing the cache.
     */
    private static function materialize(string $version, string $relPath): ?string
    {
        $assetsDir = getcwd() . '/' . rtrim(self::PUBLISHED_PREFIX, '/');
        $target = $assetsDir . '/' . $version . '/' . $relPath;

        if (is_file($target) && $version !== 'dev') {
            return $target;
        }

        $sourceFile = self::resolveSourceFile($relPath);
        if ($sourceFile === null) {
            return is_file($target) ? $target : null;
        }
        if (is_file($target) && filemtime($target) >= filemtime($sourceFile)) {
            return $target;
        }

        if (!is_dir($assetsDir . '/' . $version)) {
            self::removeStaleVersions($assetsDir, $version);
        }
        $targetDir = \dirname($target);
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return null;
        }
        // copy to a temp name then rename, so concurrent requests never read a partial file
        $tmp = $targetDir . '/.' . uniqid('publish', true) . '.tmp';
        if (!@copy($sourceFile, $tmp)) {
            return null;
        }
        touch($tmp, filemtime($sourceFile) ?: time());
        if (!@rename($tmp, $target)) {
            @unlink($tmp);

            return is_file($target) ? $target : null;
        }

        return $target;
    }

    /**
     * A new version dir means the core was updated: drop the previous versions' trees
     * (published URLs embed the version, nothing references the old ones anymore).
     */
    private static function removeStaleVersions(string $assetsDir, string $currentVersion): void
    {
        foreach (glob($assetsDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (basename($dir) !== $currentVersion && self::isValidVersion(basename($dir))) {
                self::removeDir($dir);
            }
        }
    }

    private static function removeDir(string $dir): void
    {
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $entry) {
            $path = $dir . '/' . $entry;
            is_dir($path) && !is_link($path) ? self::removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private static function serveFile(string $absFile, bool $immutable): void
    {
        $extension = strtolower(pathinfo($absFile, PATHINFO_EXTENSION));
        $mtime = filemtime($absFile) ?: time();
        $size = filesize($absFile);
        $etag = '"' . md5($absFile . $mtime . $size) . '"';

        header('Content-Type: ' . (self::MIME_TYPES[$extension] ?? 'application/octet-stream'));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: ' . ($immutable ? 'public, max-age=31536000, immutable' : 'public, max-age=86400'));
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('ETag: ' . $etag);

        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
        if ($ifNoneMatch === $etag || (!$ifNoneMatch && $ifModifiedSince && strtotime($ifModifiedSince) >= $mtime)) {
            http_response_code(304);
            exit;
        }

        header('Content-Length: ' . $size);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            readfile($absFile);
        }
        exit;
    }

    private static function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Not found';
        exit;
    }
}
