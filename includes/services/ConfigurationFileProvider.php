<?php

namespace YesWiki\Core\Service;

/**
 * Provide configuration file from environment.
 */
class ConfigurationFileProvider
{
    public static function getWakkaConfigFileFromEnvironement(): string
    {
        $wakkaConfigFile = getenv('WAKKA_CONFIG_FILE');
        if ($wakkaConfigFile === false) {
            return 'wakka.config.php';
        }

        return $wakkaConfigFile;
    }
}
