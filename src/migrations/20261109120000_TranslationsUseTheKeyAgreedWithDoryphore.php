<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\Translations;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;

/** Translations still under `__extralang` or the flat `__translations` move to `__translation`, the key Doryphore uses too. */
class TranslationsUseTheKeyAgreedWithDoryphore extends YesWikiMigration
{
    public function run()
    {
        $db = $this->getService(DbService::class);
        $pages = $db->prefixTable('pages');
        $body = $db->jsonAsText('body');
        $rows = $db->loadAll(
            "SELECT id, tag, body FROM {$pages} WHERE latest = 'Y' AND ({$body} LIKE ? OR {$body} LIKE ?)",
            ['%"' . Translations::NESTED_LEGACY_KEY . '"%', '%"' . Translations::LEGACY_KEY . '"%']
        );

        $rewritten = [];
        foreach ($rows as $row) {
            $decoded = PageBody::decode((string)$row['body']);
            if (!isset($decoded[Translations::NESTED_LEGACY_KEY]) && !isset($decoded[Translations::LEGACY_KEY])) {
                continue;
            }
            $db->query("UPDATE {$pages} SET body = ? WHERE id = ?", [PageBody::encode(Translations::inCurrentShape($decoded)), (string)$row['id']]);
            $rewritten[] = (string)$row['tag'];
        }

        if ($rewritten !== []) {
            $this->say('Translations of ' . count($rewritten) . ' Content(s) are now stored under ' . Translations::BODY_KEY . '.');
        }
    }
}
