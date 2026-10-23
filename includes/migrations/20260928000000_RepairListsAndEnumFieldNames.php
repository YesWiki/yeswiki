<?php

use YesWiki\Bazar\Field\EnumField;
use YesWiki\Bazar\Service\FieldFactory;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\YesWikiMigration;

/** Redoes the list and enum field conversions that earlier migrations could silently skip. */
class RepairListsAndEnumFieldNames extends YesWikiMigration
{
    public function run()
    {
        $this->convertRemainingLists();
        $this->restoreEnumFieldNames();
    }

    /** Converts the lists still stored as { titre_liste, label }. */
    private function convertRemainingLists(): void
    {
        $pageManager = $this->getService(PageManager::class);
        $listManager = $this->getService(ListManager::class);
        $lists = $this->getService(TripleStore::class)->getMatching(null, TripleStore::TYPE_URI, ListManager::TRIPLES_LIST_ID, '', '');
        foreach ($lists as $list) {
            $page = $pageManager->getOne($list['resource'], null, false, true);
            if (empty($page)) {
                continue;
            }
            $oldJson = json_decode($page['body'], true);
            $newJson = $listManager->convertDataStructure($oldJson);
            if ($newJson !== $oldJson) {
                $pageManager->save($list['resource'], json_encode($newJson), '', true);
            }
        }
    }

    /** Gives an enum field back its full property name when the form's entries store their values under that name. */
    private function restoreEnumFieldNames(): void
    {
        $formManager = $this->getService(FormManager::class);
        $fieldFactory = $this->getService(FieldFactory::class);
        foreach ($formManager->getAll() as $form) {
            $storedKeys = $this->storedEntryKeys($form['bn_id_nature']);
            $changed = false;
            $newTemplate = [];
            foreach ($form['template'] as $fieldArray) {
                $field = $fieldFactory->create($fieldArray);
                if ($field instanceof EnumField) {
                    $name = $field->getName() ?? '';
                    $fullName = $field->getType() . $field->getLinkedObjectName() . $name;
                    if ($fullName !== $name && isset($storedKeys[$fullName]) && ($name === '' || !isset($storedKeys[$name]))) {
                        $fieldArray[EnumField::FIELD_NAME] = $fullName;
                        $changed = true;
                    }
                }
                $newTemplate[] = $fieldArray;
            }
            if ($changed) {
                $form['bn_template'] = $formManager->encodeTemplate($newTemplate);
                $this->dbService->query(
                    'UPDATE ' . $this->dbService->prefixTable('nature')
                    . " SET `bn_template` = '" . $this->dbService->escape($form['bn_template']) . "'"
                    . ' WHERE `bn_id_nature` = ' . intval($form['bn_id_nature'])
                );
                $formManager->cacheForm($form['bn_id_nature'], $formManager->getFromRawData(array_diff_key($form, ['template' => 0, 'prepared' => 0])));
            }
        }
    }

    /** Returns, as array keys, every property name used by the latest version of the form's entries. */
    private function storedEntryKeys($formId): array
    {
        $rows = $this->dbService->loadAll(
            'SELECT `body` FROM ' . $this->dbService->prefixTable('pages')
            . " WHERE `latest` = 'Y' AND `body` LIKE '%\"id\\_typeannonce\":\"" . intval($formId) . "\"%'"
        );
        $keys = [];
        foreach ($rows as $row) {
            $entry = json_decode($row['body'], true);
            if (is_array($entry) && ($entry['id_typeannonce'] ?? null) == $formId) {
                $keys += array_fill_keys(array_keys($entry), true);
            }
        }

        return $keys;
    }
}
