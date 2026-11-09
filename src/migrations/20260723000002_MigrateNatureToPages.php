<?php

use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Federation\Service\ActivityPubKeyStore;

/** Stores each `nature` form as a form page; a page already holding that id is kept unless it has no fields, then it is rebuilt from the row. */
class MigrateNatureToPages extends YesWikiMigration
{
    public function run()
    {
        if (!$this->dbService->schema()->columnExists('nature', 'bn_id_nature')) {
            return;
        }

        $formManager = $this->getService(FormManager::class);
        $rows = $this->dbService->loadAll(
            "SELECT * FROM {$this->dbService->prefixTable('nature')} ORDER BY bn_id_nature ASC"
        );

        foreach ($rows as $row) {
            $existing = $formManager->getOne($row['bn_id_nature']);
            if ($existing && (!empty($existing['template']) || trim((string)($row['bn_template'] ?? '')) === '')) {
                continue;
            }

            $existingPrivateKey = $row['bn_activitypub_private_key'] ?? null;

            $row['bn_activitypub_enable'] = (string)($row['bn_activitypub_enable'] ?? '0');

            $row['activitypub_enable'] = $row['bn_activitypub_enable'];
            $row['activitypub_username'] = $row['bn_activitypub_username'] ?? '';
            foreach (FormManager::LEGACY_BODY_KEYS as $legacyKey => $key) {
                if (array_key_exists($legacyKey, $row) && !array_key_exists($key, $row)) {
                    $row[$key] = $row[$legacyKey];
                }
                unset($row[$legacyKey]);
            }

            if ($existing) {
                $formManager->update($row);
                $this->say("Form {$row['id']} had a page without fields; it was rebuilt from the nature table.");
            } else {
                $formManager->create($row);
            }

            if (!empty($existingPrivateKey)) {
                $this->getService(ActivityPubKeyStore::class)->import((string)$row['id'], $existingPrivateKey);
            }
        }
    }
}
