<?php

namespace YesWiki\Files\Service;

/**
 * The Program tree: the code a release ships, as opposed to a wiki's own data.
 *
 * `Storage` is rooted at an Instance and its tiers describe what a wiki owns -- uploads, cache,
 * configuration -- which is why Public and Protected can live in a bucket (ADR-0022). The Program
 * is the other filesystem: themes, templates, javascripts, language files, the source tree. It is
 * the same bytes for every wiki on the host, it is read-only in most deployments, and on S3 it is
 * emphatically not in the bucket.
 *
 * Nothing here is polymorphic and it never will be, which is a fair thing to say about a service.
 * It exists so the question "which filesystem is this path in?" is answered by which service is
 * asked, rather than guessed from a string. That distinction is the one ADR-0022 is about, and
 * getting it wrong is invisible until an instance runs on object storage.
 *
 * **An Instance may shadow the Program.** A wiki's own `custom/` copy of a theme, template or
 * javascript wins over the shipped one, which is what `custom/` has always been for. `find()` is
 * that rule in one place: ask the Instance first, fall back to the Program.
 */
class ProgramFiles
{
    private string $root;

    public function __construct(private readonly Storage $storage)
    {
        $this->root = \defined('YESWIKI_PROGRAM_DIR') ? YESWIKI_PROGRAM_DIR : \dirname(__DIR__, 3);
    }

    /** The absolute path of a Program-relative one, for the four libraries that need a real path. */
    public function path(string $path): string
    {
        return $this->root . '/' . ltrim($path, '/');
    }

    public function root(): string
    {
        return $this->root;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->path($path));
    }

    public function isFile(string $path): bool
    {
        return is_file($this->path($path));
    }

    public function isDirectory(string $path): bool
    {
        return is_dir($this->path($path));
    }

    /** The bytes, or the empty string when there are none. Reading the Program never throws: a missing template is a fallback, not a failure. */
    public function read(string $path): string
    {
        $full = $this->path($path);

        return is_file($full) ? (string)file_get_contents($full) : '';
    }

    public function modifiedAt(string $path): int
    {
        return (int)@filemtime($this->path($path));
    }

    public function size(string $path): int
    {
        $full = $this->path($path);

        return is_file($full) ? (int)filesize($full) : 0;
    }

    /**
     * The files directly under this directory, as Program-relative paths.
     *
     * Deliberately the same contract as `Storage::files()` -- paths, sorted, no dot entries -- so
     * that code which has to look at both trees is written once and differs only in which service
     * it asks.
     *
     * @return list<string>
     */
    public function files(string $directory, bool $deep = false): array
    {
        return $this->listing($directory, $deep, static fn (string $full) => is_file($full));
    }

    /**
     * The directories directly under this one, as Program-relative paths.
     *
     * @return list<string>
     */
    public function directories(string $directory): array
    {
        return $this->listing($directory, false, static fn (string $full) => is_dir($full));
    }

    /**
     * @param callable(string): bool $keep
     *
     * @return list<string>
     */
    private function listing(string $directory, bool $deep, callable $keep): array
    {
        $full = $this->path($directory);
        if (!is_dir($full)) {
            return [];
        }

        $relative = trim($directory, '/');
        $found = [];
        foreach (array_diff((array)scandir($full), ['.', '..']) as $name) {
            $child = $relative === '' ? (string)$name : $relative . '/' . $name;
            $childFull = $this->path($child);
            if ($keep($childFull)) {
                $found[] = $child;
            }
            if ($deep && is_dir($childFull)) {
                $found = array_merge($found, $this->listing($child, true, $keep));
            }
        }
        sort($found);

        /* @var list<string> */
        return $found;
    }

    /**
     * @return list<string> Program-relative paths
     */
    public function glob(string $pattern): array
    {
        $found = glob($this->path($pattern)) ?: [];

        return array_map(
            fn (string $path) => ltrim(substr($path, \strlen($this->root)), '/'),
            $found
        );
    }

    /**
     * A Program path resolved through symlinks, refused if it escapes the Program tree.
     *
     * The four libraries that need a real path (ADR-0022 names them) get one from here, and a
     * `../` that climbs out gets null rather than a file the caller did not mean to offer.
     */
    public function realPath(string $path): ?string
    {
        $real = realpath($this->path($path));
        $root = realpath($this->root);

        if ($real === false || $root === false || !str_starts_with($real, $root . '/')) {
            return null;
        }

        return $real;
    }

    /**
     * The same path in the Instance if the wiki has its own, otherwise in the Program.
     *
     * This is what `custom/` means, and it was open-coded at about a dozen call sites, each with
     * its own idea of which side to try first.
     *
     * @return array{0: string, 1: bool} the bytes, and whether they came from the Instance
     */
    public function findWithSource(string $instancePath, string $programPath): array
    {
        if ($this->instanceHas($instancePath)) {
            return [$this->storage->read($instancePath), true];
        }

        return [$this->read($programPath), false];
    }

    /**
     * Whether the wiki has its own copy of this path.
     *
     * A path in no tier is not the wiki's -- `extensions/helloworld/javascripts/greeting.js` is
     * code the release or an installer put there, and `Storage::tierOf()` refuses it by design
     * rather than guessing. Refusing is the right answer to "which tier?" and the wrong answer to
     * "does the wiki have one?", so it is turned into `no` here.
     */
    public function instanceHas(string $path): bool
    {
        try {
            return $this->storage->exists($path);
        } catch (\Throwable) {
            return false;
        }
    }

    /** The Instance's copy if it has one, otherwise the Program's, or the empty string. */
    public function find(string $instancePath, string $programPath): string
    {
        return $this->findWithSource($instancePath, $programPath)[0];
    }

    /** Whether either side has it. */
    public function findExists(string $instancePath, string $programPath): bool
    {
        return $this->instanceHas($instancePath) || $this->exists($programPath);
    }
}
