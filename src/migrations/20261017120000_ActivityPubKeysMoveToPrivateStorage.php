<?php

use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Federation\Service\ActivityPubKeyStore;

/** Moves each form's ActivityPub key from its page metadata to private/keys/activitypub, keeping the same key so remote servers still trust it. */
class ActivityPubKeysMoveToPrivateStorage extends YesWikiMigration
{
    public function run()
    {
        $pageManager = $this->getService(PageManager::class);
        $keyStore = $this->getService(ActivityPubKeyStore::class);
        $moved = 0;

        foreach ($this->getService(FormManager::class)->getAll() as $form) {
            if (empty($form['tag'])) {
                continue;
            }
            $page = $pageManager->getOne($form['tag'], null, true, true);
            $activitypub = $page['metadatas']['activitypub'] ?? null;
            if (!is_array($activitypub) || (!array_key_exists('private_key', $activitypub) && !array_key_exists('public_key', $activitypub))) {
                continue;
            }
            if (is_string($activitypub['private_key'] ?? null) && $activitypub['private_key'] !== '') {
                $keyStore->import((string)$form['id'], $activitypub['private_key']);
                $moved++;
            }
            $pageManager->setMetadata($form['tag'], ['activitypub' => array_diff_key($activitypub, ['private_key' => 0, 'public_key' => 0])]);
        }

        if ($moved > 0) {
            $this->say($moved . ' ActivityPub key(s) moved to ' . ActivityPubKeyStore::DIRECTORY . '.');
        }
    }
}
