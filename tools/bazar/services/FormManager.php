<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\ImageField;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

class FormManager
{
    protected $wiki;
    protected $dbService;
    protected $entryManager;
    protected $securityController;
    protected $pageManager;
    protected $tripleStore;
    protected $fieldFactory;
    protected $params;
    protected $cachedForms;
    public $cacheValidatedForAll;
    protected $isAvailableOnlyOneEntryOption;
    protected $isAvailableOnlyOneEntryMessage;
    protected $attach;

    public const TRIPLE_FORM_ID = 'form';

    public function __construct(
        Wiki $wiki,
        DbService $dbService,
        EntryManager $entryManager,
        FieldFactory $fieldFactory,
        ParameterBagInterface $params,
        SecurityController $securityController,
        PageManager $pageManager,
        TripleStore $tripleStore,
    ) {
        if (!class_exists('attach')) {
            include 'tools/attach/libs/attach.lib.php';
        }
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->entryManager = $entryManager;
        $this->fieldFactory = $fieldFactory;
        $this->pageManager = $pageManager;
        $this->tripleStore = $tripleStore;
        $this->params = $params;

        $this->cachedForms = [];
        $this->cacheValidatedForAll = false;
        $this->securityController = $securityController;
        $this->isAvailableOnlyOneEntryOption = null;
        $this->isAvailableOnlyOneEntryMessage = null;
        $this->attach = new \Attach($this->wiki);
    }

    protected function getBasePath()
    {
        $basePath = $this->attach->GetUploadPath();

        return $basePath . (substr($basePath, -1) != '/' ? '/' : '');
    }

    protected function cleanCacheDefaultImage($prefix)
    {
        $cache_path = $this->attach->GetCachePath();
        $cache_path = $cache_path . (substr($cache_path, -1) != '/' ? '/' : '');
        $scan_cache_files = scandir($cache_path);
        foreach ($scan_cache_files as $scan_cache_file) {
            if (str_starts_with($scan_cache_file, $prefix)) {
                unlink($cache_path . $scan_cache_file);
            }
        }
    }

    protected function convertWithSpecialParameters($template, $id_nature)
    {
        $template = _convert($template, YW_CHARSET, true);
        $template_list = $this->parseTemplate($template);
        $modify = false;
        for ($temp_index = 0; $temp_index < count($template_list); $temp_index++) {
            if ($template_list[$temp_index][0] == 'image') {
                $modify = true;
                $basePath = $this->getBasePath();
                $image_comp = $template_list[$temp_index];
                $default_image_prefix = "defaultimage{$id_nature}_{$image_comp[1]}";
                $this->cleanCacheDefaultImage($default_image_prefix);
                $default_image_filename = $basePath . $default_image_prefix . '.jpg';
                $default_image = explode('|', $image_comp[ImageField::FIELD_IMAGE_DEFAULT]);
                if (count($default_image) == 2) {
                    $image_comp[ImageField::FIELD_IMAGE_DEFAULT] = $default_image[0];
                    $imgext = explode('image/', explode(';', $default_image[1])[0])[1];
                    $tmpFile = tempnam('cache', 'dfltimg');
                    $tempFile = $tmpFile . '.' . $imgext;
                    rename($tmpFile, $tempFile);
                    try {
                        $ifp = fopen($tempFile, 'wb');
                        fwrite($ifp, base64_decode(explode(',', $default_image[1])[1]));
                        fclose($ifp);
                        $this->attach->redimensionner_image($tempFile, $default_image_filename, $image_comp[5], $image_comp[6], 'crop');
                    } finally {
                        unlink($tempFile);
                    }
                } else {
                    $image_comp[ImageField::FIELD_IMAGE_DEFAULT] = '';
                    if (file_exists($default_image_filename)) {
                        unlink($default_image_filename);
                    }
                }
                $template_list[$temp_index] = $image_comp;
            }
        }
        if ($modify) {
            $template = $this->encodeTemplate($template_list);
        }

        return $this->dbService->escape($template);
    }

