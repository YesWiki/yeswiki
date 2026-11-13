<?php

use YesWiki\Files\Service\LocalFiles;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\EnvFile;
use YesWiki\Kernel\Service\LegacyConfigurationFile;
use YesWiki\Kernel\Service\WikiSettings;

/** The configuration file goes: what the wiki decides moves to its database, what its host does to private/.env, and the file is renamed `.migrated`; where that cannot be written, the file stays for the host's settings (replicas 20). */
class TheConfigurationFileMovesOut extends YesWikiMigration
{
    public function run()
    {
        $file = new LegacyConfigurationFile(ConfigurationFileProvider::getConfigFileFromEnv());
        if (!$file->exists()) {
            return;
        }

        $settings = $this->getService(WikiSettings::class);
        $copied = array_diff_key($file->wikiSettings(), $settings->all());
        $settings->store($copied);
        $this->say(count($copied) . ' setting(s) of ' . basename($file->path) . ' copied to the database');

        $files = new LocalFiles();
        if ($files->realPath(dirname($file->path)) !== $files->realPath(YESWIKI_INSTANCE_DIR)) {
            $this->say('YESWIKI_CONFIG_FILE names ' . $file->path . ', outside the wiki: it stays where the operator put it, read for the host\'s settings only');

            return;
        }
        $host = $file->hostSettingsToMove(YESWIKI_INSTANCE_DIR);
        $envFile = $this->getService(EnvFile::class);
        $aside = $file->path . '.migrated' . ($files->exists($file->path . '.migrated') ? '.' . date('Ymd-His') : '');
        if ($envFile->store($host) && $files->rename($file->path, $aside)) {
            $this->say(count($host) . ' host setting(s) written to ' . $envFile->path() . ', ' . basename($file->path) . ' renamed ' . basename($aside));

            return;
        }
        $this->say('WARNING: ' . $envFile->path() . ' or ' . $file->path . ' cannot be written, so ' . basename($file->path) . ' stays, read for the host\'s settings only. Run `yeswiki config:export-env` and put what it prints in ' . $envFile->path() . ', then remove ' . basename($file->path) . '.');
    }
}
