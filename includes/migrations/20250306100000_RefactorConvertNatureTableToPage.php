<?php

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\YesWikiMigration;

class RefactorConvertNatureTableToPage extends YesWikiMigration
{
    public function run()
    {
        $formManager = $this->getService(FormManager::class);
        $pageManager = $this->getService(PageManager::class);
        $tripleStore = $this->getService(TripleStore::class);
        $forms = $formManager->getAll();
        foreach ($forms as $form) {
            $form_array = [];
            foreach ($form['prepared'] as $i => $fields) {
                $form_array[$i] = (array) $fields;
                unset($form_array[$i]['propertyname']);
            }
            $id = genere_nom_wiki($form['bn_label_nature']);
            $newform = [
                'id' => $id,
                'title' => $form['bn_label_nature'],
                'description' => $form['bn_description'],
                'condition' => $form['bn_condition'],
                'semantic' => [
                    'context' => $form['bn_sem_context'],
                    'type' => $form['bn_sem_type'],
                    'use_template' => $form['bn_sem_use_template'],
                ],
                'only_one_entry' => $form['bn_only_one_entry'],
                'only_one_entry_message' => $form['bn_only_one_entry_message'],
                'fields' => $form_array,
            ];

            $saved = $pageManager->save($id, json_encode($newform));
            if ($saved == 0) {
                $tripleStore->create(
                    $id,
                    TripleStore::TYPE_URI,
                    'form',
                    '',
                    ''
                );
            }
        }
    }
}
