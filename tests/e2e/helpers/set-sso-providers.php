<?php

/** Writes the `sso_providers` section of the wiki under test, the way the administration screen does. */

use YesWiki\Core\YesWikiLoader;
use YesWiki\Identity\Service\SignIn\ProviderRegistry;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\WikiSettings;

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
YesWikiLoader::getWiki(true)->services->get(WikiSettings::class)->store([], [ProviderRegistry::SECTION]);
