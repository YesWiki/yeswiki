<?php

use YesWiki\Core\Service\FormManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\YesWikiMigration;

/**
 * Ticket 26: form templates (`bn_template` inside form pages' body) are now stored as a
 * native JSON array of named-attribute field objects instead of the historical positional
 * `***`-separated string. Converts the latest revision of every form page in place (no
 * new revision). Older revisions keep the legacy syntax and stay readable through
 * FormManager::parseTemplate()'s legacy branch, which is also how remote imports from
 * older wikis are converted on write.
 *
 * Also repairs a pre-existing SQLite seeding defect: the installation seeds escaped the
 * template's newlines MySQL-style (`\\r\\n`), which SQLite stored literally, leaving the
 * whole template on one line with literal `\r\n` text between fields (and the form
 * therefore broken). Those literal sequences are turned back into real newlines before
 * conversion.
 */
class ConvertFormTemplatesToJson extends YesWikiMigration
{
    public function run()
    {
        $formManager = $this->getService(FormManager::class);

        $rows = $this->dbService->loadAll(
            'SELECT id, tag, body FROM ' . $this->dbService->prefixTable('pages')
            . " WHERE latest = 'Y' AND tag IN (SELECT resource FROM " . $this->dbService->prefixTable('triples')
            . " WHERE property = '" . $this->dbService->escape(TripleStore::TYPE_URI)
            . "' AND value = '" . $this->dbService->escape(FormManager::TRIPLES_FORM_TYPE) . "')"
        );

        foreach ($rows as $row) {
            $body = json_decode($row['body'] ?? '', true);
            if (!is_array($body) || is_array($body['bn_template'] ?? null)) {
                continue; // unreadable, or already a native JSON array
            }
            $template = trim((string)($body['bn_template'] ?? ''));

            // SQLite-seeded instances: MySQL-style escaped newlines stored literally
            if (!str_contains($template, "\n") && str_contains($template, '\r\n')) {
                $template = str_replace('\r\n', "\n", $template);
            }

            $body['bn_template'] = json_decode($formManager->normalizeTemplate($template), true) ?? [];

            $this->dbService->query(
                'UPDATE ' . $this->dbService->prefixTable('pages')
                . " SET body = '" . $this->dbService->escape(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                . "' WHERE id = '" . $this->dbService->escape($row['id']) . "'"
            );
        }
    }
}
