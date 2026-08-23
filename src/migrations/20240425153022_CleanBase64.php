<?php

use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Field\TextareaField;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

class CleanBase64 extends YesWikiMigration
{
    public function run()
    {
        foreach ($this->searchPagesWithBase64() as $page) {
            $this->extractImages($page);
        }
    }

    /**
     * @return list<array<string, mixed>> every entry revision whose body embeds a data: image
     */
    private function searchPagesWithBase64(): array
    {
        $anchor = '%src=\\\\\\\\\\"data:image\\\\\\\\/%;base64,%';

        $sql = 'SELECT * FROM ' . $this->dbService->prefixTable('pages') . ' ' .
            "WHERE {$this->dbService->jsonAsText('body')} LIKE '{$anchor}' " .
            'AND ' . $this->dbService->quoteIdentifier('type') . " = '" . PageType::ENTRY . "'";

        $pages = $this->dbService->loadAll($sql);

        return empty($pages) ? [] : $pages;
    }

    /**
     * @param array<string, mixed> $page one row of the pages table
     */
    private function extractImages(array $page): bool
    {
        $body = $page['body'];
        if (!is_string($body)) {
            return false;
        }
        $entryManager = $this->getService(EntryManager::class);
        $entry = $entryManager->getOne($page['tag'], false, $page['time']);
        if (empty($entry)) {
            return false;
        }
        $formId = $entry['form_id'] ?? $entry['id_typeannonce'] ?? null;
        if (empty($formId)) {
            return false;
        }

        $formManager = $this->getService(FormManager::class);
        $form = $formManager->getOne($formId);
        if (empty($form)) {
            return false;
        }
        $updated = false;
        foreach ($form['prepared'] ?? [] as $field) {
            if ($field instanceof TextareaField && !empty($entry[$field->getPropertyName()])) {
                $formatted = $field->formatValuesBeforeSaveIfEditable($entry);
                if (isset($formatted[$field->getPropertyName()])) {
                    $oldValue = json_encode($entry[$field->getPropertyName()]);
                    $newValue = json_encode($formatted[$field->getPropertyName()]);
                    if ($oldValue === false || $newValue === false) {
                        continue;
                    }
                    $body = str_replace($oldValue, $newValue, $body);
                    $updated = true;
                }
            }
        }
        if ($updated) {
            $this->dbService->query(
                "UPDATE {$this->dbService->prefixTable('pages')} " .
                "SET body = '{$this->dbService->escape(chop($body))}' " .
                "WHERE tag = '{$this->dbService->escape($page['tag'])}' " .
                "AND time = '{$this->dbService->escape($page['time'])}'"
            );
        }

        return $updated;
    }
}
