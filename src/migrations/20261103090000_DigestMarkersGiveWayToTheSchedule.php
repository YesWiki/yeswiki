<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Files\Service\Storage;

/**
 * Deletes `private/digests/`, the files whose modification time said when each digest last went out: nothing reads them now.
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
