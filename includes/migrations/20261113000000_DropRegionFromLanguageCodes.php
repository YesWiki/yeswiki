<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Wiki;

/**
 * Stores every language code as its plain ISO 639 code, `fr` for `fr-FR`, in page bodies and in the `nature` table.
 */
class DropRegionFromLanguageCodes extends YesWikiMigration
{
    public function run()
    {
        $pages = $this->dbService->prefixTable('pages');
        foreach ($this->dbService->loadAll("SELECT `id`, `body` FROM {$pages} WHERE `body` LIKE '%\"lang\":\"%'") as $page) {
            $body = json_decode($page['body']);
            if (!$body instanceof stdClass || !is_string($body->lang ?? null)) {
                continue;
            }
            $lang = Wiki::sanitizeLang($body->lang, '');
            if ($lang !== '' && $lang !== $body->lang) {
                $body->lang = $lang;
                $this->dbService->query("UPDATE {$pages} SET `body` = '" . $this->dbService->escape(json_encode($body)) . "' WHERE `id` = '" . $this->dbService->escape($page['id']) . "'");
            }
        }

        $nature = $this->dbService->prefixTable('nature');
        if ($this->dbService->count("SHOW TABLES LIKE '" . $this->dbService->escape(trim($nature)) . "'") === 0) {
            return;
        }
        foreach ($this->dbService->loadAll("SELECT DISTINCT `bn_ce_i18n` FROM {$nature}") as $row) {
            $lang = Wiki::sanitizeLang($row['bn_ce_i18n'], '');
            if ($lang !== '' && $lang !== $row['bn_ce_i18n']) {
                $this->dbService->query("UPDATE {$nature} SET `bn_ce_i18n` = '" . $this->dbService->escape($lang) . "' WHERE `bn_ce_i18n` = '" . $this->dbService->escape($row['bn_ce_i18n']) . "'");
            }
        }
    }
}
