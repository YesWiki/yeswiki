<?php

namespace YesWiki\Render\Service;

/**
 * Template overrides in `custom/templates/`, as something a webmaster can see and edit (ticket 30).
 */
class CustomTemplateService
{
    /** Instance-relative, matching the paths TemplateEngine adds to its loader. */
    public const DIRECTORY = 'custom/templates';

    /** Where the shipped originals are. */
    public const CORE_NAMESPACE = 'core';

    /** The screen's own template, and it must stay on `@shipped`. */
    public const SCREEN_TEMPLATE = '@shipped/admin/custom-templates.twig';

    private const COMPILED_CACHE = 'cache/templates';

    public function __construct(
        protected TemplateEngine $templateEngine,
    ) {
    }

    /**
     * Every override on this instance: what it is called, what it overrides, how big it is.
     *
     * @return list<array{path: string, namespace: string, target: string, size: int, modified: int, shipped: bool}>
     */
    public function overrides(): array
    {
        if (!is_dir(self::DIRECTORY)) {
            return [];
        }

        $found = [];
        foreach ($this->twigFilesIn(self::DIRECTORY) as $path) {
            $relative = substr($path, strlen(self::DIRECTORY) + 1);
            [$namespace, $target] = $this->splitOverride($relative);
            $found[] = [
                'path' => $relative,
                'namespace' => $namespace,
                'target' => $target,
                'size' => (int)@filesize($path),
                'modified' => (int)@filemtime($path),
                'shipped' => $namespace === self::CORE_NAMESPACE && $this->shippedPath($target) !== null,
            ];
        }

        usort($found, fn (array $a, array $b) => strcmp($a['path'], $b['path']));

        return $found;
    }

    /**
     * The shipped templates, as paths relative to `templates/`.
     *
     * @return list<string>
     */
    public function shipped(): array
    {
        $root = YESWIKI_SOURCE_DIR . '/templates';
        $names = [];
        foreach ($this->twigFilesIn($root) as $path) {
            $names[] = substr($path, strlen($root) + 1);
        }
        sort($names);

        return $names;
    }

    /** The contents of an override, or '' if it does not exist. */
    public function read(string $relative): string
    {
        $path = $this->overridePath($relative);

        return is_file($path) ? (string)file_get_contents($path) : '';
    }

    /** The shipped original of a `core/…` override, or null when there is none. */
    public function readShipped(string $relative): ?string
    {
        [$namespace, $target] = $this->splitOverride($relative);
        if ($namespace !== self::CORE_NAMESPACE) {
            return null;
        }
        $path = $this->shippedPath($target);

        return $path === null ? null : (string)file_get_contents($path);
    }

    public function exists(string $relative): bool
    {
        return is_file($this->overridePath($relative));
    }

    /**
     * Write an override, having first checked that it compiles.
     *
     * @throws \RuntimeException on a template that does not compile, or a write that fails
     */
    public function write(string $relative, string $contents): void
    {
        $path = $this->overridePath($relative);
        $this->check($relative, $contents);

        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Cannot create %s', $directory));
        }
        if (@file_put_contents($path, $contents) === false) {
            throw new \RuntimeException(sprintf('Cannot write %s', $path));
        }

        $this->clearCompiled();
    }

    /**
     * Put the shipped template back, by removing the override.
     *
     * @throws \RuntimeException when the file cannot be removed
     */
    public function delete(string $relative): void
    {
        $path = $this->overridePath($relative);
        if (!is_file($path)) {
            return;
        }
        if (!@unlink($path)) {
            throw new \RuntimeException(sprintf('Cannot remove %s', $path));
        }

        $this->clearCompiled();
    }

    /**
     * Start an override by copying the shipped template, verbatim.
     *
     * @throws \RuntimeException when there is no such shipped template, or one already exists
     */
    public function copyFromShipped(string $target): string
    {
        $source = $this->shippedPath($target);
        if ($source === null) {
            throw new \RuntimeException(sprintf('No shipped template called %s', $target));
        }

        $relative = self::CORE_NAMESPACE . '/' . $target;
        if ($this->exists($relative)) {
            throw new \RuntimeException(sprintf('%s is already overridden', $target));
        }

        $this->write($relative, (string)file_get_contents($source));

        return $relative;
    }

    public function isWritable(): bool
    {
        if (is_dir(self::DIRECTORY)) {
            return is_writable(self::DIRECTORY);
        }
        $parent = dirname(self::DIRECTORY);

        return is_dir($parent) && is_writable($parent);
    }

    /**
     * Whether a template compiles, without writing anything.
     *
     * @throws \RuntimeException with Twig's own message, which names the line
     */
    public function check(string $relative, string $contents): void
    {
        try {
            $this->templateEngine->parseTemplateSource($relative, $contents);
        } catch (\Twig\Error\Error $broken) {
            throw new \RuntimeException($broken->getMessage(), 0, $broken);
        }
    }

    /**
     * An instance path for an override, refusing anything that is not one.
     *
     * @throws \RuntimeException on anything outside the directory
     */
    private function overridePath(string $relative): string
    {
        $relative = str_replace('\\', '/', $relative);

        if (str_starts_with($relative, '/') || preg_match('~^[a-z]:~i', $relative) === 1) {
            throw new \RuntimeException('A template override path must be relative to ' . self::DIRECTORY);
        }

        $relative = rtrim($relative, '/');
        if ($relative === '' || !str_ends_with(strtolower($relative), '.twig')) {
            throw new \RuntimeException('A template override must be a .twig file');
        }
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \RuntimeException('A template override path may not contain "..".');
            }
        }

        $path = self::DIRECTORY . '/' . $relative;
        $root = realpath(self::DIRECTORY);
        $parent = realpath(dirname($path));

        if ($root !== false && $parent !== false && !str_starts_with($parent . '/', $root . '/')) {
            throw new \RuntimeException('A template override must live under ' . self::DIRECTORY);
        }

        return $path;
    }

    /** The shipped file behind a `core/` override, or null. */
    private function shippedPath(string $target): ?string
    {
        $root = YESWIKI_SOURCE_DIR . '/templates';
        $path = $root . '/' . $target;
        $real = realpath($path);

        if ($real === false || !is_file($real) || !str_starts_with($real, (string)realpath($root) . '/')) {
            return null;
        }

        return $real;
    }

    /**
     * `core/admin/content.twig` -> ['core', 'admin/content.twig'].
     *
     * @return array{0: string, 1: string}
     */
    private function splitOverride(string $relative): array
    {
        $at = strpos($relative, '/');

        return $at === false
            ? ['custom', $relative]
            : [substr($relative, 0, $at), substr($relative, $at + 1)];
    }

    /**
     * @return list<string> absolute-ish paths of every .twig under $root
     */
    private function twigFilesIn(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        $walker = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($walker as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'twig') {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }
        sort($files);

        return $files;
    }

    /** Twig compiles to `cache/templates/`, keyed on the path. */
    private function clearCompiled(): void
    {
        if (!is_dir(self::COMPILED_CACHE)) {
            return;
        }
        $walker = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::COMPILED_CACHE, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($walker as $file) {
            if ($file instanceof \SplFileInfo) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }
        }
    }
}
