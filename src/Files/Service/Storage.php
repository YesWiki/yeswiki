<?php

namespace YesWiki\Files\Service;

use AsyncAws\S3\Exception\NoSuchBucketException;
use AsyncAws\S3\S3Client;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\DirectoryListing;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\Visibility;
use YesWiki\Files\Entity\S3Settings;
use YesWiki\Files\Exception\StorageException;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * The one way YesWiki's own code touches files, addressed by instance-relative paths whose prefix declares the tier (ADR-0022).
 */
class Storage
{
    public const PUBLIC_TIER = 'public';

    public const PROTECTED_TIER = 'protected';

    public const RUNTIME_TIER = 'runtime';

    /**
     * Which tier a path belongs to.
     *
     * @var array<string, string>
     */
    public const TIERS = [
        'custom/' => self::PUBLIC_TIER,
        'files/' => self::PUBLIC_TIER,
        'cache/' => self::PUBLIC_TIER,

        'private/files/' => self::PROTECTED_TIER,
        'private/backups/' => self::PROTECTED_TIER,
        'private/digests/' => self::PROTECTED_TIER,

        'custom/extensions/' => self::RUNTIME_TIER,
        'private/yeswiki.db' => self::RUNTIME_TIER,
        'private/search-indexes/' => self::RUNTIME_TIER,
        'private/.env' => self::RUNTIME_TIER,
        'cache/container/' => self::RUNTIME_TIER,
        'cache/templates/' => self::RUNTIME_TIER,
        'cache/routes/' => self::RUNTIME_TIER,
        'cache/assets/' => self::RUNTIME_TIER,
        'cache/importer/' => self::RUNTIME_TIER,
        'cache/HTMLpurifier/' => self::RUNTIME_TIER,
        'cache/hashcash.key' => self::RUNTIME_TIER,
        'cache/*.lock' => self::RUNTIME_TIER,
        'yeswiki.config.php' => self::RUNTIME_TIER,
    ];

    private string $root;

    private ?S3Settings $remote;

    private ?Filesystem $localFilesystem = null;

    private ?Filesystem $remoteFilesystem = null;

    private ?UrlFormatter $urlFormatter;

    public function __construct(?UrlFormatter $urlFormatter = null)
    {
        $this->root = \defined('YESWIKI_INSTANCE_DIR') ? YESWIKI_INSTANCE_DIR : (string)getcwd();
        $this->remote = S3Settings::fromEnvironment();
        $this->urlFormatter = $urlFormatter;
    }

    /** The same service over another directory, which is what a test wants and nothing else does. */
    public static function rootedAt(string $root): self
    {
        $storage = new self();
        $storage->root = rtrim($root, '/');
        $storage->remote = null;

        return $storage;
    }

    /** The same service with its Data tiers in a bucket, which is what a test and `storage:sync` want. */
    public static function rootedAtWith(string $root, ?S3Settings $remote): self
    {
        $storage = self::rootedAt($root);
        $storage->remote = $remote;

        return $storage;
    }

    /** Whether this path's bytes are somewhere other than this disk. */
    public function isRemote(string $path): bool
    {
        return $this->remote !== null && \in_array($this->tierOf($path), $this->remote->tiers, true);
    }

    /** Which tier this path lives in, or a refusal naming it: guessing is how a file lands in the wrong home. */
    public function tierOf(string $path): string
    {
        $path = $this->normalise($path);
        $best = null;
        foreach (self::TIERS as $pattern => $tier) {
            if (!$this->matches($pattern, $path)) {
                continue;
            }
            if ($best === null || \strlen($pattern) > \strlen($best)) {
                $best = $pattern;
            }
        }
        if ($best === null) {
            throw new StorageException("No storage tier is declared for '$path'.");
        }

        return self::TIERS[$best];
    }

    public function read(string $path): string
    {
        return $this->guard(fn () => $this->filesystem($path)->read($this->normalise($path)), $path);
    }

    /** @return resource */
    public function readStream(string $path)
    {
        return $this->guard(fn () => $this->filesystem($path)->readStream($this->normalise($path)), $path);
    }

    public function write(string $path, string $contents): void
    {
        $this->guard(fn () => $this->filesystem($path)->write($this->normalise($path), $contents, $this->writeOptions($path)), $path);
    }

    /** @param resource $contents */
    public function writeStream(string $path, $contents): void
    {
        $this->guard(fn () => $this->filesystem($path)->writeStream($this->normalise($path), $contents, $this->writeOptions($path)), $path);
    }

