<?php

namespace YesWiki\Files\Entity;

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

    /**
     * What the environment asks for, or null for the local disk -- and a refusal, naming what is wrong, for anything in between.
     */
    public static function fromEnvironment(): ?self
    {
        $backend = strtolower(trim((string)(getenv('YESWIKI_STORAGE') ?: 'local')));
        if (!in_array($backend, self::BACKENDS, true)) {
            throw new StorageException("YESWIKI_STORAGE is '$backend', which is neither of " . implode(' nor ', self::BACKENDS) . '.');
        }
        if ($backend === 'local') {
            return null;
        }

        $tiers = self::tiersFromEnvironment();

        $missing = [];
        $required = ['YESWIKI_S3_BUCKET', 'YESWIKI_S3_KEY', 'YESWIKI_S3_SECRET'];
        if (in_array('public', $tiers, true)) {
            $required[] = 'YESWIKI_S3_PUBLIC_URL';
        }
        foreach ($required as $name) {
            if (trim((string)getenv($name)) === '') {
                $missing[] = $name;
            }
        }
        if ($missing !== []) {
            throw new StorageException('YESWIKI_STORAGE is s3 but ' . implode(', ', $missing) . ' is not set.');
        }

        return new self(
            bucket: trim((string)getenv('YESWIKI_S3_BUCKET')),
            region: trim((string)getenv('YESWIKI_S3_REGION')) ?: 'us-east-1',
            endpoint: trim((string)getenv('YESWIKI_S3_ENDPOINT')),
            key: trim((string)getenv('YESWIKI_S3_KEY')),
            secret: trim((string)getenv('YESWIKI_S3_SECRET')),
            prefix: trim((string)getenv('YESWIKI_S3_PREFIX'), " \t\n\r\0\x0B/"),
            pathStyle: filter_var(getenv('YESWIKI_S3_PATH_STYLE'), FILTER_VALIDATE_BOOLEAN),
            publicUrl: rtrim(trim((string)getenv('YESWIKI_S3_PUBLIC_URL')), '/'),
            tiers: $tiers,
        );
    }

    /**
     * @return list<string>
     */
    private static function tiersFromEnvironment(): array
    {
        $asked = trim((string)getenv('YESWIKI_S3_TIERS'));
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
                throw new StorageException("YESWIKI_S3_TIERS names '$tier', which cannot live in object storage: " . 'a path like private/yeswiki.db is read as a real file by something other than YesWiki.');
            }
            $tiers[] = $tier;
        }

        return $tiers === [] ? self::REMOTABLE_TIERS : $tiers;
    }
}
