<?php

use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Core\YesWikiMigration;

/**
 * Ticket 27 (ADR-0010): entry system keys go plain-English -- id_fiche => tag, id_typeannonce => form_id, date_creation_fiche => created_at, date_maj_fiche => updated_at, statut_fiche => status -- and every entry gains the computed `title` (stamped from bf_titre for existing entries).
 */
class RenameEntryBodyKeys extends YesWikiMigration
{
    private const STRIPPED_ARTIFACTS = ['antispam', 'valider', 'MAX_FILE_SIZE'];

    public function run()
    {
        $rows = $this->dbService->loadAll(
            'SELECT id, tag, body FROM ' . $this->dbService->prefixTable('pages')
            . " WHERE latest = 'Y' AND " . $this->dbService->quoteIdentifier('type')
            . " = '" . $this->dbService->escape(PageType::ENTRY) . "'"
        );

        foreach ($rows as $row) {
            $body = json_decode($row['body'] ?? '', true);
            if (!is_array($body)) {
                continue;
            }

            $renamed = [];
            $changed = false;
            foreach ($body as $key => $value) {
                if (in_array($key, self::STRIPPED_ARTIFACTS, true)) {
                    $changed = true;
                    continue;
                }

                $newKey = $key === 'bf_titre' ? $key : (EntryManager::LEGACY_ENTRY_KEYS[$key] ?? $key);
                if ($newKey !== $key) {
                    $changed = true;
                }
                if (!array_key_exists($newKey, $renamed)) {
                    $renamed[$newKey] = $value;
                }
            }

            if (!array_key_exists('title', $renamed)) {
                $renamed['title'] = (string)($renamed['bf_titre'] ?? $row['tag']);
                $changed = true;
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
