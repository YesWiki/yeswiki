<?php

/* Turns `.twig` files into `template` Contents at the same path, for the migration, archive restores and `templates:import`; and writes them back out for `templates:export` (ticket 18). */

namespace YesWiki\Render\Service;

use YesWiki\Files\Service\LocalFiles;

class TemplateImporter
{
    public const IMPORTED = 'imported';

    public const SKIPPED = 'skipped';

    public const INVALID = 'invalid';

    public const DELETED = 'deleted';

    public const KEPT = 'kept';

    public function __construct(
        private readonly TemplateOverrides $overrides,
        private readonly LocalFiles $files,
    ) {
    }

    /**
     * Imports every `.twig` under $directory, its path relative to it naming the override.
     *
     * @return array{imported: list<string>, skipped: list<string>, invalid: array<string, string>, deleted: list<string>, kept: list<string>}
     */
    public function importFrom(string $directory, bool $force = false, bool $keepFiles = false, bool $dryRun = false): array
    {
        $report = [self::IMPORTED => [], self::SKIPPED => [], self::INVALID => [], self::DELETED => [], self::KEPT => []];
        foreach ($this->twigFilesIn($directory) as $found) {
            $file = rtrim($directory, '/') . '/' . $found;
            $path = self::currentPath($found);
            if (!$force && $this->knows($path)) {
                $report[self::SKIPPED][] = $path;
                continue;
            }
            $twig = $this->files->read($file);
            try {
                if ($dryRun) {
                    TemplateOverrides::tagFor($path);
                    $this->overrides->check($path, $twig);
                } else {
                    $this->overrides->save($path, $twig);
                }
            } catch (\InvalidArgumentException $refused) {
                $report[self::INVALID][$path] = $refused->getMessage();
                continue;
            }
            $report[self::IMPORTED][] = $path;
            if ($keepFiles) {
                continue;
            }
            if ($this->files->isWritable($file) && $this->files->isWritable(\dirname($file)) && ($dryRun || $this->files->remove($file))) {
                $report[self::DELETED][] = $path;
            } else {
                $report[self::KEPT][] = $path;
            }
        }
        if (!$dryRun && !$keepFiles) {
            $this->removeEmptyFolders($directory);
        }

        return $report;
    }

    /**
     * Writes every override the database holds under $directory, at its path.
     *
     * @return list<string> the paths written
     */
    public function exportTo(string $directory, bool $dryRun = false): array
    {
        $written = [];
        foreach ($this->overrides->all() as $override) {
            $file = rtrim($directory, '/') . '/' . $override['path'];
            if (!$dryRun) {
                $this->files->makeDirectory(\dirname($file));
                if (!$this->files->writeAtomically($file, (string)$this->overrides->read($override['path']))) {
                    throw new \RuntimeException("Cannot write $file");
                }
            }
            $written[] = $override['path'];
        }

        return $written;
    }

    /** Doryphore kept bazar's overrides in `bazar/`; bazar's templates are core's now, so they live under `core/`. */
    public static function currentPath(string $path): string
    {
        return str_starts_with($path, 'bazar/') ? 'core/' . substr($path, \strlen('bazar/')) : $path;
    }

    private function knows(string $path): bool
    {
        try {
            return $this->overrides->revisionOf($path) !== null;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /** @return list<string> the `.twig` files under $directory, relative to it, sorted */
    private function twigFilesIn(string $directory, string $prefix = ''): array
    {
        $found = [];
        $here = rtrim($directory, '/') . ($prefix === '' ? '' : '/' . $prefix);
        if (!$this->files->isDirectory($here)) {
            return [];
        }
        foreach ($this->files->entriesIn($here) as $entry) {
            $relative = $prefix === '' ? $entry : $prefix . '/' . $entry;
            if ($this->files->isDirectory($here . '/' . $entry)) {
                array_push($found, ...$this->twigFilesIn($directory, $relative));
            } elseif (str_ends_with(strtolower($entry), '.twig')) {
                $found[] = $relative;
            }
        }
        sort($found);

        return $found;
    }

    private function removeEmptyFolders(string $directory, string $prefix = ''): void
    {
        $here = rtrim($directory, '/') . ($prefix === '' ? '' : '/' . $prefix);
        if (!$this->files->isDirectory($here)) {
            return;
        }
        foreach ($this->files->entriesIn($here) as $entry) {
            if ($this->files->isDirectory($here . '/' . $entry)) {
                $this->removeEmptyFolders($directory, $prefix === '' ? $entry : $prefix . '/' . $entry);
            }
        }
        if ($prefix !== '') {
            $this->files->removeEmptyDirectory($here);
        }
    }
}
