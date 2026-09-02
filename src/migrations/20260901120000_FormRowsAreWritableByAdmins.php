<?php

use YesWiki\Content\Entity\PageType;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\DbService;

/** Ticket 63: a form's row answers to `/edit` with the designer, so its write ACL says who may design, not the wiki-wide default. */
class FormRowsAreWritableByAdmins extends YesWikiMigration
{
    public function run(): void
    {
        $dbService = $this->getService(DbService::class);
        $aclService = $this->getService(AclService::class);

        $rows = $dbService->loadAll(
            'SELECT tag FROM ' . $dbService->prefixTable('pages') . " WHERE latest = 'Y' AND type = ?",
            [PageType::FORM]
        );

        foreach ($rows as $row) {
            if ($aclService->load($row['tag'], 'write', false) === null) {
                $aclService->save($row['tag'], 'write', '@admins');
            }
        }
    }
}
