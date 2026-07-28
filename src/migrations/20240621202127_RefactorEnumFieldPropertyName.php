<?php

use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Service\FieldFactory;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

class RefactorEnumFieldPropertyName extends YesWikiMigration
{
    public function run()
    {
        $formManager = $this->getService(FormManager::class);
        $fieldFactory = $this->getService(FieldFactory::class);
        $forms = $formManager->getAll();
        foreach ($forms as $form) {
            $newTemplate = [];
            foreach ($form['template'] as $fieldArray) {
                $field = $fieldFactory->create($fieldArray);
                if ($field instanceof EnumField) {
                    $fieldArray[EnumField::FIELD_NAME] = $field->getType() . $field->getLinkedObjectName() . $field->getName();
                }
                $newTemplate[] = $fieldArray;
            }
            $form['bn_template'] = $formManager->encodeTemplate($newTemplate);
            $formManager->update($form);
        }
    }
}
