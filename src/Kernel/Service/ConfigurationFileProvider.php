<?php

namespace YesWiki\Kernel\Service;

/**
 * Provide configuration file from environment.
 */
class ConfigurationFileProvider
{
    public static function getConfigFileFromEnv(): string
    {
        $configFile = getenv('YESWIKI_CONFIG_FILE');
        if ($configFile !== false) {
            return $configFile;
        }

        // instances installed before the wakka.config.php -> yeswiki.config.php rename
        // keep working until an update renames their file
        if (!is_file('yeswiki.config.php') && is_file('wakka.config.php')) {
            return 'wakka.config.php';
        }

        return 'yeswiki.config.php';
    }
}
