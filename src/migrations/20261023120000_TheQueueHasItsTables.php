<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueSchema;

/** The background queue (messenger / scheduler) keeps its messages, its scheduled tasks and its checkpoint in one table of the wiki's own database, and its runs and passes in the Journal's `queue` channel. */
class TheQueueHasItsTables extends YesWikiMigration
{
    public function run()
    {
        $schema = $this->getService(QueueSchema::class);

        if (!$schema->exists()) {
            $schema->create();
        }

        $this->getService(JournalSchema::class)->ensureQueueIndex();
    }
}
