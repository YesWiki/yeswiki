<?php

namespace YesWiki\Render\Service;

use YesWiki\Files\Exception\StorageException;
use YesWiki\Files\Service\ProgramFiles;
use YesWiki\Files\Service\Storage;

/**
 * Template overrides in `custom/templates/`, as something a webmaster can see and edit (ticket 30).
 */
class CustomTemplateService
{
    /** Instance-relative, matching the paths TemplateEngine adds to its loader. */
    /** Where the shipped templates live in the Program tree. */
    public const SHIPPED_DIRECTORY = 'templates';

    public const DIRECTORY = 'custom/templates';

    /** Where the shipped originals are. */
    public const CORE_NAMESPACE = 'core';

    /** The screen's own template, and it must stay on `@shipped`. */
    public const SCREEN_TEMPLATE = '@shipped/admin/custom-templates.twig';

    private const COMPILED_CACHE = 'cache/templates';

    public function __construct(
        protected TemplateEngine $templateEngine,
        protected Storage $storage,
        protected ProgramFiles $programFiles,
    ) {
    }

    /**
     * Every override on this instance: what it is called, what it overrides, how big it is.
     *
     * @return list<array{path: string, namespace: string, target: string, size: int, modified: int, shipped: bool}>
     */
    public function overrides(): array
    {
        $found = [];
        foreach ($this->storage->files(self::DIRECTORY, true) as $path) {
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'twig') {
                continue;
            }
            $relative = substr($path, strlen(self::DIRECTORY) + 1);
            [$namespace, $target] = $this->splitOverride($relative);
            $found[] = [
                'path' => $relative,
                'namespace' => $namespace,
                'target' => $target,
                'size' => $this->storage->fileSize($path),
                'modified' => $this->storage->lastModified($path),
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
        $names = [];
        foreach ($this->programFiles->files(self::SHIPPED_DIRECTORY, true) as $path) {
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'twig') {
                $names[] = substr($path, \strlen(self::SHIPPED_DIRECTORY) + 1);
            }
        }
        sort($names);

        return $names;
    }

    /** The contents of an override, or '' if it does not exist. */
    public function read(string $relative): string
    {
        $path = $this->overridePath($relative);

        return $this->storage->fileExists($path) ? $this->storage->read($path) : '';
    }

    /** The shipped original of a `core/…` override, or null when there is none. */
    public function readShipped(string $relative): ?string
    {
        [$namespace, $target] = $this->splitOverride($relative);
        if ($namespace !== self::CORE_NAMESPACE) {
            return null;
        }
        $path = $this->shippedPath($target);

        return $path === null ? null : $this->programFiles->read($path);
    }

    public function exists(string $relative): bool
    {
        return $this->storage->fileExists($this->overridePath($relative));
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

        try {
            $this->storage->write($path, $contents);
        } catch (StorageException $exception) {
            throw new \RuntimeException(sprintf('Cannot write %s', $path), 0, $exception);
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
        if (!$this->storage->fileExists($path)) {
            return;
        }

        try {
            $this->storage->delete($path);
        } catch (StorageException $exception) {
            throw new \RuntimeException(sprintf('Cannot remove %s', $path), 0, $exception);
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

        $this->write($relative, $this->programFiles->read($source));

        return $relative;
    }

    public function isWritable(): bool
    {
        return $this->storage->isWritable(self::DIRECTORY);
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

        return self::DIRECTORY . '/' . $relative;
    }

    /** The shipped template behind a `core/` override, as a Program-relative path, or null. */
    private function shippedPath(string $target): ?string
    {
        $relative = self::SHIPPED_DIRECTORY . '/' . $target;

        return $this->programFiles->realPath($relative) !== null && $this->programFiles->isFile($relative)
            ? $relative
            : null;
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

    /** Twig compiles to `cache/templates/`, keyed on the path. */
    private function clearCompiled(): void
    {
        if (!$this->storage->directoryExists(self::COMPILED_CACHE)) {
            return;
        }
        $this->storage->deleteDirectory(self::COMPILED_CACHE);
    }
}
