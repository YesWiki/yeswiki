<?php

namespace YesWiki\Kernel\Service;

/**
 * Publishes static assets (css/js/fonts/images...) from the YesWiki Program tree into the instance's own cache/assets/{version}/ folder, so that an instance whose docroot only contains index.php + its data folders (yeswiki.config.php, files/, custom/, cache/, private/) needs no symlinks or webserver aliases to the shared YesWiki sources.
 */
class AssetPublisher
{
    public const PUBLISHED_PREFIX = 'cache/assets/';

    /** Beside the version folders, not inside one: it outlives them. */
    private const STAMP_FILE = '.sources-changed';

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

    /** Root of the shared YesWiki sources. */
    public static function programDir(): string
    {
        return defined('YESWIKI_PROGRAM_DIR') ? constant('YESWIKI_PROGRAM_DIR') : \dirname(__DIR__, 3);
    }

    /** Called from index.php before anything else boots. */
    public static function interceptAssetRequest(): void
    {
        if (\PHP_SAPI === 'cli' || !in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
            return;
        }

        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $uriPath = rawurldecode(is_string($requestPath) ? $requestPath : '');

        if (preg_match('~^(.+)/index\.php$~', $_SERVER['SCRIPT_NAME'] ?? '', $m)
            && str_starts_with($uriPath, $m[1] . '/')) {
            $uriPath = substr($uriPath, strlen($m[1]));
        }
        $uriPath = ltrim($uriPath, '/');

        if (str_starts_with($uriPath, 'index.php/')) {
            $uriPath = substr($uriPath, strlen('index.php/'));
        }

        if ($uriPath === '' || basename($uriPath) === 'index.php') {
            $fromQuery = ltrim(rawurldecode(explode('&', (string)($_SERVER['QUERY_STRING'] ?? ''), 2)[0]), '/');
            if ($fromQuery !== '' && !str_contains($fromQuery, '=')) {
                $uriPath = $fromQuery;
            }
        }

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
                return;
            }

