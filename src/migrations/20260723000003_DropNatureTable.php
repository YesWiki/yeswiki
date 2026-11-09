<?php

use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

/** Drops the `nature` table, refusing while one of its forms has no form page with fields to replace it. */
class DropNatureTable extends YesWikiMigration
{
    public function run()
    {
        if ($this->dbService->schema()->columnExists('nature', 'bn_id_nature')) {
            $formManager = $this->getService(FormManager::class);
            $missing = [];
            foreach ($this->dbService->loadAll("SELECT bn_id_nature, bn_template FROM {$this->dbService->prefixTable('nature')}") as $row) {
                if (trim((string)$row['bn_template']) === '') {
                    continue;
                }
                $form = $formManager->getOne($row['bn_id_nature']);
                if (empty($form['template'])) {
                    $missing[] = (string)$row['bn_id_nature'];
                }
            }
            if ($missing !== []) {
                throw new Exception('the nature table is kept: forms ' . implode(', ', $missing) . ' have no form page with fields yet');
            }
        }

        $this->dbService->query("DROP TABLE IF EXISTS {$this->dbService->prefixTable('nature')}");
    }
}
