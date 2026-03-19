<?php

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

class AddActivityPubCompatibility extends YesWikiMigration
{
    public function run()
    {
        if (!$this->dbService->columnExists('nature', 'bn_activitypub_enable')) {
            $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_activitypub_enable tinyint(1) NOT NULL DEFAULT 0 AFTER bn_sem_use_template");
            $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_activitypub_username varchar(255) DEFAULT NULL AFTER bn_activitypub_enable");
            $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_activitypub_private_key text DEFAULT NULL AFTER bn_activitypub_username");
            $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_activitypub_public_key text DEFAULT NULL AFTER bn_activitypub_private_key");          
        }
    }
}
