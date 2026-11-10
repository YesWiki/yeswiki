<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\Queue\QueueSchema;

/** The background queue (messenger / scheduler) keeps its messages in one table of the wiki's own database, and its runs, passes and scheduled tasks in another. */
class TheQueueHasItsTables extends YesWikiMigration
{
    public function run()
    {
        $schema = $this->getService(QueueSchema::class);

        if (!$schema->exists()) {
            $schema->create();
        }
    }
}
