<?php

use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

/** `archive[privatePath]` is gone: a wiki's backups are in `private/backups`, which is where every wiki already kept them. */
class BackupsHaveOnePlace extends YesWikiMigration
{
    public function run()
    {
        $file = ConfigurationFileProvider::getConfigFileFromEnv();
        $configuration = $this->getService(ConfigurationService::class)->getConfiguration($file);
        $configuration->load();

        $archive = (array)($configuration[ArchiveService::PARAMS_KEY_IN_WAKKA] ?? []);
        if (!array_key_exists('privatePath', $archive)) {
            return;
        }

        $was = (string)$archive['privatePath'];
        unset($archive['privatePath']);
        $configuration[ArchiveService::PARAMS_KEY_IN_WAKKA] = $archive;
        if (!$configuration->write()) {
            throw new RuntimeException("could not write {$file}: archive[privatePath] is still there");
        }

        $elsewhere = $was !== '' && rtrim($was, '/') !== ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP;
        $storage = $this->getService(Storage::class);

        $this->say(
            $elsewhere
                ? "archive[privatePath] was '{$was}' and is no longer a setting: new backups go to "
                    . ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . ', which is also what a bucket receives when'
                    . ' this wiki is configured for one. Whatever is already in the old folder was left there;'
                    . ' move it into ' . ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . ' if you want it listed.'
                : 'archive[privatePath] is no longer a setting. Backups keep going to '
                    . ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . ', where this wiki already put them'
                    . ($storage->isRemote(ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP) ? ', and now to the bucket.' : '.')
        );
    }
}
