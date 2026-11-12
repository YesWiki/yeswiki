<?php

/**
 * Writes the `sso_providers` section of the wiki under test, the way the administration screen does.
 * Run from the wiki's folder: php set-sso-providers.php '<the section as JSON>'. `null` removes the section, which leaves local accounts alone.
 * The web server's opcode cache tells a new file by its modification time, so each write is given a later one than the file had.
 */

use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

$section = json_decode($argv[1] ?? '', true, 512, JSON_THROW_ON_ERROR);
$service = new ConfigurationService();
$file = ConfigurationFileProvider::getConfigFileFromEnv();
$before = (int)@filemtime($file);
$config = $service->getConfiguration($file);
$config->load();
if ($section === null) {
    unset($config['sso_providers']);
} else {
    $config['sso_providers'] = $section;
}
if (!$service->write($config)) {
    fwrite(STDERR, "the configuration file could not be written\n");
    exit(1);
}
clearstatcache();
if ((int)filemtime($file) <= $before) {
    touch($file, $before + 1);
}
