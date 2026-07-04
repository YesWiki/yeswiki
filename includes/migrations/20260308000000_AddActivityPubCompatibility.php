<?php

use YesWiki\Core\YesWikiMigration;

class AddActivityPubCompatibility extends YesWikiMigration
{
    public function run()
    {
        if (!$this->dbService->columnExists('nature', 'bn_activitypub_enable')) {
            $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_activitypub_enable tinyint(1) NOT NULL DEFAULT 0");
            $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_activitypub_username varchar(255) DEFAULT NULL");
            $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_activitypub_private_key text DEFAULT NULL");
            $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_activitypub_public_key text DEFAULT NULL");
        }

        // Replace bn_sem_context, bn_sem_type, bn_sem_use_template with Twig-based templates
        if (!$this->dbService->columnExists('nature', 'bn_sem_template')) {
            $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_sem_template text DEFAULT NULL");
            $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_sem_reverse_template text DEFAULT NULL");
        }
        $this->dbService->dropColumn('nature', 'bn_sem_context');
        $this->dbService->dropColumn('nature', 'bn_sem_type');
        $this->dbService->dropColumn('nature', 'bn_sem_use_template');
    }
}
