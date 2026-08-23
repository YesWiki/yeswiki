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

        $forms = $formManager->getAll();
        if (!empty($forms)) {
            $fields = [];
            foreach ($forms as $form) {
                $formId = $form['bn_id_nature'];
                if (!empty($form['prepared'])) {
                    foreach ($form['prepared'] as $field) {
                        if ($field instanceof CalcField) {
                            if (empty($fields[$formId])) {
                                $fields[$formId] = [];
                            }

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
                        $fieldsNamesList = implode('|', $fieldNames);
                        $regexpOp = $this->dbService->regexpOperator();

                        $commentOnCol = $this->dbService->quoteIdentifier('parent');
                        $bodyCol = $this->dbService->quoteIdentifier('body');
                        $bodyAsText = $this->dbService->jsonAsText('body');
                        $typeCol = $this->dbService->quoteIdentifier('type');
                        $entryType = PageType::ENTRY;
                        $idCol = $this->dbService->quoteIdentifier('id');

                        $sql = <<<SQL
                            SELECT DISTINCT * FROM {$this->dbService->prefixTable('pages')}
                            WHERE $commentOnCol = ''
                            AND $bodyAsText LIKE '%"id_typeannonce":"{$this->dbService->escape(strval($formId))}"%'
                            AND $typeCol = '{$entryType}'
                            AND $bodyAsText $regexpOp '"($fieldsNamesList)":-?[0-9]'
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
