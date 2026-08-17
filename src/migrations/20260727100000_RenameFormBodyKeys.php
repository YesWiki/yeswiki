<?php

use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

/**
 * Ticket 27 (ADR-0010): form body keys go plain-English -- bn_id_nature => id, bn_label_nature => label, bn_template => template, etc.
 */
class RenameFormBodyKeys extends YesWikiMigration
{
    public function run()
    {
        $rows = $this->dbService->loadAll(
            'SELECT id, tag, body FROM ' . $this->dbService->prefixTable('pages')
            . " WHERE latest = 'Y' AND " . $this->dbService->quoteIdentifier('type')
            . " = '" . $this->dbService->escape(PageType::FORM) . "'"
        );

        foreach ($rows as $row) {
            $body = json_decode($row['body'] ?? '', true);
            if (!is_array($body)) {
                continue;
            }

            $renamed = [];
            $changed = false;
            foreach ($body as $key => $value) {
                $newKey = FormManager::LEGACY_BODY_KEYS[$key] ?? $key;
                if ($newKey !== $key) {
                    $changed = true;
                }

                if (!array_key_exists($newKey, $renamed)) {
                    $renamed[$newKey] = $value;
                }
            }
            if (!$changed) {
                continue;
            }

            $this->dbService->query(
                'UPDATE ' . $this->dbService->prefixTable('pages')
                . " SET body = '" . $this->dbService->escape(json_encode($renamed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                . "' WHERE id = '" . $this->dbService->escape($row['id']) . "'"
            );
        }
    }
}
