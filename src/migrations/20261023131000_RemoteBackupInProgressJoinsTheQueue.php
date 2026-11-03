<?php

use YesWiki\Admin\Service\RemoteBackupService;
use YesWiki\Core\YesWikiMigration;

/** Hands the queue the next step of a fetch from another wiki left under way, which used to move only with the admin's open tab. */
class RemoteBackupInProgressJoinsTheQueue extends YesWikiMigration
{
    public function run()
    {
        if (!$this->getService(RemoteBackupService::class)->queueNextStep()) {
            return;
        }

        $this->say('The backup being fetched from another wiki was handed to the background queue, which takes it to the end without the backups page open.');
    }
}
