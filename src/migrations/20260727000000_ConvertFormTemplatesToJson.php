<?php

use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

/**
 * Ticket 26: form templates (`bn_template` inside form pages' body) are now stored as a native JSON array of named-attribute field objects instead of the historical positional `***`-separated string.
 */
class ConvertFormTemplatesToJson extends YesWikiMigration
{
    public function run()
    {
        $formManager = $this->getService(FormManager::class);

        $rows = $this->dbService->loadAll(
            'SELECT id, tag, body FROM ' . $this->dbService->prefixTable('pages')
            . " WHERE latest = 'Y' AND " . $this->dbService->quoteIdentifier('type')
            . " = '" . $this->dbService->escape(PageType::FORM) . "'"
        );

        foreach ($rows as $row) {
            $body = json_decode($row['body'] ?? '', true);
            if (!is_array($body) || is_array($body['bn_template'] ?? null)) {
                continue;
            }
            $template = trim((string)($body['bn_template'] ?? ''));

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
