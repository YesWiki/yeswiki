<?php

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FieldFactory;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Bazar\Field\ImageField;
use YesWiki\Bazar\Service\ActivityPubService;
use YesWiki\Bazar\Service\HttpSignatureService;
use YesWiki\Security\Controller\SecurityController;

class MigrationFormManager extends FormManager
{
    protected function prepare_with_special_parameters($form)
    {
        $basePath = $this->getBasePath();
        $template_list = $this->parseTemplate($form['bn_template']);
        $prepared = [];
        $modify = false;
        for (
            $temp_index = 0;
            $temp_index < count($template_list);
            $temp_index++
        ) {
            if ($template_list[$temp_index][0] == 'image') {
                $modify = true;
                $image_comp = $template_list[$temp_index];
                $default_image_filename =
                    $basePath .
                    "defaultimage{$form['bn_id_nature']}_{$image_comp[1]}.jpg";
                if (file_exists($default_image_filename)) {
                    $image_comp[ImageField::FIELD_IMAGE_DEFAULT] =
                        $image_comp[ImageField::FIELD_IMAGE_DEFAULT] .
                        '|data:image/jpg;base64,' .
                        base64_encode(
                            file_get_contents($default_image_filename),
                        );
                } else {
                    $image_comp[ImageField::FIELD_IMAGE_DEFAULT] = '';
                }
                $template_list[$temp_index] = $image_comp;
            }
        }

        return [$template_list, $modify];
    }
}

class ConvertTableNature2Pages extends YesWikiMigration
{
    public function run()
    {
        $pageManager = $this->getService(PageManager::class);
        $tripleStore = $this->getService(TripleStore::class);
        $aclService = $this->getService(AclService::class);
        $formManager = new MigrationFormManager(
            $this->wiki,
            $this->dbService,
            $this->getService(EntryManager::class),
            $this->getService(FieldFactory::class),
            $this->params,
            $this->getService(SecurityController::class),
            $this->getService(ActivityPubService::class),
            $this->getService(HttpSignatureService::class),
            $pageManager,
            $tripleStore,
            $this->getService(AclService::class),
        );

        $forms = $this->dbService->loadAll(
            "SELECT * FROM {$this->dbService->prefixTable(
                'nature',
            )} ORDER BY bn_label_nature ASC",
        );
        foreach ($forms as $form) {
            $form = $formManager->getFromRawData($form);
            $form_array = [];
            $counter = 0;
            foreach ($form['prepared'] as $i => $fields) {
                $classType = get_class($fields);
                $fields = json_decode(json_encode($fields), true);
                $fields["order"] = $counter;

                if (
                    (!isset($fields['name']) || $fields['name'] == '') &&
                    isset($fields['linkedObjectName'])
                ) {
                    $id = $fields['type'] . $fields['linkedObjectName'];
                }
                $id = $id ?? $fields['name'] ?? $fields['type'] . '__' . $counter;
                if (isset($fields['options'])) {
                    unset($fields['options']);
                }
                $fieldExploded = explode('\\', $classType);
                $fields['field_type'] = array_pop($fieldExploded);
                $form_array[$id] = $fields;
                $counter++;
            }
            $slug = getAvailableSlug($form['bn_label_nature']); //TODO changer pour slug et ajouter mettre les droits d'admin.
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
            $saved = $pageManager->save(
                $slug,
                json_encode($newform, JSON_FORCE_OBJECT),
                '',
                true,
            );

            // give access only to admins
            $aclService->save($slug, 'read', '@admins');
            $aclService->save($slug, 'write', '@admins');

            if ($saved == 0) {
                $tripleStore->create(
                    $slug,
                    TripleStore::TYPE_URI,
                    'form',
                    '',
                    '',
                );
            }
        }
    }
}
