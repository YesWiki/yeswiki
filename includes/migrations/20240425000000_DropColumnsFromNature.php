<?php

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

class DropColumnsFromNature extends YesWikiMigration
{
    public function run()
    {
        // drop old nature table fields
        $this->dbService->dropColumn('nature', 'bn_ce_id_menu');
        $this->dbService->dropColumn('nature', 'bn_commentaire');
        $this->dbService->dropColumn('nature', 'bn_appropriation');
        $this->dbService->dropColumn('nature', 'bn_image_titre');
        $this->dbService->dropColumn('nature', 'bn_image_logo');
        $this->dbService->dropColumn('nature', 'bn_couleur_calendrier');
        $this->dbService->dropColumn('nature', 'bn_picto_calendrier');
        $this->dbService->dropColumn('nature', 'bn_type_fiche');
        $this->dbService->dropColumn('nature', 'bn_label_class');
        $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} MODIFY COLUMN bn_ce_i18n VARCHAR(5) NOT NULL DEFAULT ''");

        // add semantic bazar fields
        if (!$this->dbService->columnExists('nature', 'bn_sem_context')) {
            $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_sem_context text COLLATE utf8mb4_unicode_ci AFTER bn_condition");
            $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_sem_type varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER bn_sem_context");
            $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_sem_use_template tinyint(1) NOT NULL DEFAULT 1 AFTER bn_sem_type");
        }

        // TODO: What is this??? seems sooo weird
        $formManager = $this->wiki->services->get(FormManager::class);
        if (!$formManager->isAvailableOnlyOneEntryOption()) {
            $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN `bn_only_one_entry` enum('Y','N') NOT NULL DEFAULT 'N' COLLATE utf8mb4_unicode_ci;");
        }
        if (!$formManager->isAvailableOnlyOneEntryMessage()) {
            $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN `bn_only_one_entry_message` text DEFAULT NULL COLLATE utf8mb4_unicode_ci;");
        }
    }
}