    // necessary until jquery form is rework to use new format
    protected function prepare_with_special_parameter_old_form_format($form) {
        $basePath = $this->getBasePath();
        $template_list = $this->parseTemplate($form['bn_template']);
        $id = $form['id'] ?? $form['bn_id_nature'];
        $prepared = [];
        $modify = false;
        for ($temp_index = 0; $temp_index < count($template_list); $temp_index++) {
            if ($template_list[$temp_index][0] == 'image') {
                $modify = true;
                $image_comp = $template_list[$temp_index];
                $default_image_filename = $basePath . "defaultimage{$id}_{$image_comp[1]}.jpg";
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

    protected function prepare_with_special_parameters($form)
    {
        $basePath = $this->getBasePath();
        if (isset($form['bn_template'])) {
            $template_list = $this->parseTemplate($form['bn_template']);
        } else {
            $template_list = $form['body'];

        }

        $prepared = [];
        $modify = false;
        foreach($template_list['fields'] as $key => $field) {
            $cname = "YesWiki\\Bazar\\Field\\".$field['field_type'];
            $wikifield = $cname::mapToFieldArray($field);

            if ($wikifield[0] == 'image') {
                $modify = true;
                $default_image_filename = $basePath . "defaultimage{$form['id']}_{$wikifield[1]}.jpg";
                if (file_exists($default_image_filename)) {
                    $wikifield[ImageField::FIELD_IMAGE_DEFAULT] = $wikifield[ImageField::FIELD_IMAGE_DEFAULT] . '|data:image/jpg;base64,' . base64_encode(file_get_contents($default_image_filename));
                } else {
                    $wikifield[ImageField::FIELD_IMAGE_DEFAULT] = '';
                }
            }
            $prepared[] = $wikifield;
        }
        $template_list['fields'] = $prepared;
        return [$prepared, $modify];
    }


    public function getOne($formId,  $lang = 'default'): ?array
    {
        if ($lang === 'default' and isset($_GET['lang'])) {
            $lang = $_GET['lang'];
        }
        $lang_id = $formId;
        if ($lang != 'default') {
            $lang_id .= '_'.$lang;
        }
        if (isset($this->cachedForms[$lang_id])) {
            return $this->cachedForms[$lang_id];
        }

        if ($lang === 'all' || $lang === 'default') {
            $select_options = '*';
        } else {
            $select_options = "id, tag, time, body_r, owner, user, latest, handler, comment_on ,JSON_MERGE_PATCH(body, COALESCE(JSON_EXTRACT(body, \"\$.__extra_lang.$lang\"), body)) as body";
        }


        if (is_numeric($formId)) {
            $formId = $this->getPageTagFromId($formId);
            if ($formId == null) {
                return null;
            }
        }

        $form = $this->pageManager->getOne($formId, null, true, false, null, $select_options);

        if (!$form) {
            return null;
        }

        $form = $this->getFromRawData($form);
        $template = [];
        foreach($form['template'] as $line) {
            $template[] = implode('***', $line);
        }
        $form['template'] = implode("\n", $template);

        if ($lang != 'all' and isset($form['body']['__extra_lang'])) {
              unset($form['body']['__extra_lang']);
        }

        $this->cachedForms[$lang_id] = $form;

        return $form;
    }


    public function getPageTagFromId($id) {
        $request = 'SELECT tag FROM '. $this->pageManager->pageTableName .' WHERE latest = "Y" AND json_value(body, "$.id") = '.$id.'; ';
        $tag = $this->dbService->loadSingle($request);
        return $tag['tag'] ?? null ;
    }

    public function getFromRawData($form)
    {
        foreach ($form as $key => $value) {
            $form[$key] = _convert($value, 'ISO-8859-15');
        }
        if (isset($form['body'])) {
            $form['body'] = json_decode($form['body'], true);
            $form['description'] = $form['body']['description'];
            $form['title'] = $form['body']['title'];
            $form['bn_condition'] = $form['body']['condition'];
            $form['bn_sem_context'] = $form['body']['semantic']['context'] ?? '';
            $form['bn_sem_type'] = $form['body']['semantic']['type'] ?? '';
            $form['bn_sem_use_template'] = $form['body']['semantic']['use_template'] ?? '' ;
            $form['bn_condition'] = $form['body']['condition'] ?? '';
            $form['bn_only_one_entry'] = $form['body']['only_one_entry'] ?? '';
            $form['bn_only_one_entry_message'] = $form['body']['one_entry_message'] ?? '';
            $form['bn_id_nature'] = $form['body']['id'];

            list($template_list, $modify) = $this->prepare_with_special_parameters($form);
        } else {
            list($template_list, $modify) = $this->prepare_with_special_parameter_old_form_format($form);
        }
        $form['template'] = $template_list;
        $form['prepared'] = $this->prepareData($form);
        if ($modify == true) {
            $form['bn_template'] = $this->encodeTemplate($template_list);
        }

        return $form;
    }

    public function getAll(): array
    {
        if (!$this->cacheValidatedForAll) {
            $forms = $this->pageManager->getManyFromTriple('form');
            foreach ($forms as $form) {
                if (!empty($form['id'])) {
                    // save only not empty formId
                    $form_prepared = $this->getFromRawData($form);
                    $this->cachedForms[$form_prepared['body']['id']] = $form_prepared;
                }
            }
            $this->cacheValidatedForAll = true;
        }

        return $this->cachedForms;
    }


    /**
    * @return array with id => title for each form
    *
    */
    public function getAllIds(): array
    {
        $forms = [];
        $id_and_title = $this->pageManager->getManyFromTriple('form', ['JSON_VALUE(body, "$.id") as id', 'JSON_VALUE(body, "$.title") as title']);
        foreach ($id_and_title as $value) {
            $forms[$value['id']] = $value['title'];
        }
        return $forms;
    }

    public function getMany($formsIds): array
    {
        $results = [];

        foreach ($formsIds as $formId) {
            if (empty($this->cachedForms[$formId])) {
                $this->cachedForms[$formId] = $this->getOne($formId);
            }
            $results[$formId] = $this->cachedForms[$formId];
        }

        return $results;
    }

    // TODO Pass a Form object instead of a raw array
    public function create($data)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $id = $data['id'] ?? $data['bn_id_nature'];
        // If ID is not set or if it is already used, find a new ID
        if (empty($id) || $this->getOne($id)) {
            $data['id'] = $this->findNewId();
        }

        // reset cache
        $this->cacheValidatedForAll = false;

        $form = $this->getFromRawData($data);
        $id = genere_nom_wiki($form['bn_label_nature']);
        $saved = $this->__createOrUpdate($form, $id);
        if ($saved == 0) {
            $this->tripleStore->create(
                $id,
                TripleStore::TYPE_URI,
                'form',
                '',
                ''
            );
         }
    }

    private function __createOrUpdate($form, $tag) {
        $counter = 0;
        $form_array = [];
        foreach ($form['prepared'] as $i => $fields) {
            $classType = get_class($fields);
            $fields = json_decode(json_encode($fields), true);
            $fields['name'] = $fields['name'] ?? $fields['type'] . '__' . $counter;
            $counter++;
            if (isset($fields['options'])) {
                unset($fields['options']);
            }
            $fieldExploded = explode('\\', $classType);
            $fields['field_type'] = array_pop($fieldExploded);
            $form_array[] = $fields;
        }
        $newform = [
            'id' => $form['id'] ?? $form['bn_id_nature'],
            'title' => $form['bn_label_nature'],
            'description' => $form['bn_description'],
            'condition' => $form['bn_condition'],
            'semantic' => [
                'context' => $form['bn_sem_context'],
                'type' => $form['bn_sem_type'],
                'use_template' => $form['bn_sem_use_template'] ?? '',
            ],
            'only_one_entry' => $form['bn_only_one_entry'] ?? '',
            'only_one_entry_message' => $form['bn_only_one_entry_message'],
            'fields' => $form_array,
        ];
        $saved = $this->pageManager->save($tag, json_encode($newform, JSON_FORCE_OBJECT), '', true);
        return $saved;
        }

    public function update($data, $tag)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $template = $this->convertWithSpecialParameters($data['bn_template'], $data['id']);

        // reset cache
        $this->cacheValidatedForAll = false;

        $form = $this->getFromRawData($data);
        $this->__createOrUpdate($form, $tag);
    }

    public function clone($id)
    {
        $data = $this->getOne($id);
        if (!empty($data)) {
            unset($data['bn_id_nature']);
            $data['bn_label_nature'] = $data['bn_label_nature'] . ' (' . _t('BAZ_DUPLICATE') . ')';

            return $this->create($data);
        } else {
            // raise error?
            return false;
        }
    }

    public function delete($id)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        // tests of if $formId is int
        if (strval(intval($id)) != strval($id)) {
            return null;
        }

        $this->clear($id);

        // reset cache
        $this->cacheValidatedForAll = false;
        if (is_numeric($id)) {
            $id = $this->getPageTagFromId($id);
        }
        $this->pageManager->deleteOrphaned($id);
        return $this->tripleStore->delete($id,'http://outils-reseaux.org/_vocabulary/type');
    }

