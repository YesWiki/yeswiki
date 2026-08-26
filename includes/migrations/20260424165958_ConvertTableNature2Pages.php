<?php

use YesWiki\Bazar\Field\ImageField;
use YesWiki\Bazar\Service\ActivityPubService;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FieldFactory;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\HttpSignatureService;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\YesWikiMigration;
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

    public function convertfield($element, $index, $existing_keys )
    {
        $classType = get_class($element);
        $field = json_decode(json_encode($element), true);
        $field['order'] = $index;
        $fieldExploded = explode('\\', $classType);
        $field['field_type'] = array_pop($fieldExploded);

        switch ($field['field_type']) {
            case 'SelectEntryField':
                $key = 'listefiche'.$field['linkedObjectName'];
                if (in_array($key, $existing_keys)) {
                    $field['name'] = $key;
                }

                break;
            case 'SelectListField':
                $key = 'liste'.$field['linkedObjectName'];
                if (in_array($key, $existing_keys)) {
                    $field['name'] = $key;
                }

                break;
            case 'CheckboxListField':
                $key = 'checkbox'.$field['linkedObjectName'].$field['id'];
                if (in_array($key, $existing_keys)) {
                    $field['name'] = $key;
                }

                break;
            case 'CheckboxEntryField':
                $key = 'checkbox'.$field['linkedObjectName'].$field['id'];
                if (in_array($key, $existing_keys)) {
                    $field['name'] = $key;
                }

                break;
            case 'RadioListField':
                $key = 'radio'.$field['linkedObjectName'].$field['id'];
                if (in_array($key, $existing_keys)) {
                    $field['name'] = $key;
                }

                break;
            case 'RadioEntryField':
                $key = 'radio'.$field['linkedObjectName'].$field['id'];
                if (in_array($key, $existing_keys)) {
                    $field['name'] = $key;
                }

                break;
            case 'MapField':
                if ($field['name'] == 'bf_latitude') {
                    $field['name'] = $field['id'] = $field['propertyname'] = 'bf_geolocation';
                }
                if ($field['label'] == 'bf_longitude') {
                    $field['label'] = _t('BAZ_FORM_EDIT_GEO_LABEL');
                }
        }
        $field['name'] = empty($field['name']) ? $field['type'].'__'.$index : $field['name'];
        $field['id'] = empty($field['id']) ? $field['name'] : $field['id'];
        if (isset($field['options'])) {
            unset($field['options']);
        }

        return $field;
    }

    /**
     * This function get a form in old nature syntax and return a form in new json syntax.
     * @param string $form  formulaire with old nature syntax
     * @param array|null $fiche as associative array using json_decode.
     * @@return array fiche in new format
     */
    public function convertform($pageManager, $tripleStore, $form, $fiche = []): array
    {

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
        $existing_keys = array_keys($fiche);
        $form = $formManager->getFromRawData($form);
        $form_array = [];
        foreach ($form['prepared'] as $i => $element) {
            $field = $this->convertfield($element, $i, $existing_keys);
            $form_array[$field['id']] = $field;
        }
        $newform = [
            'id' => $form['bn_id_nature'],
            'title' => $form['bn_label_nature'],
            'description' => $form['bn_description'],
            'condition' => $form['bn_condition'],
            'only_one_entry' => $form['bn_only_one_entry'],
            'only_one_entry_message' => $form['bn_only_one_entry_message'],
            'fields' => $form_array,
        ];
        if (isset($form['bn_sem_context']) || isset($form['bn_sem_type']) || isset($form['bn_sem_use_template'])) {
            $semantic = [];
            $form['bn_sem_context'] ?? $semantic['context'] = $form['bn_sem_context'];
            $form['bn_sem_type'] ?? $semantic['type'] = $form['type'];
            $form['bn_sem_use_template'] ?? $semantic['use_template'] = $form['bn_sem_use_template'];
            $newform['semantic'] = $semantic;
        }
        return $newform;
    }

    public function run()
    {
        $aclService = $this->getService(AclService::class);
        $pageManager = $this->getService(PageManager::class);
        $tripleStore = $this->getService(TripleStore::class);

        $forms = $this->dbService->loadAll(
            "SELECT * FROM {$this->dbService->prefixTable(
                'nature',
            )} ORDER BY bn_label_nature ASC",
        );
        foreach ($forms as $form) {

            $sql_query = "select body from {$this->dbService->prefixTable('pages')} where tag in (SELECT resource FROM {$this->dbService->prefixTable('triples')} WHERE value = 'fiche_bazar') and JSON_VALUE(body, '$.id_typeannonce') = {$form['bn_id_nature']} limit 1";
            $existing_keys = $this->dbService->loadSingle($sql_query);

            $existing_keys = empty($existing_keys) ? [] : json_decode($existing_keys['body'], true);

            $newform = $this->convertform($pageManager, $tripleStore, $form, $existing_keys);
            $slug = getAvailableSlug($form['bn_label_nature']);

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
