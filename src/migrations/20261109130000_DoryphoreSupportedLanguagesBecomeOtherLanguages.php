<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

/** Doryphore's `supported_langs`, which lists the default language too, becomes `other_languages`, which does not. */
class DoryphoreSupportedLanguagesBecomeOtherLanguages extends YesWikiMigration
{
    public function run()
    {
        $file = ConfigurationFileProvider::getConfigFileFromEnv();
        $configuration = $this->getService(ConfigurationService::class)->getConfiguration($file);
        $configuration->load();

        if (!isset($configuration['supported_langs'])) {
            return;
        }
        $supported = $configuration['supported_langs'];
        if (is_string($supported)) {
            $supported = preg_split('/[\s,\[\]\'"]+/', $supported, -1, PREG_SPLIT_NO_EMPTY);
        }
        $default = trim((string)($configuration['default_language'] ?? ''));
        $others = array_values(array_unique(array_merge(
            array_filter((array)($configuration['other_languages'] ?? []), 'is_string'),
            array_filter((array)$supported, 'is_string')
        )));

        $configuration['other_languages'] = array_values(array_diff($others, [$default]));
        unset($configuration['supported_langs']);
        if (!$configuration->write()) {
            throw new RuntimeException("could not write {$file}: supported_langs is still set");
        }

        $this->say('supported_langs became other_languages: ' . implode(', ', $configuration['other_languages']) . '.');
    }
}
