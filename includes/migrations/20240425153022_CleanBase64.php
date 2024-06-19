<?php

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

class CleanBase64 extends YesWikiMigration
{
    public function run()
    {
        foreach ($this->searchPagesWithBase64() as $page) {
            $this->extractImages($page);
        }
    }

    private function searchPagesWithBase64(): array
    {
        $anchor = '%src=\\\\\\\\\\"data:image\\\\\\\\/%;base64,%';
        $select_entries_filter =
            'SELECT DISTINCT resource FROM ' . $this->dbService->prefixTable('triples') .
            'WHERE value = "fiche_bazar" AND property = "http://outils-reseaux.org/_vocabulary/type" ' .
            'ORDER BY resource ASC';

        $sql = 'SELECT * FROM ' . $this->dbService->prefixTable('pages') . ' ' .
            "WHERE body LIKE '{$anchor}' " .
            'AND tag IN (' . $select_entries_filter . ')';

        $pages = $this->dbService->loadAll($sql);

        return empty($pages) ? [] : $pages;
    }

    private function extractImages(array $page): bool
    {
        $entryManager = $this->wiki->services->get(EntryManager::class);
        $entry = $entryManager->getOne($page['tag'], false, $page['time']);
        if (empty($entry)) {
            return false;
        }
        $formId = $entry['id_typeannonce'] ?? null;
        if (empty($formId)) {
            return false;
        }

        $formManager = $this->wiki->services->get(FormManager::class);
        $form = $formManager->getOne($formId);
        if (empty($form)) {
            return false;
        }
        $updated = false;
        foreach ($form['prepared'] as $field) {
            if ($field instanceof TextareaField && !empty($entry[$field->getPropertyName()])) {
                $newValue = $field->formatValuesBeforeSaveIfEditable($entry);
                if (isset($newValue[$field->getPropertyName()])) {
                    $oldValue = json_encode($entry[$field->getPropertyName()]);
                    $newValue = json_encode($newValue[$field->getPropertyName()]);
                    $page['body'] = str_replace($oldValue, $newValue, $page['body']);
                    $updated = true;
                }
            }
        }
        if ($updated) {
            $this->dbService->query(
                "UPDATE {$this->dbService->prefixTable('pages')} " .
                "SET body = '{$this->dbService->escape(chop($page['body']))}' " .
                "WHERE tag = '{$this->dbService->escape($page['tag'])}' " .
                "AND time = '{$this->dbService->escape($page['time'])}'"
            );
        }

        return $updated;
    }
}
