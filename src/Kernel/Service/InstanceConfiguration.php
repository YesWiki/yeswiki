<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Files\Service\LocalFiles;

/** Another wiki's settings, opened from its folder: the host's in its private/.env, the rest in its database, so a farm reads and writes them the way that wiki does (replicas 20). */
#[Exclude]
final class InstanceConfiguration
{
    private ?WikiSettings $settings = null;

    public function __construct(public readonly string $instanceDir, private readonly LocalFiles $files = new LocalFiles())
    {
    }

    /** Whether that folder holds a wiki: its private/.env names a database, or a configuration file from before names its address. */
    public function exists(): bool
    {
        return InstanceSetup::statedDatabaseSettings(YesWikiLoader::envFileValues($this->instanceDir)) || $this->legacy()->exists() && InstanceSetup::fileConfigures($this->legacy()->path);
    }

    /** @return array<string, mixed> the host's settings: a configuration file from before, private/.env over it */
    public function hostSettings(): array
    {
        $host = EnvironmentConfiguration::applyEnvFile($this->legacy()->hostSettings(), YesWikiLoader::envFileValues($this->instanceDir));
        if (($host['db_driver'] ?? '') === 'sqlite' && !str_starts_with((string)($host['db_database'] ?? ''), '/')) {
            $host['db_database'] = $this->instanceDir . '/' . ((string)($host['db_database'] ?? '') ?: 'private/yeswiki.db');
        }

        return $host;
    }

    /** @return array<string, mixed> every setting: the host's, the configuration file's own, then what that wiki's database stores */
    public function load(): array
    {
        return $this->database()->over($this->legacy()->wikiSettings() + $this->hostSettings(), false);
    }

    /**
     * Sets those keys, the host's in private/.env and the rest in the database; $unset takes keys away; false when private/.env cannot be written.
     *
     * @param array<string, mixed> $set
     * @param list<string>         $unset
     */
    public function store(array $set, array $unset = []): bool
    {
        [$host, $wiki] = self::split($set + array_fill_keys(array_filter($unset, [SettingKinds::class, 'isOperator']), null));
        $this->database()->store($wiki, array_values(array_filter($unset, static fn (string $key): bool => !SettingKinds::isOperator($key))));
        $fromTheFile = $this->legacy()->wikiSettings();
        $removed = array_values(array_filter($unset, static fn (string $key): bool => !SettingKinds::isOperator($key) && array_key_exists($key, $fromTheFile)));
        if ($removed !== []) {
            $this->database()->store(array_fill_keys($removed, null));
        }

        return (new EnvFile($this->files, $this->instanceDir))->store($host);
    }

    /**
     * Where each setting goes, the one rule a wiki and a farm both follow: the host's to private/.env, the rest to the database.
     *
     * @param array<string, mixed> $settings
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} the host's, then the wiki's
     */
    public static function split(array $settings): array
    {
        $host = array_filter($settings, [SettingKinds::class, 'isOperator'], ARRAY_FILTER_USE_KEY);

        return [$host, array_diff_key($settings, $host)];
    }

    /** @return array<string, mixed> what that wiki's database stores, a null value taking a key away */
    public function storedSettings(): array
    {
        return $this->database()->all();
    }

    /** That wiki's stored settings, through a connection tried first, since a refused one would end the request. */
    private function database(): WikiSettings
    {
        if ($this->settings === null) {
            $db = new DbService(new ParameterBag($this->hostSettings() + ['debug' => false, 'base_url' => '', 'table_prefix' => 'yeswiki_', 'db_driver' => 'mysql', 'db_host' => 'localhost', 'db_port' => '', 'db_database' => '', 'db_user' => '', 'db_password' => '']), false);
            $refused = $db->ping();
            if ($refused !== null) {
                throw new \RuntimeException('The database of ' . $this->instanceDir . ' does not answer: ' . $refused);
            }
            $this->settings = new WikiSettings($db);
        }

        return $this->settings;
    }

    private function legacy(): LegacyConfigurationFile
    {
        foreach (['yeswiki.config.php', 'wakka.config.php'] as $name) {
            if ($this->files->isFile($this->instanceDir . '/' . $name)) {
                return new LegacyConfigurationFile($this->instanceDir . '/' . $name);
            }
        }

        return new LegacyConfigurationFile($this->instanceDir . '/yeswiki.config.php');
    }
}
