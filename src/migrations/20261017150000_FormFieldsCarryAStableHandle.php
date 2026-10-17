<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;

/** Ticket 66: every form field gets a stable handle, so renaming it in the designer keeps its translations. */
class FormFieldsCarryAStableHandle extends YesWikiMigration
{
    public function run()
    {
        $db = $this->getService(DbService::class);
        $formManager = $this->getService(FormManager::class);
        $pages = $db->prefixTable('pages');
        $rows = $db->loadAll("SELECT id, tag, body FROM {$pages} WHERE latest = 'Y' AND {$db->quoteIdentifier('type')} = ?", [PageType::FORM]);

        $given = [];
        foreach ($rows as $row) {
            $body = PageBody::decode((string)$row['body']);
            if (!is_array($body['template'] ?? null)) {
                continue;
            }
            $template = $formManager->withHandles($body['template']);
            if ($template === $body['template']) {
                continue;
            }
            $body['template'] = $template;
            $db->query("UPDATE {$pages} SET body = ? WHERE id = ?", [PageBody::encode($body), (string)$row['id']]);
            $given[] = (string)$row['tag'];
        }

        if ($given !== []) {
            $this->say('Fields got a stable handle in ' . count($given) . ' form(s).');
        }
    }
}
