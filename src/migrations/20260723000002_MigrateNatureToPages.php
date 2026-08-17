<?php

use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

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
            if ($formManager->getOne($row['bn_id_nature'])) {
                continue;
            }

            $existingPrivateKey = $row['bn_activitypub_private_key'] ?? null;
            $existingPublicKey = $row['bn_activitypub_public_key'] ?? null;

            $row['bn_activitypub_enable'] = (string)($row['bn_activitypub_enable'] ?? '0');

            $row['activitypub_enable'] = $row['bn_activitypub_enable'];
            $row['activitypub_username'] = $row['bn_activitypub_username'] ?? '';
            foreach (FormManager::LEGACY_BODY_KEYS as $legacyKey => $key) {
                if (array_key_exists($legacyKey, $row) && !array_key_exists($key, $row)) {
                    $row[$key] = $row[$legacyKey];
                }
                unset($row[$legacyKey]);
            }

            $formManager->create($row);

            if (!empty($existingPrivateKey) && !empty($existingPublicKey)) {
                $formManager->setActivitypubKeypair($row['id'], $existingPrivateKey, $existingPublicKey);
            }
        }
    }
}
