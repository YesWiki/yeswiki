<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

/** Removes `archive.call_archive_async`: backups and restores go through the queue, whose mode decides how they run. */
class ArchivesNoLongerChooseHowTheyRun extends YesWikiMigration
{
    private const SETTING = 'call_archive_async';

    public function run()
    {
        $file = ConfigurationFileProvider::getConfigFileFromEnv();
        $configuration = $this->getService(ConfigurationService::class)->getConfiguration($file);
        $configuration->load();

        $archive = $configuration['archive'] ?? null;
        if (!is_array($archive) || !array_key_exists(self::SETTING, $archive)) {
            return;
        }

        $was = var_export($archive[self::SETTING], true);
        unset($archive[self::SETTING]);
        $configuration['archive'] = $archive;
        if (!$configuration->write()) {
            throw new RuntimeException("could not write {$file}: archive.call_archive_async is still there");
        }

        $this->say("archive.call_archive_async ({$was}) was removed: backups and restores now go through the background queue, and run in the admin's own request unless a consumer of its own (worker) takes them.");
    }
}