    public function clear($id)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        // delete acls for fiches of form
        $this->dbService->query(<<<SQL
                DELETE FROM {$this->dbService->prefixTable('acls')}
                WHERE page_tag IN (SELECT tag from {$this->dbService->prefixTable('pages')}
                WHERE tag in (SELECT resource FROM {$this->dbService->prefixTable('triples')}
                WHERE property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar")
                AND JSON_VALUE(body, "$.id_typeannonce") = {$this->dbService->escape($id)})
        SQL);

        // TODO use PageManager
        // delete form_entries
        $this->dbService->query(<<<SQL
            DELETE FROM {$this->dbService->prefixTable('pages')}
                WHERE tag IN (SELECT resource FROM {$this->dbService->prefixTable('triples')}
                WHERE property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar")
                AND JSON_VALUE(body, "$.id_typeannonce") = {$this->dbService->escape($id)}
        SQL);

        // TODO use TripleStore
        // delete triple entries
        $this->dbService->query(
            'DELETE FROM' . $this->dbService->prefixTable('triples') .
                'WHERE resource NOT IN (SELECT tag FROM ' . $this->dbService->prefixTable('pages') .
                'WHERE 1) AND property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar";'
        );
    }

    public function findNewId()
    {

        // QUESTION : Pourquoi une condition sur des valeures inférieurs à 10000 ?
        $result = $this->dbService->loadSingle('SELECT MAX(Json_value(body, "$.id")) as maxi FROM '.$this->pageManager->pageTableName.' WHERE latest = \'Y\' and json_value(body, "$.id") < 10000 and tag in (SELECT resource FROM '.$this->dbService->prefixTable('triples').' WHERE value = \'form\')');


        if (!$result['maxi']) {
            return 1;
        }
        if ($result['maxi'] < 999) {
            return $result['maxi'] + 1;
        }

        // DEAD CODE TODO remove on specific commit
        $result = $this->dbService->loadSingle('SELECT MAX(bn_id_nature) AS maxi FROM' . $this->dbService->prefixTable('nature') . ' where bn_id_nature > 10000');

        if (!$result['maxi']) {
            return 10001;
        } else {
            return $result['maxi'] + 1;
        }
    }

    /**
     * Découpe le template et renvoie un tableau structuré.
     *
     * @param string  Template du formulaire
     *
     * @return mixed Le tableau des elements du formulaire et options pour l'element liste
     */
    public function parseTemplate($raw)
    {
        // Parcours du template, pour mettre les champs du formulaire avec leurs valeurs specifiques
        $tableau_template = [];
        $nblignes = 0;

        // on traite le template ligne par ligne
        $chaine = explode("\n", $raw);
        foreach ($chaine as $ligne) {
            $ligne = trim($ligne);
            // on ignore les lignes vides ou commencant par # (commentaire)
            if (!empty($ligne) && !(strrpos($ligne, '#', -strlen($ligne)) !== false)) {
                // on decoupe chaque ligne par le separateur *** (c'est historique)
                $tablignechampsformulaire = array_map('trim', explode('***', $ligne));

                // TODO find another way to check that the field is valid
                if (true /* function_exists($tablignechampsformulaire[self::FIELD_TYPE]) */) {
                    if (count($tablignechampsformulaire) > 3) {
                        $tableau_template[$nblignes] = $tablignechampsformulaire;
                        for ($i = 0; $i < 16; $i++) {
                            if (!isset($tableau_template[$nblignes][$i])) {
                                $tableau_template[$nblignes][$i] = '';
                            }
                        }

                        $nblignes++;
                    }
                }
            }
        }

        return $tableau_template;
    }

    public function encodeTemplate($template_list)
    {
        $new_template_list = [];
        for ($temp_index = 0; $temp_index < count($template_list); $temp_index++) {
            $new_line = '';
            foreach ($template_list[$temp_index] as $value) {
                if ($value == '') {
                    $new_line .= ' ';
                } elseif ($value == '*') {
                    $new_line .= ' * ';
                } else {
                    $new_line .= $value;
                }
                $new_line .= '***';
            }
            $new_template_list[] = $new_line;
        }
        $template = implode("\r\n", array_map('trim', $new_template_list));

        return $template;
    }

    public function prepareData($form)
    {
        $i = 0;
        $prepared = $result = [];

        $form['template'] = _convert($form['template'], 'ISO-8859-15');

        foreach ($form['template'] as $field) {
            $classField = $this->fieldFactory->create($field);

            if ($classField) {
                $prepared[$i] = $classField;
            } elseif (function_exists($field[0])) {
                $functionName = $field[0];
                $field[0] = 'old'; // field name
                $field['functionName'] = $functionName;
                $classField = $this->fieldFactory->create($field);
                if ($classField) {
                    $prepared[$i] = $classField;
                }
            }
            //$prepared[$i]['field_type'] = get_class($classField);
            $i++;
        }

        return $prepared;
    }

    /**
     * put a form form External Wiki in cache.
     */
    public function putInCacheFromExternalBazarService(int $localFormId): bool
    {
        if (empty($localFormId) || !empty($this->getOne($localFormId))) {
            // error
            return false;
        }
        $form = $this->wiki->services->get(ExternalBazarService::class)->getTmpForm();
        if (empty($form)) {
            return false;
        } else {
            $this->cachedForms[$localFormId] = $form;

            return true;
        }
    }

    /**
     * return field from field name or property name.
     */
    public function findFieldFromNameOrPropertyName(?string $name, ?string $formId): ?BazarField
    {
        // check params
        if (empty($name) || empty($formId) || strval(intval($formId)) != strval($formId)) {
            return null;
        }

        $form = $this->getOne($formId);
        if (empty($form) || !is_array($form['prepared'])) {
            return null;
        }

        foreach ($form['prepared'] as $field) {
            if (in_array($name, [$field->getName(), $field->getPropertyName()])) {
                return $field;
            }
        }

        return null;
    }

    /**
     * check if the bn_only_one_entry option is available.
     */
    public function isAvailableOnlyOneEntryOption(): bool
    {
        if (is_null($this->isAvailableOnlyOneEntryOption)) {
            $result = $this->dbService->query("SHOW COLUMNS FROM {$this->dbService->prefixTable('nature')} LIKE 'bn_only_one_entry';");
            $this->isAvailableOnlyOneEntryOption = (@mysqli_num_rows($result) !== 0);
        }

        return $this->isAvailableOnlyOneEntryOption;
    }

    /**
     * check if the bn_only_one_entry_message is available.
     */
    public function isAvailableOnlyOneEntryMessage(): bool
    {
        if (is_null($this->isAvailableOnlyOneEntryMessage)) {
            $result = $this->dbService->query("SHOW COLUMNS FROM {$this->dbService->prefixTable('nature')} LIKE 'bn_only_one_entry_message';");
            $this->isAvailableOnlyOneEntryMessage = (@mysqli_num_rows($result) !== 0);
        }

        return $this->isAvailableOnlyOneEntryMessage;
    }
}