            chdir($instanceDir);
        }

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
            $rest = substr($uriPath, strlen(self::PUBLISHED_PREFIX));
            $parts = explode('/', $rest, 2);
            if (count($parts) !== 2 || !self::isValidVersion($parts[0]) || !self::isServablePath($parts[1])) {
                self::notFound('not a publishable asset path: ' . $rest);
            }
            $target = self::materialize($parts[0], $parts[1]);
            if ($target !== null) {
                self::serveFile($target, true);
            }
            self::notFound(
                self::resolveSourceFile($parts[1]) === null
                    ? 'no such file in the sources: ' . $parts[1]
                    : 'could not publish into ' . getcwd() . '/' . self::PUBLISHED_PREFIX . ' (not writable?): ' . $parts[1]
            );
        }

        if (self::isServablePath($uriPath)) {
            $sourceFile = self::resolveSourceFile($uriPath);
            if ($sourceFile !== null) {
                self::serveFile($sourceFile, false);
            }
            self::notFound('no such file in the sources: ' . $uriPath);
        }

        $requested = rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
        if (str_contains($requested, '/' . self::PUBLISHED_PREFIX)) {
            self::notFound('asset path not placed: ' . $requested);
        }
    }

    /**
     * Publish $relPath (eagerly copied if needed) and return its instance-relative URL path, or null when the file exists nowhere.
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

    /** The stamp appended to the published version: how old the published set is, as a plain mtime. */
    public static function publishedStamp(): string
    {
        $assetsDir = getcwd() . '/' . rtrim(self::PUBLISHED_PREFIX, '/');
        $stamp = trim((string)@file_get_contents($assetsDir . '/' . self::STAMP_FILE));
        if (preg_match('/^[0-9]{1,20}$/', $stamp)) {
            return $stamp;
        }

        $newest = self::newestPublishedFile($assetsDir);
        if ($newest === 0) {
            return '';
        }
        self::bumpStamp($assetsDir, $newest);

        return (string)$newest;
    }

    /** mtime of the most recently published file, across every version folder present. */
    private static function newestPublishedFile(string $assetsDir): int
    {
        if (!is_dir($assetsDir)) {
            return 0;
        }

        $newest = 0;
        foreach (glob($assetsDir . '/*', GLOB_ONLYDIR) ?: [] as $versionDir) {
            if (!self::isValidVersion(basename($versionDir))) {
                continue;
            }
            $tree = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($versionDir, \FilesystemIterator::SKIP_DOTS));
            foreach ($tree as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile()) {
                    $newest = max($newest, (int)$file->getMTime());
                }
            }
        }

        return $newest;
    }

    /** Record how new the published set is. */
    private static function bumpStamp(string $assetsDir, int $sourceMtime): void
    {
        $file = $assetsDir . '/' . self::STAMP_FILE;
        $current = is_file($file) ? (int)trim((string)@file_get_contents($file)) : 0;
        if ($sourceMtime <= $current) {
            return;
        }
        if (!is_dir($assetsDir)) {
            @mkdir($assetsDir, 0755, true);
        }
        @file_put_contents($file, (string)$sourceMtime, LOCK_EX);
    }

    public static function sanitizeVersion(string $version): string
    {
        $version = (string)preg_replace('/[^A-Za-z0-9._-]/', '-', $version);

        return self::isValidVersion($version) ? $version : 'dev';
    }

    private static function isValidVersion(string $version): bool
    {
        return (bool)preg_match('/^[A-Za-z0-9_-][A-Za-z0-9._-]{0,63}$/', $version);
    }

    /**
     * Only ever serve regular files with a whitelisted prefix and extension, no dot segments ('..' traversal, hidden files) anywhere in the path.
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
     * Locate $relPath in the Program tree, or in the Instance dir as fallback (per-instance custom/ assets).
     */
    private static function resolveSourceFile(string $relPath): ?string
    {
        $roots = [self::programDir()];
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
     * Ensure cache/assets/{version}/{relPath} exists in the instance dir and is up to date, copying from the sources when needed.
     */
    private static function materialize(string $version, string $relPath): ?string
    {
        $assetsDir = getcwd() . '/' . rtrim(self::PUBLISHED_PREFIX, '/');
        $target = $assetsDir . '/' . $version . '/' . $relPath;

        self::publishMissingReferences($version, $assetsDir . '/' . $version);

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

        $tmp = $targetDir . '/.' . uniqid('publish', true) . '.tmp';
        if (!@copy($sourceFile, $tmp)) {
            return null;
        }
        touch($tmp, filemtime($sourceFile) ?: time());
        if (!@rename($tmp, $target)) {
            @unlink($tmp);

            return is_file($target) ? $target : null;
        }

        self::bumpStamp($assetsDir, (int)filemtime($sourceFile));
        self::publishReferences($version, $relPath, $sourceFile);

        return $target;
    }

    /**
     * Publish what a freshly published stylesheet or script points at: `@import`ed sheets, `url()` fonts and images, statically imported modules.
     *
     * @param array<string, true> $seen guards cycles (a.css imports b.css imports a.css)
     */
    private static function publishReferences(string $version, string $relPath, string $sourceFile, array &$seen = []): void
    {
        $extension = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
        if (!in_array($extension, ['css', 'js', 'mjs'], true) || \count($seen) > 500) {
            return;
        }
        $seen[$relPath] = true;

        $content = @file_get_contents($sourceFile);
        if ($content === false) {
            return;
        }

        $base = \dirname($relPath);
        foreach (self::referencesIn($content, $extension) as $reference) {
            $resolved = self::resolveRelative($base, $reference);
            if ($resolved === null || isset($seen[$resolved]) || !self::isServablePath($resolved)) {
                continue;
            }
            $seen[$resolved] = true;
            $referenced = self::resolveSourceFile($resolved);
            if ($referenced === null) {
                continue;
            }
            if (self::materializeOne($version, $resolved, $referenced)) {
                self::publishReferences($version, $resolved, $referenced, $seen);
            }
        }
    }

    /**
     * The relative references a stylesheet or script makes to files beside it.
     *
     * @return list<string>
     */
    private static function referencesIn(string $content, string $extension): array
    {
        $patterns = $extension === 'css'
            ? [
                '~@import\s+(?:url\()?\s*[\'"]([^\'"]+)[\'"]~i',
                '~url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)~i',
            ]
            : [
                '~(?:^|[\s;])(?:import|export)\s[^;\'"]*?from\s*[\'"]([^\'"]+)[\'"]~m',
                '~(?:^|[\s;])import\s*[\'"]([^\'"]+)[\'"]~m',
            ];

        $found = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $reference) {
                    $reference = trim($reference);
                    if ($reference === '' || !str_starts_with($reference, '.')) {
                        continue;
                    }
                    $found[] = $reference;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * Resolve `../fonts/x.woff2` against the directory of the file that referenced it, into a source-relative path.
     */
    private static function resolveRelative(string $base, string $reference): ?string
    {
        $reference = (string)preg_replace('~[?#].*$~', '', $reference);
        if ($reference === '') {
            return null;
        }

        $segments = [];
        foreach (explode('/', ($base === '.' ? '' : $base . '/') . $reference) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($segments === []) {
                    return null;
                }
                array_pop($segments);

                continue;
            }
            $segments[] = $segment;
        }

        return $segments === [] ? null : implode('/', $segments);
    }

    /** Copy one already-located source file into the published tree. */
    private static function materializeOne(string $version, string $relPath, string $sourceFile): bool
    {
        $target = getcwd() . '/' . rtrim(self::PUBLISHED_PREFIX, '/') . '/' . $version . '/' . $relPath;
        if (is_file($target) && filemtime($target) >= filemtime($sourceFile)) {
            return false;
        }

        $targetDir = \dirname($target);
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return false;
        }
        $tmp = $targetDir . '/.' . uniqid('publish', true) . '.tmp';
        if (!@copy($sourceFile, $tmp)) {
            return false;
        }
        touch($tmp, filemtime($sourceFile) ?: time());
        if (!@rename($tmp, $target)) {
            @unlink($tmp);

            return false;
        }
        self::bumpStamp(getcwd() . '/' . rtrim(self::PUBLISHED_PREFIX, '/'), (int)filemtime($sourceFile));

        return true;
    }

    /**
     * One sweep per published version: every stylesheet and script already sitting there gets its references published too.
     */
    private static function publishMissingReferences(string $version, string $versionDir): void
    {
        $marker = $versionDir . '/.references-published';
        if (!is_dir($versionDir) || is_file($marker)) {
            return;
        }
        if (@file_put_contents($marker, '') === false) {
            return;
        }

        $seen = [];
        $tree = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($versionDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($tree as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $relPath = ltrim(str_replace($versionDir, '', $file->getPathname()), '/');
            if (isset($seen[$relPath]) || !self::isServablePath($relPath)) {
                continue;
            }
            $sourceFile = self::resolveSourceFile($relPath);
            if ($sourceFile !== null) {
                self::publishReferences($version, $relPath, $sourceFile, $seen);
            }
        }
    }

    /**
     * A new version dir means the core was updated: drop the previous versions' trees (published URLs embed the version, nothing references the old ones anymore).
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

    /** 404 as text, never as a page, and saying which of the ways this can fail happened. */
    private static function notFound(string $reason = ''): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        echo 'YesWiki: asset not found', $reason === '' ? '' : ' -- ' . $reason, "\n";
        exit;
    }
}
