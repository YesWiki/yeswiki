<?php

use YesWiki\Core\YesWikiMigration;

/**
 * Drops the ActivityPub key pairs stored with the forms: the API served them, so they are discarded and new keys live in private/activitypub.
 */
class DropActivityPubKeysFromForms extends YesWikiMigration
{
    public function run()
    {
        $this->dbService->dropColumn('nature', 'bn_activitypub_private_key');
        $this->dbService->dropColumn('nature', 'bn_activitypub_public_key');
    }
}
