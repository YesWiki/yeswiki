<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\Translations;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;

/** Translations move from the flat `__translations` to `__extralang`, nested the way Doryphore's `extralang` is. */
class TranslationsAreStoredLikeDoryphoreExtralang extends YesWikiMigration
{
    public function run()
    {
        $db = $this->getService(DbService::class);
        $pages = $db->prefixTable('pages');
        $rows = $db->loadAll(
            "SELECT id, tag, body FROM {$pages} WHERE latest = 'Y' AND {$db->jsonAsText('body')} LIKE ?",
            ['%"' . Translations::LEGACY_KEY . '"%']
        );

        $rewritten = [];
        foreach ($rows as $row) {
            $body = PageBody::decode((string)$row['body']);
            if (!isset($body[Translations::LEGACY_KEY])) {
                continue;
            }
            $db->query("UPDATE {$pages} SET body = ? WHERE id = ?", [PageBody::encode(Translations::inCurrentShape($body)), (string)$row['id']]);
            $rewritten[] = (string)$row['tag'];
        }

        if ($rewritten !== []) {
            $this->say('Translations of ' . count($rewritten) . ' Content(s) are now stored under ' . Translations::BODY_KEY . '.');
        }
    }
}
