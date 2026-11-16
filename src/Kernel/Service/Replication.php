<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Files\Entity\S3Settings;
use YesWiki\Files\Service\Storage;

/** Whether the operator runs this wiki on several replicas at once (ADR-0031), which `YESWIKI_REPLICATED=1` says. */
final class Replication
{
    public const ENV = 'YESWIKI_REPLICATED';

    public const KEY_VARIABLES = [SignedTokens::KEY_ENV, 'YESWIKI_BOT_GUARD_KEY'];

    public static function isOn(): bool
    {
        return filter_var(getenv(self::ENV), FILTER_VALIDATE_BOOLEAN);
    }

    /** @param array<string, mixed> $config why it cannot serve as a replica, null when it can or is not replicated: what the boot throws and `/readyz` answers 503 for */
    public static function refusal(array $config): ?string
    {
        if (!self::isOn()) {
            return null;
        }
        if (empty($config['base_url'])) {
            return self::ENV . ' is set and base_url is not: a replica must not learn its address from whoever reaches it first. State base_url.';
        }
        if (($config['db_driver'] ?? '') === 'sqlite') {
            return self::ENV . ' is set and the database is SQLite, a file on one machine: replicas need MySQL, MariaDB or PostgreSQL.';
        }
        $unshared = self::unsharedKeys(S3Settings::forInstance(YESWIKI_INSTANCE_DIR));
        if ($unshared !== []) {
            return self::ENV . ' is set, the protected storage is this machine\'s disk and ' . implode(' and ', $unshared) . (count($unshared) > 1 ? ' are' : ' is') . ' not set: each replica would make its own key, and see whoever another one signed in as a stranger. Give every replica the same values (openssl rand -hex 32), or keep the protected tier on S3.';
        }

        return null;
    }

    /** @return list<string> the key variables a replica still needs, none when the protected tier is on S3, where every replica reads the first key written */
    public static function unsharedKeys(?S3Settings $storage): array
    {
        if ($storage !== null && in_array(Storage::PROTECTED_TIER, $storage->tiers, true)) {
            return [];
        }

        return array_values(array_filter(self::KEY_VARIABLES, static fn (string $name): bool => trim((string)getenv($name)) === ''));
    }
}
