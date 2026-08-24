<?php

namespace YesWiki\Files\Service;

use YesWiki\Files\Entity\S3Settings;
use YesWiki\Files\Exception\StorageException;

/** Creates the bucket a new wiki will own, and refuses one another wiki is already in. */
class BucketProvisioner
{
    /** @var list<string> */
    private array $done = [];

    /** @var list<string> */
    private array $warnings = [];

    /**
     * Bucket names are DNS labels: this is the subset every provider accepts.
     *
     * @see https://docs.aws.amazon.com/AmazonS3/latest/userguide/bucketnamingrules.html
     */
    public static function isName(string $bucket): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/', $bucket) === 1
            && !str_contains($bucket, '..')
            && !preg_match('/^\d+\.\d+\.\d+\.\d+$/', $bucket);
    }

    /**
     * What was created, for an operator who has to find it later.
     *
     * @return list<string>
     */
    public function done(): array
    {
        return $this->done;
    }

    /**
     * What is worth knowing but does not stop an install.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @param string               $adminKey a key that may create buckets, kept for this call only
     * @param array<string, mixed> $config   the wiki's own configuration, naming its bucket
     * @param bool                 $mayReuse whether a bucket that already holds objects is allowed
     *
     * @throws StorageException when the bucket cannot be made this wiki's own
     */
    public function provision(string $adminKey, string $adminSecret, string $root, array $config, bool $mayReuse = false): void
    {
        $this->done = [];
        $this->warnings = [];

        $settings = S3Settings::fromConfiguration($config);
        if ($settings === null) {
            return;
        }

        if (!self::isName($settings->bucket)) {
            throw new StorageException("'{$settings->bucket}' is not a name a bucket can have: lowercase letters, digits, dots and hyphens, 3 to 63 of them, starting and ending with a letter or a digit.");
        }

        $asAdmin = Storage::rootedAtWith($root, $adminKey !== '' ? $settings->withCredentials($adminKey, $adminSecret) : $settings);

        if ($asAdmin->createBucketIfMissing()) {
            $this->done[] = "bucket {$settings->bucket}";
        } else {
            $this->refuseASharedBucket($asAdmin, $settings, $mayReuse);
            $this->done[] = "bucket {$settings->bucket}, which was already there";
        }

        $asTheWiki = Storage::rootedAtWith($root, $settings);
        $asTheWiki->proveWritable();
        $this->done[] = 'a write and a read with this wiki\'s own key';

        $others = $asTheWiki->otherBucketsInReach();
        if ($others !== []) {
            $this->warnings[] = 'This wiki\'s key also reaches ' . implode(', ', \array_slice($others, 0, 5))
                . (\count($others) > 5 ? ' and ' . (\count($others) - 5) . ' more' : '')
                . '. A wiki should hold a key scoped to its own bucket, or a wiki that is compromised takes the others with it.';
        }
    }

    /**
     * Drop what a wiki kept in object storage.
     *
     * @param array<string, mixed> $config the wiki's own configuration, naming its bucket
     *
     * @throws StorageException when the bucket cannot be reached
     */
    public function destroy(string $adminKey, string $adminSecret, string $root, array $config): void
    {
        $this->done = [];
        $this->warnings = [];

        $settings = S3Settings::fromConfiguration($config);
        if ($settings === null) {
            return;
        }

        $storage = Storage::rootedAtWith($root, $adminKey !== '' ? $settings->withCredentials($adminKey, $adminSecret) : $settings);
        $gone = $storage->dropRemote();

        $this->done[] = $gone['objects'] . ' object(s) from ' . $settings->bucket
            . ($settings->prefix !== '' ? ' under ' . $settings->prefix . '/' : '');

        if ($gone['bucket']) {
            $this->done[] = 'bucket ' . $settings->bucket;
        } else {
            $this->warnings[] = $settings->bucket . ' was left standing because this wiki had a prefix in it, '
                . 'which means something else may be in there too.';
        }
    }

    private function refuseASharedBucket(Storage $storage, S3Settings $settings, bool $mayReuse): void
    {
        if ($mayReuse || !$storage->bucketHolds()) {
            return;
        }

        $where = $settings->prefix !== '' ? "{$settings->bucket} under {$settings->prefix}/" : $settings->bucket;

        throw new StorageException("$where already holds files, so something is using it. Give this wiki a bucket of its " . 'own, or an s3_prefix of its own, or pass --reuse-bucket if you are restoring a wiki into the bucket it already had.');
    }
}
