<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Entity\ConfigurationFile;

/** Whether a wiki folder is configured: its database settings come from the environment, its private/.env, or a configuration file from before (replicas 20). */
#[Exclude]
final class InstanceSetup
{
    /** Whether that folder's wiki knows its database, so it is served rather than shown the installer. */
    public static function isConfigured(string $instanceDir, string $configFile): bool
    {
        $environment = array_filter(['DB_DRIVER' => getenv('DB_DRIVER'), 'DB_DATABASE' => getenv('DB_DATABASE')], 'is_string');
        if (self::statedDatabaseSettings($environment + YesWikiLoader::envFileValues($instanceDir))) {
            return true;
        }

        return self::fileConfigures($configFile);
    }

    /** @param array<string, string> $values whether these variables name a SQLite driver or a database */
    public static function statedDatabaseSettings(array $values): bool
    {
        foreach (['DB_DRIVER' => 'sqlite', 'DB_DATABASE' => null] as $name => $expected) {
            $value = trim($values[$name] ?? '');
            if ($value !== '' && ($expected === null || strtolower($value) === $expected)) {
                return true;
            }
        }

        return false;
    }

    /** Whether a configuration file from before this ticket names the wiki's address. */
    public static function fileConfigures(string $configFile): bool
    {
        if (!(new LocalFiles())->isFile($configFile)) {
            return false;
        }
        $configuration = new ConfigurationFile($configFile);
        $configuration->load();

        return !empty($configuration['base_url']);
    }
}
