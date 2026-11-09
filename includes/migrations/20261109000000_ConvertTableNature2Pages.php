<?php

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiMigration;

/**
 * Stores every form of the `nature` table as a form page keeping its id, in the shape Ectoplasme reads; entries are left as they are.
 * A form page without a template, as an early version of this migration wrote them, is rebuilt from its row.
 */
class ConvertTableNature2Pages extends YesWikiMigration
{
    public function run()
    {
        $formManager = $this->getService(FormManager::class);
        $pageManager = $this->getService(PageManager::class);
        if ($this->dbService->count("SHOW TABLES LIKE '" . $this->dbService->escape(trim($this->dbService->prefixTable('nature'))) . "'") === 0) {
            return;
        }
        $failures = [];
        foreach ($this->dbService->loadAll('SELECT * FROM ' . $this->dbService->prefixTable('nature') . 'ORDER BY bn_id_nature') as $row) {
            $tag = $formManager->getPageTagFromId($row['bn_id_nature']);
            if ($tag !== null) {
                $body = json_decode($pageManager->getOne($tag, null, false, true)['body'] ?? '', true);
                if (!is_array($body) || array_key_exists('template', $body) || array_key_exists('bn_template', $body)) {
                    continue;
                }
            }
            try {
                $formManager->importLegacyForm($row, $tag);
            } catch (Throwable $th) {
                $failures[] = $th->getMessage();
            }
        }
        $formManager->forgetCaches();
        if (!empty($failures)) {
            throw new Exception(implode('; ', $failures));
        }
    }
}
