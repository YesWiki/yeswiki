<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * The secrets of the configuration, compiled as `%env(yeswiki_secret:<key>)%` so that `cache/container/` holds their names only (replicas 10).
 * The runtime sets this service with the configuration it read for the request, before any parameter is read.
 */
final class ConfigurationSecrets implements EnvVarProcessorInterface
{
    public const PREFIX = 'yeswiki_secret';

    /** Sections of the configuration that never reach the compiled container: read at runtime only. */
    public const RUNTIME_ONLY = ['sso_providers'];

    public const KEYS = [
        'db_password',
        'contact_smtp_pass',
        'contact_dkim_private_key',
        'queue_consume_secret',
        'sync_secret',
        'herse_password',
        'password_for_editing',
    ];

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config = [])
    {
    }

    /** The placeholder a secret is compiled as. */
    public static function placeholder(string $key): string
    {
        return '%env(' . self::PREFIX . ':' . $key . ')%';
    }

    /**
     * That configuration with every secret it holds swapped for its placeholder.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public static function withPlaceholders(array $config): array
    {
        foreach (self::RUNTIME_ONLY as $key) {
            unset($config[$key]);
        }
        foreach (self::KEYS as $key) {
            if (isset($config[$key]) && is_scalar($config[$key]) && (string)$config[$key] !== '') {
                $config[$key] = self::placeholder($key);
            }
        }

        return $config;
    }

    public function getEnv(string $prefix, string $name, \Closure $getEnv): mixed
    {
        $value = $this->config[$name] ?? '';

        return is_scalar($value) ? (string)$value : '';
    }

    public static function getProvidedTypes(): array
    {
        return [self::PREFIX => 'string'];
    }
}
