<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\LanguageService;

/** `default_language: auto` becomes a real language, and what `auto` meant becomes settings. */
class ADefaultLanguageIsALanguage extends YesWikiMigration
{
    private const FALLBACK = 'fr';

    public function run()
    {
        $file = ConfigurationFileProvider::getConfigFileFromEnv();
        $configuration = $this->getService(ConfigurationService::class)->getConfiguration($file);
        $configuration->load();

        $current = trim((string)($configuration['default_language'] ?? ''));
        $installed = $this->getService(LanguageService::class)->installedLanguages();

        if ($current !== '' && $current !== 'auto' && $current !== 'browser') {
            return;
        }

        $default = in_array(self::FALLBACK, $installed, true)
            ? self::FALLBACK
            : (string)(reset($installed) ?: self::FALLBACK);

        $others = array_values(array_unique(array_merge(
            array_filter((array)($configuration['other_languages'] ?? []), 'is_string'),
            $installed
        )));
        $others = array_values(array_diff($others, [$default]));

        $configuration['default_language'] = $default;
        $configuration['other_languages'] = $others;
        if (!$configuration->write()) {
            throw new RuntimeException("could not write {$file}: default_language is still '{$current}'");
        }

        $this->getService(AdministrativeLogService::class)->log(
            'migration',
            "default_language was '{$current}', which names no language, and is now '{$default}'."
            . ' The languages this wiki offers were set to ' . implode(', ', $others)
            . ' so that a visitor still gets their own language when the wiki has it --'
            . ' which is what auto did. Both are on /?GererConfig.'
        );
    }
}
