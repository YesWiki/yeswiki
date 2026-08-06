<?php

namespace YesWiki\Render\Service;

/**
 * Template overrides in `custom/templates/`, as something a webmaster can see and edit
 * (ticket 30).
 *
 * ## Why there is no sandbox here
 *
 * Measured on Twig 3.28: **the sandbox propagates into `{% extends %}`ed and `{% include %}`d
 * templates.** A core template rendered under a global sandbox dies on its first
 * `{{ service.method() }}`, and `{% sandbox %}` scopes to `{% include %}` only -- an
 * override's whole point is `{% extends %}`, which no sandbox tag can reach. There is no
 * configuration in which the override is sandboxed and the core template it extends is not.
 *
 * So a custom template is **code**, at the same trust level as an extension, and the boundary
 * is authorisation: `custom/templates/` is already on the main Twig loader, so an override
 * has always run unsandboxed. This screen does not change what an override can do -- it
 * changes who can write one, from "whoever has FTP" to "whoever is an admin of this wiki",
 * which is a strictly smaller set on most instances.
 *
 * What this class does instead of a policy is the four things that actually go wrong:
 * confine writes to the directory, refuse a template that does not compile, be able to put
 * one back, and drop the compiled cache so the file on disk is the one that renders.
 */
class CustomTemplateService
{
    /** Instance-relative, matching the paths TemplateEngine adds to its loader. */
    public const DIRECTORY = 'custom/templates';

    /** Where the shipped originals are. `core` is the namespace they answer to. */
    public const CORE_NAMESPACE = 'core';

    /**
     * The screen's own template, and it must stay on `@shipped`.
     *
     * `@core` searches `custom/templates/core/` first, so `@core/admin/custom-templates.twig`
     * would be overridable -- and an override that breaks the screen for removing overrides
     * leaves FTP as the only way back. Named here rather than written inline in the
     * controller so that the rule is next to the code that depends on it, and so a test can
     * assert it (CustomTemplateServiceTest).
     */
    public const SCREEN_TEMPLATE = '@shipped/admin/custom-templates.twig';

    private const COMPILED_CACHE = 'cache/templates';

    public function __construct(
        protected TemplateEngine $templateEngine,
    ) {
    }

    /**
     * Every override on this instance: what it is called, what it overrides, how big it is.
     *
     * A file with no shipped counterpart is listed too, and marked. That is not an error --
     * a theme's own partial, or a template belonging to an extension that is currently
     * disabled -- but it is worth seeing, because the commonest way to write an override
     * that does nothing is to misspell its path.
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
     * This is the list the "start an override" picker offers. Only core ones: an extension's
     * templates live wherever the extension does, and offering to copy them would be
     * offering to fork a directory this screen does not own.
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
     * The compile check is the point. A syntax error in an override is by far the likeliest
     * failure here, and it does not fail where it was made: it fails on every page that
     * renders that template, as a 500. Caught at save time it is a message on this screen.
     *
     * It is a *parse*, not a render -- rendering would need the variables the real caller
     * passes, which this screen has no way to know. Parsing catches the whole class of error
     * that takes a wiki down (an unclosed block, a misspelled tag, a stray `{%`).
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
     * Deleting is what "revert" means here, and it is the honest verb: there is nothing to
     * restore, because the original was never modified -- it is still in `templates/`, and
     * removing the file in front of it is all it takes to be rendering it again.
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
     * Verbatim and not "a stub that extends it": a template is overridden by *replacing* it,
     * so an override that starts empty starts by breaking the page. Starting from the real
     * thing means the first save changes nothing, which is the only safe first save.
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
     * The three refusals are the whole of the confinement: a `.twig` extension, no traversal,
     * and a resolved parent that is still inside `custom/templates/`. `realpath()` is taken
     * of the *parent*, because the file itself may not exist yet -- which is precisely the
     * create case, and the one a naive `realpath($path)` check waves through.
     *
     * @throws \RuntimeException on anything outside the directory
     */
    private function overridePath(string $relative): string
    {
        $relative = str_replace('\\', '/', $relative);

        // refused, NOT trimmed to a relative one. Stripping the leading slash would turn
        // `/etc/passwd.twig` into `custom/templates/etc/passwd.twig` -- written, inside the
        // directory, under a name nobody asked for. Silently reinterpreting a path is how a
        // confinement check comes to report success on input it did not understand.
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
        // both null when the directory does not exist yet: nothing is there to escape into,
        // and mkdir below builds the path from the segments already checked above
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
     * A file directly in `custom/templates/` belongs to no namespace but `@custom`, which is
     * a real thing to have: the first path segment is only a namespace when there is a
     * second one.
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

    /**
     * Twig compiles to `cache/templates/`, keyed on the path.
     *
     * `auto_reload` is on, so Twig would notice a changed file by mtime -- but a *deleted*
     * override leaves the compiled class of a file that no longer exists, and a copy written
     * within the same second as the last compile can lose the mtime comparison. The whole
     * cost of being sure is one directory, rebuilt on the next render.
     */
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
