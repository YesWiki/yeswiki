<?php

namespace YesWiki\Files\Entity;

use YesWiki\Core\YesWikiLoader;
use YesWiki\Files\Exception\StorageException;

/** Where an instance's Public and Protected bytes live when they do not live on this disk (ADR-0022). */
class S3Settings
{
    /** What `YESWIKI_STORAGE` accepts. */
    public const BACKENDS = ['local', 's3'];

    /** The tiers `YESWIKI_S3_TIERS` may name. */
    public const REMOTABLE_TIERS = ['public', 'protected'];

    /**
     * @param list<string> $tiers
     */
    public function __construct(
        public readonly string $bucket,
        public readonly string $region,
        public readonly string $endpoint,
        public readonly string $key,
        public readonly string $secret,
        public readonly string $prefix = '',
        public readonly bool $pathStyle = false,
        public readonly string $publicUrl = '',
        public readonly array $tiers = self::REMOTABLE_TIERS,
    ) {
    }

    public function withCredentials(string $key, string $secret): self
    {
        return new self(
            bucket: $this->bucket,
            region: $this->region,
            endpoint: $this->endpoint,
            key: $key,
            secret: $secret,
            prefix: $this->prefix,
            pathStyle: $this->pathStyle,
            publicUrl: $this->publicUrl,
            tiers: $this->tiers,
        );
    }

    /** The storage one instance is configured for, read from the private/.env that belongs to it. */
    public static function forInstance(?string $instanceDir = null): ?self
    {
        $stated = [];
        foreach (YesWikiLoader::envFileValues($instanceDir) as $name => $value) {
            if (str_starts_with($name, 'YESWIKI_')) {
                $stated[strtolower(substr($name, \strlen('YESWIKI_')))] = $value;
            }
        }

        return self::fromConfiguration($stated);
    }

    /**
     * What the environment asks for, or null for the local disk -- and a refusal, naming what is wrong, for anything in between.
     */
    public static function fromEnvironment(): ?self
    {
        return self::fromConfiguration([]);
    }

    /**
     * The storage this wiki is configured for: its own configuration first, the environment after.
     *
     * @param array<string, mixed> $config
     */
    public static function fromConfiguration(array $config): ?self
    {
        $backend = strtolower(trim((string)(self::stated($config, 'storage') ?: 'local')));
        if (!in_array($backend, self::BACKENDS, true)) {
            throw new StorageException(self::named('storage') . " is '$backend', which is neither of " . implode(' nor ', self::BACKENDS) . '.');
        }
        if ($backend === 'local') {
            return null;
        }

        $tiers = self::tiers($config);

        $missing = [];
        $required = ['s3_bucket', 's3_key', 's3_secret'];
        if (in_array('public', $tiers, true)) {
            $required[] = 's3_public_url';
        }
        foreach ($required as $key) {
            if (trim(self::stated($config, $key)) === '') {
                $missing[] = self::named($key);
            }
        }
        if ($missing !== []) {
            throw new StorageException(self::named('storage') . ' is s3 but ' . implode(', ', $missing) . ' is not set.');
        }

        return new self(
            bucket: trim(self::stated($config, 's3_bucket')),
            region: trim(self::stated($config, 's3_region')) ?: 'us-east-1',
            endpoint: trim(self::stated($config, 's3_endpoint')),
            key: trim(self::stated($config, 's3_key')),
            secret: trim(self::stated($config, 's3_secret')),
            prefix: trim(self::stated($config, 's3_prefix'), " \t\n\r\0\x0B/"),
            pathStyle: filter_var(self::stated($config, 's3_path_style'), FILTER_VALIDATE_BOOLEAN),
            publicUrl: rtrim(trim(self::stated($config, 's3_public_url')), '/'),
            tiers: $tiers,
        );
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<string>
     */
    private static function tiers(array $config): array
    {
        $asked = trim(self::stated($config, 's3_tiers'));
        if ($asked === '') {
            return self::REMOTABLE_TIERS;
        }

        $tiers = [];
        foreach (explode(',', strtolower($asked)) as $tier) {
            $tier = trim($tier);
            if ($tier === '') {
                continue;
            }
            if (!in_array($tier, self::REMOTABLE_TIERS, true)) {
                throw new StorageException(self::named('s3_tiers') . " names '$tier', which cannot live in object storage: " . 'a path like private/yeswiki.db is read as a real file by something other than YesWiki.');
            }
            $tiers[] = $tier;
        }

        return $tiers === [] ? self::REMOTABLE_TIERS : $tiers;
    }

    /**
     * A setting as this wiki states it, or as the environment does.
     *
     * @param array<string, mixed> $config
     */
    private static function stated(array $config, string $key): string
    {
        $own = $config[$key] ?? null;
        if (is_scalar($own) && trim((string)$own) !== '') {
            return (string)$own;
        }

        return (string)getenv(self::environmentName($key));
    }

    private static function named(string $key): string
    {
        return $key . ' (or ' . self::environmentName($key) . ')';
    }

    private static function environmentName(string $key): string
    {
        return 'YESWIKI_' . strtoupper($key);
    }
}
