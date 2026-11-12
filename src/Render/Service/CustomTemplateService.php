<?php

namespace YesWiki\Render\Service;

use YesWiki\Files\Service\ProgramFiles;
use YesWiki\Files\Service\Storage;

/**
 * Template overrides as something a webmaster can see and edit (ticket 30): saved in the database (ticket 18), over the files the operator ships in `custom/templates/`.
 */
class CustomTemplateService
{
    public const SHIPPED_DIRECTORY = 'templates';

    public const DIRECTORY = 'custom/templates';

    /** Where the shipped originals are. */
    public const CORE_NAMESPACE = 'core';

    public const SCREEN_TEMPLATE = '@shipped/admin/custom-templates.twig';

    public const IN_THE_DATABASE = 'database';

    public const ON_DISK = 'disk';

    public function __construct(
        protected TemplateOverrides $database,
        protected Storage $storage,
        protected ProgramFiles $programFiles,
    ) {
    }

    /**
     * Every override on this instance, the database's and the operator's files, each once.
     *
     * @return list<array{path: string, namespace: string, target: string, size: int, modified: int, shipped: bool, source: string, onDisk: bool, tag: string}>
     */
    public function overrides(): array
    {
        $found = [];
        foreach ($this->database->all() as $override) {
            $found[$override['path']] = $this->describe($override['path'], self::IN_THE_DATABASE, strlen((string)$this->database->read($override['path'])), (int)strtotime($override['time']), $override['tag']);
        }
        foreach ($this->filesOnDisk() as $relative) {
            if (isset($found[$relative])) {
                $found[$relative]['onDisk'] = true;
                continue;
            }
            $path = self::DIRECTORY . '/' . $relative;
            $found[$relative] = $this->describe($relative, self::ON_DISK, $this->storage->fileSize($path), $this->storage->lastModified($path), '');
        }
        ksort($found);

        return array_values($found);
    }

    /** @return array{path: string, namespace: string, target: string, size: int, modified: int, shipped: bool, source: string, onDisk: bool, tag: string} */
    private function describe(string $relative, string $source, int $size, int $modified, string $tag): array
    {
        [$namespace, $target] = $this->splitOverride($relative);

        return [
            'path' => $relative,
            'namespace' => $namespace,
            'target' => $target,
            'size' => $size,
            'modified' => $modified,
            'shipped' => $namespace === self::CORE_NAMESPACE && $this->shippedPath($target) !== null,
            'source' => $source,
            'onDisk' => $source === self::ON_DISK,
            'tag' => $tag,
        ];
    }

    /** @return list<string> the `.twig` files the operator put in `custom/templates/`, relative to it */
    public function filesOnDisk(): array
    {
        $found = [];
        foreach ($this->storage->files(self::DIRECTORY, true) as $path) {
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'twig') {
                $found[] = substr($path, strlen(self::DIRECTORY) + 1);
            }
        }

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

    /** The contents of an override, the database's before the operator's file, or '' if neither exists. */
    public function read(string $relative): string
    {
        $path = $this->overridePath($relative);

        return $this->database->read($relative) ?? ($this->storage->fileExists($path) ? $this->storage->read($path) : '');
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
        return $this->database->revisionOf($relative) !== null || $this->storage->fileExists($this->overridePath($relative));
    }

    /** Whether the database holds this override, which the screens can take away; the operator's file stays. */
    public function inTheDatabase(string $relative): bool
    {
        return $this->database->revisionOf($relative) !== null;
    }

    public function isOnDisk(string $relative): bool
    {
        return $this->storage->fileExists($this->overridePath($relative));
    }

    /** Saves an override in the database once it compiles; a RuntimeException says why it was not. */
    public function write(string $relative, string $contents): void
    {
        $this->overridePath($relative);

        try {
            $this->database->save($relative, $contents);
        } catch (\InvalidArgumentException $refused) {
            throw new \RuntimeException($refused->getMessage(), 0, $refused);
        }
    }

    /** Takes the database's override away, so the operator's file or the shipped template shows again; a file alone is the operator's to remove. */
    public function delete(string $relative): void
    {
        $this->overridePath($relative);
        if (!$this->inTheDatabase($relative)) {
            if ($this->isOnDisk($relative)) {
                throw new \RuntimeException(sprintf('%s is a file the operator ships in %s: only they can remove it', $relative, self::DIRECTORY));
            }

            return;
        }
        $this->database->delete($relative);
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
        return true;
    }

    /**
     * Whether a template compiles, without writing anything.
     *
     * @throws \RuntimeException with Twig's own message, which names the line
     */
    public function check(string $relative, string $contents): void
    {
        try {
            $this->database->check($relative, $contents);
        } catch (\InvalidArgumentException $broken) {
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
}
