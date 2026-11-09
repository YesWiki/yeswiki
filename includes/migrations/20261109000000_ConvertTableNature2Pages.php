<?php

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

/** Stores every form of the `nature` table as a JSON form page, keeping its id; entries are left as they are. */
class ConvertTableNature2Pages extends YesWikiMigration
{
    public function run()
    {
        $formManager = $this->getService(FormManager::class);
        if ($this->dbService->count("SHOW TABLES LIKE '" . $this->dbService->escape(trim($this->dbService->prefixTable('nature'))) . "'") === 0) {
            return;
        }
        $failures = [];
        foreach ($this->dbService->loadAll('SELECT * FROM ' . $this->dbService->prefixTable('nature') . 'ORDER BY bn_id_nature') as $row) {
            if ($formManager->getPageTagFromId($row['bn_id_nature']) !== null) {
                continue;
            }
            try {
                $formManager->importLegacyForm($row);
            } catch (\Throwable $th) {
                $failures[] = $th->getMessage();
            }
        }
        $formManager->forgetCaches();
        if (!empty($failures)) {
            throw new \Exception(implode('; ', $failures));
        }
    }
}
