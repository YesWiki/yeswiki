<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Files\Service\Storage;

/** Deletes the old seen-signature files. */
class SeenSignaturesMoveToTheDatabase extends YesWikiMigration
{
    public function run()
    {
        $attachConfig = $this->params->has('attach_config') ? $this->params->get('attach_config') : [];
        $cachePath = is_array($attachConfig) && !empty($attachConfig['cache_path']) ? (string)$attachConfig['cache_path'] : 'cache';
        try {
            $this->getService(Storage::class)->deleteDirectory(rtrim($cachePath, '/') . '/activitypub-signatures');
        } catch (Throwable $nothingToClean) {
        }
    }
}
