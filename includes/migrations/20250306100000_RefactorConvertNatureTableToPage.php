<?php

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FieldFactory;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Bazar\Field\ImageField;
use YesWiki\Security\Controller\SecurityController;

class MigrationFormManager extends FormManager
{
    protected function prepare_with_special_parameters($form)
    {

        $basePath = $this->getBasePath();
        $template_list = $this->parseTemplate($form['bn_template']);
        $prepared = [];
        $modify = false;
        for ($temp_index = 0; $temp_index < count($template_list); $temp_index++) {
            if ($template_list[$temp_index][0] == 'image') {
                $modify = true;
                $image_comp = $template_list[$temp_index];
                $default_image_filename = $basePath . "defaultimage{$form['bn_id_nature']}_{$image_comp[1]}.jpg";
                if (file_exists($default_image_filename)) {
                    $image_comp[ImageField::FIELD_IMAGE_DEFAULT] = $image_comp[ImageField::FIELD_IMAGE_DEFAULT] . '|data:image/jpg;base64,' . base64_encode(file_get_contents($default_image_filename));
                } else {
                    $image_comp[ImageField::FIELD_IMAGE_DEFAULT] = '';
                }
                $template_list[$temp_index] = $image_comp;
            }
        }

        return [$template_list, $modify];
    }
}

class RefactorConvertNatureTableToPage extends YesWikiMigration
{
    public function run()
    {

        $pageManager = $this->getService(PageManager::class);
        $tripleStore = $this->getService(TripleStore::class);
        $formManager = new MigrationFormManager(
            $this->wiki,
            $this->dbService,
            $this->getService(EntryManager::class),
            $this->getService(FieldFactory::class),
            $this->params,
            $this->getService(SecurityController::class),
            $pageManager,
            $tripleStore,
        );
        $forms = $this->dbService->loadAll("SELECT * FROM {$this->dbService->prefixTable('nature')} ORDER BY bn_label_nature ASC");
        foreach ($forms as $form) {
            $form = $formManager->getFromRawData($form);
            $form_array = [];
            $counter = 0;
            foreach ($form['prepared'] as $i => $fields) {
                $classType = get_class($fields);
                $fields = json_decode(json_encode($fields), true);
                dump($fields);
                if (isset($fields['id'])) {
                    $field_id = $fields['id'];
                    unset($fields['id']);
                } else {
                    $field_id = $fields['type'] . '__' . $counter;
                    $counter++;
                }
                if (isset($fields['options'])) {
                    unset($fields['options']);
                }
                $fieldExploded = explode('\\', $classType);
                $fields['field_type'] = array_pop($fieldExploded);
                $form_array[$field_id] = $fields;
                unset($form_array[$i]['propertyname']);
            }
            $id = genere_nom_wiki($form['bn_label_nature']);
            $newform = [
                'id' => $form['bn_id_nature'],
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
            $saved = $pageManager->save($id, json_encode($newform), '', true);
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
