<?php

use YesWiki\Admin\Service\RemoteBackupService;
use YesWiki\Core\YesWikiMigration;

/**
 * A fetch from another wiki used to move one step per request of the admin's open tab: one left under way when the wiki is upgraded has nothing to move it any more, unless the queue is handed its next step.
 */
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
