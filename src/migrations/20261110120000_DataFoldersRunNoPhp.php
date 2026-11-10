<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DataFolderGuard;

/** Adds the rules that keep Apache from running PHP to the .htaccess of cache/, custom/ and files/, keeping whatever each one already says. */
class DataFoldersRunNoPhp extends YesWikiMigration
{
    public function run()
    {
        foreach (DataFolderGuard::guard(YESWIKI_INSTANCE_DIR) as $folder) {
            $this->say("{$folder}/.htaccess now refuses to run PHP");
        }
    }
}
