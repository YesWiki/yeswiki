<?php

use YesWiki\Bazar\Field\EnumField;
use YesWiki\Bazar\Service\FieldFactory;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

/** Stores each enum field's full property name (type + linked object + name) in its form template. */
class RefactorEnumFieldPropertyName extends YesWikiMigration
{
    public function run()
    {
        $formManager = $this->getService(FormManager::class);
        $fieldFactory = $this->getService(FieldFactory::class);
        foreach ($formManager->getAll() as $form) {
            $newTemplate = [];
            foreach ($form['template'] as $fieldArray) {
                $field = $fieldFactory->create($fieldArray);
                if ($field instanceof EnumField) {
                    $prefix = $field->getType() . $field->getLinkedObjectName();
                    if (!str_starts_with($field->getName() ?? '', $prefix)) {
                        $fieldArray[EnumField::FIELD_NAME] = $prefix . $field->getName();
                    }
                }
                $newTemplate[] = $fieldArray;
            }
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
