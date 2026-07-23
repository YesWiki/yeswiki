<?php

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

class MigrateNatureToPages extends YesWikiMigration
{
    public function run()
    {
        // clean break, per this rewrite's decisions: forms move from the standalone
        // `nature` table into `pages`, typed via the same TYPE_URI-triple convention
        // EntryManager already uses for bazar entries (see FormManager::create(), which
        // this reuses so the migration and the ordinary "create a form" path can't drift
        // apart). Each form's stable numeric id (bn_id_nature) is preserved so entries
        // (id_typeannonce), default-image filenames, and ActivityPub actor URLs -- all
        // keyed off that id, never the tag -- keep resolving unchanged.
        if (!$this->dbService->columnExists('nature', 'bn_id_nature')) {
            return;
        }

        $formManager = $this->getService(FormManager::class);
        $rows = $this->dbService->loadAll(
            "SELECT * FROM {$this->dbService->prefixTable('nature')} ORDER BY bn_id_nature ASC"
        );

        foreach ($rows as $row) {
            if ($formManager->getOne($row['bn_id_nature'])) {
                // already migrated (e.g. migration re-run against a farm instance that
                // was already converted) -- don't duplicate
                continue;
            }
            $formManager->create($row);
        }
    }
}