    /** The bytes of a file that is not the instance's: an upload, a download, a lease. */
    public function readForeign(string $localFile): string
    {
        $bytes = @file_get_contents($localFile);
        if ($bytes === false) {
            throw new StorageException("Unable to read '$localFile'.");
        }

        return $bytes;
    }

    /**
     * The same, as a stream, for something too big to hold: an uploaded CSV read a row at a time.
     *
     * @return resource
     */
    public function readForeignStream(string $localFile)
    {
        $handle = @fopen($localFile, 'r');
        if ($handle === false) {
            throw new StorageException("Unable to read '$localFile'.");
        }

        return $handle;
    }

    /** Bytes that arrived from outside -- an upload, a download -- put where they belong. */
    public function writeFrom(string $path, string $localFile): void
    {
        $handle = @fopen($localFile, 'r');
        if ($handle === false) {
            throw new StorageException("Unable to read '$localFile' on its way to '$path'.");
        }

        try {
            $this->writeStream($path, $handle);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /**
     * The only write allowed to fail quietly: a thumbnail, a cached feed image, a published asset -- whatever the caller can serve once and make again.
     */
    public function storeDerived(string $path, string $contents): bool
    {
        $this->tierOf($path);

        try {
            $this->filesystem($path)->write($this->normalise($path), $contents, $this->writeOptions($path));
        } catch (FilesystemException) {
            return false;
        }

        return true;
    }

    public function delete(string $path): void
    {
        $this->guard(fn () => $this->filesystem($path)->delete($this->normalise($path)), $path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->guard(fn () => $this->filesystem($path)->deleteDirectory($this->normalise($path)), $path);
    }

    public function move(string $source, string $destination): void
    {
        $this->copy($source, $destination);
        $this->delete($source);
    }

    public function copy(string $source, string $destination): void
    {
        $this->tierOf($destination);
        if ($this->isRemote($source) !== $this->isRemote($destination)) {
            $this->writeStream($destination, $this->readStream($source));

            return;
        }
        $this->guard(
            fn () => $this->filesystem($source)->copy(
                $this->normalise($source),
                $this->normalise($destination),
                $this->writeOptions($destination)
            ),
            $source
        );
    }

    /** Whether anything is there, file or directory, which is the question `file_exists()` was asking. */
    public function exists(string $path): bool
    {
        return $this->asking(fn () => $this->filesystem($path)->has($this->normalise($path)), $path);
    }

    public function fileExists(string $path): bool
    {
        return $this->asking(fn () => $this->filesystem($path)->fileExists($this->normalise($path)), $path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->asking(fn () => $this->filesystem($path)->directoryExists($this->normalise($path)), $path);
    }

    public function fileSize(string $path): int
    {
        return $this->guard(fn () => $this->filesystem($path)->fileSize($this->normalise($path)), $path);
    }

    public function lastModified(string $path): int
    {
        return $this->guard(fn () => $this->filesystem($path)->lastModified($this->normalise($path)), $path);
    }

    /**
     * @return DirectoryListing<StorageAttributes>
     */
    public function listContents(string $path, bool $deep = false): DirectoryListing
    {
        return $this->guard(fn () => $this->filesystem($path)->listContents($this->normalise($path), $deep), $path);
    }

    /**
     * The files under this directory, which is what `scandir()` and `glob()` were being asked for.
     *
     * @return list<string>
     */
    public function files(string $directory, bool $deep = false): array
    {
        if (!$this->directoryExists($directory)) {
            return [];
        }
        $paths = [];
        foreach ($this->listContents($directory, $deep) as $item) {
            if ($item->isFile()) {
                $paths[] = $item->path();
            }
        }
        sort($paths);

        return $paths;
    }

    /**
     * Whether a write here would land: a screen that offers an editor asks before offering one, because a read-only deploy is better said than discovered on submit.
     */
    public function isWritable(string $path): bool
    {
        if ($this->isRemote($path)) {
            return true;
        }
        $candidate = $this->absolutePath($path);
        while (!file_exists($candidate)) {
            $parent = \dirname($candidate);
            if ($parent === $candidate || \strlen($parent) < \strlen($this->root)) {
                return false;
            }
            $candidate = $parent;
        }

        return is_writable($candidate);
    }

    /**
     * The directories directly under this one, which is what `glob(..., GLOB_ONLYDIR)` was being asked for.
     *
     * @return list<string>
     */
    public function directories(string $directory): array
    {
        if (!$this->directoryExists($directory)) {
            return [];
        }
        $paths = [];
        foreach ($this->listContents($directory) as $item) {
            if ($item->isDir()) {
                $paths[] = $item->path();
            }
        }
        sort($paths);

        return $paths;
    }

    /**
     * The files matching a shell pattern, listed from the directory the pattern names, which is what `glob()` was being asked for.
     *
     * @return list<string>
     */
    public function glob(string $pattern): array
    {
        $directory = \dirname($pattern);
        $wanted = basename($pattern);
        $found = [];
        foreach ($this->files($directory) as $path) {
            if (fnmatch($wanted, basename($path))) {
                $found[] = $path;
            }
        }

        return $found;
    }

    /**
     * A real file on a real disk for the libraries that cannot take a stream, and nothing kept afterwards.
     *
     * @template T
     *
     * @param callable(string): T $use
     *
     * @return T
     */
    public function withLocalCopy(string $path, callable $use): mixed
    {
        if (!$this->isRemote($path)) {
            return $use($this->absolutePath($path));
        }

        return $this->withTemporaryFile(pathinfo($path, PATHINFO_EXTENSION), function (string $local) use ($path, $use) {
            $this->materialise($path, $local);

            return $use($local);
        });
    }

    /**
     * The mirror: a real path to produce a file at, seeded with whatever is already stored there, and stored back once the block returns.
     *
     * @template T
     *
     * @param callable(string): T $produce
     *
     * @return T
     */
    public function withLocalTarget(string $path, callable $produce): mixed
    {
        if (!$this->isRemote($path)) {
            $absolute = $this->absolutePath($path);
            $directory = \dirname($absolute);
            if (!is_dir($directory) && !@mkdir($directory, 0o755, true) && !is_dir($directory)) {
                throw new StorageException("Unable to create the directory for '$path'.");
            }

            return $produce($absolute);
        }

        return $this->withTemporaryFile(pathinfo($path, PATHINFO_EXTENSION), function (string $local) use ($path, $produce) {
            $this->materialise($path, $local);
            $produced = $produce($local);
            $this->writeFrom($path, $local);

            return $produced;
        });
    }

    /** Bring an object down to a real file, or leave the file absent when there is no object yet. */
    private function materialise(string $path, string $local): void
    {
        if (!$this->fileExists($path)) {
            return;
        }
        $bytes = $this->readStream($path);
        $handle = @fopen($local, 'w');
        if ($handle === false) {
            throw new StorageException("Unable to write the local copy of '$path'.");
        }

        try {
            stream_copy_to_stream($bytes, $handle);
        } finally {
            fclose($handle);
            if (is_resource($bytes)) {
                fclose($bytes);
            }
        }
    }

    /**
     * What `getimagesize()` says about this file, which needs a path and so needs a lease.
     *
     * @return array<int|string, mixed>|false
     */
    public function imageSize(string $path)
    {
        return $this->withLocalCopy($path, static fn (string $local) => @getimagesize($local));
    }

    /**
     * A real file on a real disk that is nobody's to keep, for the libraries that must be handed a path they can write beside.
     *
     * @template T
     *
     * @param callable(string): T $use
     *
     * @return T
     */
    public function withTemporaryFile(string $extension, callable $use): mixed
    {
        $temporary = tempnam(sys_get_temp_dir(), 'yeswiki');
        if ($temporary === false) {
            throw new StorageException('Unable to make a temporary file.');
        }
        $named = $extension === '' ? $temporary : $temporary . '.' . ltrim($extension, '.');
        if ($named !== $temporary) {
            @unlink($temporary);
        }

        try {
            return $use($named);
        } finally {
            foreach (array_unique([$temporary, $named]) as $leftover) {
                if (is_file($leftover)) {
                    @unlink($leftover);
                }
            }
        }
    }

    /**
     * Where a reader gets this file, which only a Public path has: a URL is exactly what an access check exists to withhold.
     */
    public function url(string $path): string
    {
        $tier = $this->tierOf($path);
        if ($tier !== self::PUBLIC_TIER) {
            throw new StorageException("'$path' is $tier, so it has no URL: it is served behind an access check.");
        }
        $path = $this->normalise($path);

        $remote = $this->remote;
        if ($remote !== null && $this->isRemote($path)) {
            return $remote->publicUrl . '/' . $path;
        }

        return $this->urlFormatter === null ? $path : $this->urlFormatter->getBaseUrl() . '/' . $path;
    }

    /**
     * The path as the machine running this sees it, for the leases and for nothing else.
     */
    private function absolutePath(string $path): string
    {
        return $this->root . '/' . $this->normalise($path);
    }

    /**
     * Make sure the bucket exists, because the first thing a wiki does with one is ask whether a file is in it, and a missing bucket answers that with an error rather than a "no".
     */
    public function createBucketIfMissing(): bool
    {
        $settings = $this->remote;
        if ($settings === null) {
            return false;
        }
        $client = $this->s3Client($settings);
        if ($client->bucketExists(['Bucket' => $settings->bucket])->isSuccess()) {
            return false;
        }
        $client->createBucket(['Bucket' => $settings->bucket]);

        return true;
    }

    /** The home this path's tier has: the bucket when it is configured for that tier, this disk otherwise. */
    private function filesystem(string $path): Filesystem
    {
        return $this->isRemote($path) ? $this->remoteFilesystem() : $this->localFilesystem();
    }

    private function localFilesystem(): Filesystem
    {
        if ($this->localFilesystem === null) {
            $this->localFilesystem = new Filesystem(new LocalFilesystemAdapter($this->root));
        }

        return $this->localFilesystem;
    }

    private function remoteFilesystem(): Filesystem
    {
        if ($this->remoteFilesystem === null) {
            $settings = $this->remote;
            if ($settings === null) {
                throw new StorageException('No bucket is configured.');
            }
            $this->remoteFilesystem = new Filesystem(new AsyncAwsS3Adapter(
                $this->s3Client($settings),
                $settings->bucket,
                $settings->prefix
            ));
        }

        return $this->remoteFilesystem;
    }

    private function s3Client(S3Settings $settings): S3Client
    {
        $configuration = [
            'region' => $settings->region,
            'accessKeyId' => $settings->key,
            'accessKeySecret' => $settings->secret,
        ];
        if ($settings->endpoint !== '') {
            $configuration['endpoint'] = $settings->endpoint;
        }
        if ($settings->pathStyle) {
            $configuration['pathStyleEndpoint'] = 'true';
        }

        return new S3Client($configuration);
    }

    /**
     * Public bytes are served straight from the bucket, so they are written readable; everything else is not.
     *
     * @return array<string, string>
     */
    private function writeOptions(string $path): array
    {
        return $this->isRemote($path) && $this->tierOf($path) === self::PUBLIC_TIER
            ? ['visibility' => Visibility::PUBLIC]
            : [];
    }

    /**
     * Asking whether something is there, where a bucket that does not exist is a truthful "no" rather than a fatal: it is what lets a wiki configured for a bucket nobody has created yet still boot, and `storage:sync` create it.
     *
     * @param callable(): bool $question
     */
    private function asking(callable $question, string $path): bool
    {
        try {
            return $this->guard($question, $path);
        } catch (StorageException $exception) {
            if ($this->isMissingBucket($exception)) {
                return false;
            }

            throw $exception;
        }
    }

    /** Whether this failure is the bucket itself being absent, which deserves its own words. */
    private function isMissingBucket(\Throwable $exception): bool
    {
        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof NoSuchBucketException) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every path is checked for a tier before it is touched, and Flysystem's failures are reported as ours.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    private function guard(callable $operation, string $path): mixed
    {
        $this->tierOf($path);

        try {
            return $operation();
        } catch (FilesystemException $exception) {
            if ($this->remote !== null && $this->isMissingBucket($exception)) {
                throw new StorageException("The bucket '{$this->remote->bucket}' does not exist. Create it, or run ./yeswicli storage:sync.", 0, $exception);
            }

            throw new StorageException($exception->getMessage(), 0, $exception);
        }
    }

    private function matches(string $pattern, string $path): bool
    {
        if (str_ends_with($pattern, '/')) {
            return str_starts_with($path . '/', $pattern);
        }
        if (str_contains($pattern, '*')) {
            return fnmatch($pattern, $path);
        }

        return $path === $pattern;
    }

    /** An instance-relative path, said the one way: no leading slash, no `.`, no `..`. */
    private function normalise(string $path): string
    {
        $path = trim($path);
        if (str_starts_with($path, $this->root . '/')) {
            $path = substr($path, \strlen($this->root) + 1);
        }
        $path = ltrim($path, '/');
        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }
        if ($path === '' || str_contains($path, '../')) {
            throw new StorageException("'$path' is not an instance-relative path.");
        }

        return rtrim($path, '/');
    }
}
