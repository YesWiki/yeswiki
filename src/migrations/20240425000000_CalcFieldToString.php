<?php

use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Field\CalcField;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

class CalcFieldToString extends YesWikiMigration
{
    public function run()
    {
        $formManager = $this->getService(FormManager::class);

        // find CalcField in forms
        $forms = $formManager->getAll();
        if (!empty($forms)) {
            $fields = [];
            foreach ($forms as $form) {
                $formId = $form['bn_id_nature'];
                if (!empty($form['prepared'])) {
                    foreach ($form['prepared'] as $field) {
                        if ($field instanceof CalcField) {
                            // init array for this form, if needed
                            if (empty($fields[$formId])) {
                                $fields[$formId] = [];
                            }
                            // append propertyName if not already present
                            if (!empty($field->getPropertyName()) && !in_array($field->getPropertyName(), $fields[$formId])) {
                                $fields[$formId][] = $field->getPropertyName();
                            }
                        }
                    }
                }
            }

            if (!empty($fields)) {
                foreach ($fields as $formId => $fieldNames) {
                    if (!empty($fieldNames)) {
                        // prepare SQL to select concerned entries (SearchManager->search does not manage int)
                        $fieldsNamesList = implode('|', $fieldNames);
                        $regexpOp = $this->dbService->regexpOperator();

                        // Quote identifiers for cross-database compatibility
                        $commentOnCol = $this->dbService->quoteIdentifier('parent');
                        $bodyCol = $this->dbService->quoteIdentifier('body');
                        $typeCol = $this->dbService->quoteIdentifier('type');
                        $entryType = PageType::ENTRY;
                        $idCol = $this->dbService->quoteIdentifier('id');

                        $sql = <<<SQL
                            SELECT DISTINCT * FROM {$this->dbService->prefixTable('pages')}
                            WHERE $commentOnCol = ''
                            AND $bodyCol LIKE '%"id_typeannonce":"{$this->dbService->escape(strval($formId))}"%'
                            AND $typeCol = '{$entryType}'
                            AND $bodyCol $regexpOp '"($fieldsNamesList)":-?[0-9]'
                        SQL;
                        $results = $this->dbService->loadAll($sql);
                        if (!empty($results)) {
                            foreach ($results as $page) {
                                if (preg_match_all("/\"($fieldsNamesList)\":(-?[0-9\.]*),/", $page['body'], $matches)) {
                                    foreach ($matches[0] as $index => $match) {
                                        $fieldName = $matches[1][$index];
                                        $oldValue = $matches[2][$index];
                                        $newValue = strval($oldValue);
                                        $replaceSQL = <<<SQL
                                            UPDATE {$this->dbService->prefixTable('pages')}
                                            SET $bodyCol = replace($bodyCol, '"$fieldName":$oldValue,', '"$fieldName":"$newValue",')
                                            WHERE $idCol = '{$this->dbService->escape($page['id'])}'
                                        SQL;
                                        // replace
                                        $this->dbService->query($replaceSQL);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
