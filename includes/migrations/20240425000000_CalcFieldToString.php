<?php

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

class CalcFieldToString extends YesWikiMigration
{
    public function run()
    {
        $formManager = $this->wiki->services->get(FormManager::class);

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
                        // prepare SQL to select concerned entries (EntryManager->search does not manage int)
                        $fieldsNamesList = implode('|', $fieldNames);
                        $sql = <<<SQL
                            SELECT DISTINCT * FROM {$this->dbService->prefixTable('pages')}
                            WHERE `comment_on` = ''
                            AND `body` LIKE '%"id_typeannonce":"{$this->dbService->escape(strval($formId))}"%'
                            AND `tag` IN (
                                    SELECT DISTINCT `resource` FROM {$this->dbService->prefixTable('triples')}
                                    WHERE `value` = "fiche_bazar" AND `property` = "http://outils-reseaux.org/_vocabulary/type"
                                    ORDER BY `resource` ASC
                            )
                            AND `body` REGEXP '"($fieldsNamesList)":-?[0-9]'
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
                                            SET `body` = replace(`body`,'"$fieldName":$oldValue,','"$fieldName":"$newValue",')
                                            WHERE `id` = '{$this->dbService->escape($page['id'])}'
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
