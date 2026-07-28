<?php

use YesWiki\Content\Service\FormManager;
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

        // Modify bn_ce_i18n column - different syntax per database
        $driver = $this->dbService->getDriver();
        switch ($driver) {
            case 'sqlite':
                // SQLite has limited ALTER TABLE support, column type changes require table recreation
                // For now, we skip this as SQLite is flexible with types
                break;
            case 'pgsql':
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ALTER COLUMN bn_ce_i18n TYPE VARCHAR(5), ALTER COLUMN bn_ce_i18n SET DEFAULT '', ALTER COLUMN bn_ce_i18n SET NOT NULL");
                break;
            case 'mysql':
            default:
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} MODIFY COLUMN bn_ce_i18n VARCHAR(5) NOT NULL DEFAULT ''");
                break;
        }

        // add semantic bazar fields
        if (!$this->dbService->columnExists('nature', 'bn_sem_context')) {
            $this->addSemanticColumns();
        }

        // add only_one_entry fields
        $formManager = $this->wiki->services->get(FormManager::class);
        if (!$formManager->isAvailableOnlyOneEntryOption()) {
            $this->addOnlyOneEntryColumn();
        }
        if (!$formManager->isAvailableOnlyOneEntryMessage()) {
            $this->addOnlyOneEntryMessageColumn();
        }
    }

    private function addSemanticColumns(): void
    {
        $driver = $this->dbService->getDriver();
        switch ($driver) {
            case 'sqlite':
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_sem_context TEXT");
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_sem_type VARCHAR(255) DEFAULT NULL");
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_sem_use_template INTEGER NOT NULL DEFAULT 1");
                break;
            case 'pgsql':
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_sem_context TEXT");
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_sem_type VARCHAR(255) DEFAULT NULL");
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_sem_use_template SMALLINT NOT NULL DEFAULT 1");
                break;
            case 'mysql':
            default:
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_sem_context text COLLATE utf8mb4_unicode_ci AFTER bn_condition");
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_sem_type varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER bn_sem_context");
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN bn_sem_use_template tinyint(1) NOT NULL DEFAULT 1 AFTER bn_sem_type");
                break;
        }
    }

    private function addOnlyOneEntryColumn(): void
    {
        $driver = $this->dbService->getDriver();
        $quotedCol = $this->dbService->quoteIdentifier('bn_only_one_entry');
        switch ($driver) {
            case 'sqlite':
                // SQLite doesn't have ENUM, use TEXT with CHECK constraint
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN $quotedCol TEXT NOT NULL DEFAULT 'N' CHECK($quotedCol IN ('Y', 'N'))");
                break;
            case 'pgsql':
                // PostgreSQL: use VARCHAR with CHECK constraint
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN $quotedCol VARCHAR(1) NOT NULL DEFAULT 'N' CHECK($quotedCol IN ('Y', 'N'))");
                break;
            case 'mysql':
            default:
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN $quotedCol enum('Y','N') NOT NULL DEFAULT 'N' COLLATE utf8mb4_unicode_ci");
                break;
        }
    }

    private function addOnlyOneEntryMessageColumn(): void
    {
        $driver = $this->dbService->getDriver();
        $quotedCol = $this->dbService->quoteIdentifier('bn_only_one_entry_message');
        switch ($driver) {
            case 'sqlite':
            case 'pgsql':
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN $quotedCol TEXT DEFAULT NULL");
                break;
            case 'mysql':
            default:
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('nature')} ADD COLUMN $quotedCol text DEFAULT NULL COLLATE utf8mb4_unicode_ci");
                break;
        }
    }
}
