<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Federation\Service\SeenSignatures;
use YesWiki\Files\Service\Storage;

/** ActivityPub replay protection keeps the signatures it has seen in a table whose unique key makes the check atomic, instead of one file per signature. */
class SeenSignaturesMoveToTheDatabase extends YesWikiMigration
{
    public function run()
    {
        $this->getService(SeenSignatures::class)->create();

        $attachConfig = $this->params->has('attach_config') ? $this->params->get('attach_config') : [];
        $cachePath = is_array($attachConfig) && !empty($attachConfig['cache_path']) ? (string)$attachConfig['cache_path'] : 'cache';
        try {
            $this->getService(Storage::class)->deleteDirectory(rtrim($cachePath, '/') . '/activitypub-signatures');
        } catch (Throwable $nothingToClean) {
        }
    }
}
