<?php

use YesWiki\Content\Service\FormManager;
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

            // create() has no way to tell "a new form" apart from "an existing form being
            // migrated" and always generates a fresh ActivityPub keypair when enabled --
            // stash the real, previously published keypair (if any) and restore it
            // afterwards, so an already-federating form doesn't silently lose its identity
            $existingPrivateKey = $row['bn_activitypub_private_key'] ?? null;
            $existingPublicKey = $row['bn_activitypub_public_key'] ?? null;

            // `nature.bn_activitypub_enable` is an integer column: loadAll() (native
            // prepared statements, no PDO::ATTR_STRINGIFY_FETCHES) returns it as a PHP int,
            // but ActivityPubService::isEnabled() strictly compares === '1' -- every other
            // caller of create()/isEnabled() already passes a string (POST data, or
            // pageToFormArray()'s (string) cast), so this raw DB row is the one place that
            // needs normalizing rather than loosening that check everywhere else
            $row['bn_activitypub_enable'] = (string)($row['bn_activitypub_enable'] ?? '0');

            // ticket 27: create() speaks the plain-English key language now -- translate
            // the raw legacy nature row at this boundary
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
