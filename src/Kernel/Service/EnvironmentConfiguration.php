<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Core\YesWikiLoader;

/** Environment-variable overrides for the YesWiki configuration. */
class EnvironmentConfiguration
{
    /**
     * Config keys whose UPPER(key) variable is honored from the real environment, and that may be created even when absent from the current configuration (some have their defaults in extension config.yaml files, loaded after Init::getConfig()).
     */
    public const KNOWN_KEYS = [
        'db_driver',
        'db_host',
        'db_database',
        'db_user',
        'db_password',
        'db_port',
        'table_prefix',
        'base_url',
        'rewrite_mode',
        'debug',
        'default_language',
        'timezone',
        'root_page',
        'yeswiki_name',
        'meta_keywords',
        'meta_description',
        'allow_raw_html',
        'contact_mail_func',
        'contact_smtp_host',
        'contact_smtp_port',
        'contact_smtp_user',
        'contact_smtp_pass',
        'contact_smtp_secure',
        'contact_reply_to',
        'contact_debug',
    ];

    /**
     * Variables that are environment-only and must never leak into the configuration (ADMIN_* hold plain credentials for the automated installation, YESWIKI_S3_* the bucket's, and both would otherwise end up in container parameters and config archives).
     */
    public const NOT_CONFIG = [
        'YESWIKI_CONFIG_FILE',
        'ASYNC_PHP_BINARY',
        'ADMIN_NAME',
        'ADMIN_PASSWORD',
        'ADMIN_EMAIL',

        'YESWIKI_STORAGE',
        'YESWIKI_S3_BUCKET',
        'YESWIKI_S3_REGION',
        'YESWIKI_S3_ENDPOINT',
        'YESWIKI_S3_KEY',
        'YESWIKI_S3_SECRET',
        'YESWIKI_S3_PREFIX',
        'YESWIKI_S3_PATH_STYLE',
        'YESWIKI_S3_PUBLIC_URL',
        'YESWIKI_S3_TIERS',
    ];

    /**
     * Apply the environment overrides on a configuration array.
     *
     * @param array<string, mixed>       $config     configuration to override
     * @param array<string, string>|null $fileValues private/.env content override, for tests
     *                                               (default: YesWikiLoader::envFileValues())
     *
     * @return array<string, mixed>
     */
    public static function apply(array $config, ?array $fileValues = null): array
    {
        $fileValues = $fileValues ?? YesWikiLoader::envFileValues();

        $keysByUpperName = [];
        foreach (array_keys($config) as $key) {
            $keysByUpperName[strtoupper($key)] = $key;
        }

        foreach ($fileValues as $name => $value) {
            if (in_array($name, self::NOT_CONFIG, true)) {
                continue;
            }
            $key = $keysByUpperName[strtoupper($name)] ?? strtolower($name);
            self::override($config, $key, $value);
        }

        foreach (self::knownEnvNames() as $name => $key) {
            $value = getenv($name);
            if ($value !== false) {
                self::override($config, $key, $value);
            }
        }

        return $config;
    }

    /**
     * The environment variable names honored beyond private/.env, as an envName => configKey map.
     *
     * @return array<string,string>
     */
    public static function knownEnvNames(): array
    {
        $names = [];
        foreach (self::KNOWN_KEYS as $key) {
            $names[strtoupper($key)] = $key;
        }

        return $names;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function override(array &$config, string $key, string $value): void
    {
        if (isset($config[$key]) && is_array($config[$key])) {
            return;
        }
        $config[$key] = self::cast($value, $config[$key] ?? null);
    }

    /**
     * Env values are strings: give the override the type of the value it replaces.
     *
     * @return string|int|float|bool
     */
    private static function cast(string $value, mixed $currentValue)
    {
        if (is_bool($currentValue)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
        if (is_int($currentValue)) {
            return (int)$value;
        }
        if (is_float($currentValue)) {
            return (float)$value;
        }

        return $value;
    }
}
