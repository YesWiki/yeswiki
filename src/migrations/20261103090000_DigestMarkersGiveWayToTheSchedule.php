<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Files\Service\Storage;

/**
 * Deletes `private/digests/`: the files whose modification time said when each digest last went out, or was last tried.
 *
 * The digests go out on a cron now, at 7 a.m. in the wiki's timezone, and what has gone out is the
 * queue's checkpoint (ContactSchedule). Nothing reads the markers any more.
 */
class DigestMarkersGiveWayToTheSchedule extends YesWikiMigration
{
    public function run()
    {
        try {
            $this->getService(Storage::class)->deleteDirectory('private/digests');
        } catch (Throwable $nothingToClean) {
        }
    }
}
