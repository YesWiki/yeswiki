<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\ImageField;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\HtmlPurifierService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

class FormManager
{
    protected $wiki;
    protected $dbService;
    protected $activityPubService;
    protected $entryManager;
    protected $securityController;
    protected $fieldFactory;
    protected $params;
    protected $cachedForms;
    protected $cacheValidatedForAll;
    protected $attach;
    protected $pageManager;
    protected $tripleStore;
    protected $aclService;
    protected $formTags;

    public const TRIPLE_FORM_TYPE = 'form';
    public const TRANSLATABLE_FIELD_PROPERTIES = ['label', 'helper', 'hint', 'default'];

    public function __construct(
        Wiki $wiki,
        DbService $dbService,
        EntryManager $entryManager,
        FieldFactory $fieldFactory,
        ParameterBagInterface $params,
        SecurityController $securityController,
        ActivityPubService $activityPubService,
        PageManager $pageManager,
        TripleStore $tripleStore,
        AclService $aclService,
    ) {
        if (!class_exists('attach')) {
            include 'tools/attach/libs/attach.lib.php';
        }
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->activityPubService = $activityPubService;
        $this->entryManager = $entryManager;
        $this->fieldFactory = $fieldFactory;
        $this->params = $params;
        $this->pageManager = $pageManager;
        $this->tripleStore = $tripleStore;
        $this->aclService = $aclService;

        $this->cachedForms = [];
        $this->cacheValidatedForAll = false;
        $this->securityController = $securityController;
        $this->formTags = null;
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

        return $template;
    }

    /** Attaches the stored default image of each image field, as base64, to the template rows. */
    protected function withDefaultImages(array $template_list, $formId): array
    {
        $basePath = $this->getBasePath();
        foreach ($template_list as $index => $row) {
            if ($row[0] == 'image') {
                $default_image_filename = $basePath . "defaultimage{$formId}_{$row[1]}.jpg";
                if (file_exists($default_image_filename)) {
                    $row[ImageField::FIELD_IMAGE_DEFAULT] = explode('|', $row[ImageField::FIELD_IMAGE_DEFAULT])[0] . '|data:image/jpg;base64,' . base64_encode(file_get_contents($default_image_filename));
                } else {
                    $row[ImageField::FIELD_IMAGE_DEFAULT] = '';
                }
                $template_list[$index] = $row;
            }
        }

        return $template_list;
    }

    /** The template rows of a form stored as JSON, in field order. */
    protected function templateFromFields(array $fields): array
    {
        uasort($fields, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        $template_list = [];
        foreach ($fields as $field) {
            if (isset($field['template_row']) && is_array($field['template_row'])) {
                $template_list[] = $field['template_row'];
            } else {
                $template_list[] = $this->fieldClass($field)::mapToFieldArray($field);
            }
        }

        return $template_list;
    }

    /** The field class a stored JSON field rebuilds into. */
    protected function fieldClass(array $field): string
    {
        foreach ([$field['field_class'] ?? null, isset($field['field_type']) ? 'YesWiki\\Bazar\\Field\\' . $field['field_type'] : null] as $class) {
            if (is_string($class) && class_exists($class) && is_a($class, BazarField::class, true)) {
                return $class;
            }
        }

        return BazarField::class;
    }

    /** The tag of the page holding form $id, or null. */
    public function getPageTagFromId($id): ?string
    {
        return $this->formTags()[(string)$id] ?? null;
    }

    /** Form ids mapped to the tags of their pages. */
    protected function formTags(): array
    {
        if ($this->formTags === null) {
            $this->formTags = [];
            foreach ($this->pageManager->getManyFromTriple(self::TRIPLE_FORM_TYPE) as $page) {
                $body = json_decode($page['body'] ?? '', true);
                if (isset($body['id']) && intval($body['id']) . '' === $body['id'] . '') {
                    $this->formTags[(string)$body['id']] = $page['tag'];
                }
            }
        }

        return $this->formTags;
    }

    public function forgetCaches(): void
    {
        $this->cachedForms = [];
        $this->cacheValidatedForAll = false;
        $this->formTags = null;
    }

    /**
     * The form $formId (its numeric id or its page tag), with its `__translation` for $lang applied
     * ('default' = the current language, 'all' = untranslated, with every translation).
     */
    public function getOne($formId, string $lang = 'default'): ?array
    {
        if ($formId === null || $formId === '') {
            return null;
        }
        $formId = (string)$formId;
        if (intval($formId) . '' === $formId) {
            $id = $formId;
            $tag = $this->getPageTagFromId($id);
        } else {
            $tag = $formId;
            $id = array_search($tag, $this->formTags(), true);
            if ($id === false) {
                return null;
            }
        }
        $cacheKey = $lang === 'default' ? $id : "{$id}|{$lang}";
        if (array_key_exists($cacheKey, $this->cachedForms)) {
            return $this->cachedForms[$cacheKey] ?: null;
        }
        if ($tag === null) {
            return null;
        }

        $page = $this->pageManager->getOne($tag, null, true, false, null, $lang);
        if (empty($page['body'])) {
            return null;
        }
        $form = $this->getFromRawData($page);
        $form['tag'] = $tag;
        $this->cachedForms[$cacheKey] = $form;

        return $form;
    }

    /**
     * A form ready for use (`template` rows and `prepared` fields) from a form page, a `nature` row or posted form data.
     */
    public function getFromRawData($form)
    {
        if (isset($form['body'])) {
            $body = json_decode($form['body'], true);
            if (!is_array($body)) {
                return null;
            }
            $form = [
                'bn_id_nature' => (string)($body['id'] ?? ''),
                'bn_label_nature' => $body['title'] ?? '',
                'bn_description' => $body['description'] ?? '',
                'bn_condition' => $body['condition'] ?? '',
                'bn_sem_template' => $body['sem_template'] ?? '',
                'bn_sem_reverse_template' => $body['sem_reverse_template'] ?? '',
                'bn_activitypub_enable' => (string)($body['activitypub_enable'] ?? '0'),
                'bn_only_one_entry' => $body['only_one_entry'] ?? 'N',
                'bn_only_one_entry_message' => $body['only_one_entry_message'] ?? '',
                'fields' => $body['fields'] ?? [],
                'lang' => $body['lang'] ?? null,
                '__translation' => $body['__translation'] ?? [],
            ];
            $template_list = $this->templateFromFields($form['fields']);
        } else {
            foreach ($form as $key => $value) {
                $form[$key] = _convert($value, 'ISO-8859-15');
            }
            $template_list = $this->parseTemplate($form['bn_template'] ?? '');
        }
        $form['id'] = $form['bn_id_nature'] ?? '';
        $form['title'] = $form['bn_label_nature'] ?? '';
        $form['description'] = $form['bn_description'] ?? '';

        $form['template'] = $this->withDefaultImages($template_list, $form['bn_id_nature'] ?? '');
        $form['prepared'] = $this->prepareData($form);
        $form['bn_template'] = $this->encodeTemplate($form['template']);

        return $form;
    }

    /**
     * The JSON stored in a form page. A field keeps its template row when its JSON does not rebuild into the same field.
     */
    public function toPageBody(array $form, array $translations = []): array
    {
        $template_list = $this->parseTemplate($form['bn_template'] ?? '');
        $fields = [];
        $order = 0;
        foreach ($this->prepareData(['template' => $template_list]) as $index => $field) {
            $json = $this->storableJson($field);
            $json['order'] = $order;
            $json['field_class'] = get_class($field);
            $json['field_type'] = (new \ReflectionClass($field))->getShortName();
            if (!$this->rebuildsIdentically($json, $field)) {
                $json['template_row'] = $template_list[$index];
            }
            $key = $this->fieldKey($json, $order, $fields);
            $fields[$key] = $json;
            $order++;
        }

        return [
            'id' => (string)$form['bn_id_nature'],
            'title' => $form['bn_label_nature'] ?? '',
            'description' => $form['bn_description'] ?? '',
            'condition' => $form['bn_condition'] ?? '',
            'sem_template' => $form['bn_sem_template'] ?? '',
            'sem_reverse_template' => $form['bn_sem_reverse_template'] ?? '',
            'activitypub_enable' => (string)(int)$this->activityPubService->isEnabled($form),
            'only_one_entry' => ($form['bn_only_one_entry'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'only_one_entry_message' => $form['bn_only_one_entry_message'] ?? '',
            'lang' => $form['lang'] ?? $this->wiki->lang,
            'fields' => $fields,
            '__translation' => $translations,
        ];
    }

    protected function storableJson(BazarField $field): array
    {
        $json = json_decode(json_encode($field), true);
        unset($json['options']);

        return $json;
    }

    protected function rebuildsIdentically(array $json, BazarField $original): bool
    {
        try {
            $rebuilt = $this->fieldFactory->create($this->fieldClass($json)::mapToFieldArray($json));
        } catch (\Throwable $th) {
            return false;
        }

        return $rebuilt instanceof BazarField
            && get_class($rebuilt) === get_class($original)
            && $this->fieldState($rebuilt) == $this->fieldState($original);
    }

    /** Every scalar or array property of $field, services left out. */
    protected function fieldState(BazarField $field): array
    {
        $state = [];
        for ($class = new \ReflectionClass($field); $class !== false; $class = $class->getParentClass()) {
            foreach ($class->getProperties() as $property) {
                if ($property->isStatic() || !$property->isInitialized($field)) {
                    continue;
                }
                $value = $property->getValue($field);
                if (is_scalar($value) || is_null($value) || (is_array($value) && json_encode($value) !== false && !str_contains(json_encode($value), '{}'))) {
                    $state[$class->getName() . '::' . $property->getName()] = $value;
                }
            }
        }

        return $state;
    }

    protected function fieldKey(array $json, int $order, array $taken): string
    {
        $key = (string)($json['id'] ?? '');
        if ($key === '') {
            $key = (string)($json['name'] ?? '');
        }
        if ($key === '' || isset($taken[$key])) {
            $key = ($key === '' ? ($json['type'] ?? 'field') : $key) . '__' . $order;
        }

        return $key;
    }

    /** Saves form $form into the page $tag, keeping $translations. */
    protected function savePage(string $tag, array $form, array $translations): bool
    {
        $body = $this->toPageBody($form, $translations);

        return $this->pageManager->save($tag, json_encode($body), '', true) === 0;
    }

    /** The untranslated JSON body of the page $tag. */
    protected function storedBody(string $tag): array
    {
        $page = $this->pageManager->getOne($tag, null, false, true);
        $body = json_decode($page['body'] ?? '', true);

        return is_array($body) ? $body : [];
    }

    public function getAll(string $lang = 'default'): array
    {
        if ($lang !== 'default') {
            $forms = [];
            foreach ($this->pageManager->getManyFromTriple(self::TRIPLE_FORM_TYPE, $lang) as $page) {
                $form = $this->getFromRawData($page);
                if (!empty($form['bn_id_nature'])) {
                    $form['tag'] = $page['tag'];
                    $forms[$form['bn_id_nature']] = $form;
                }
            }

            return $forms;
        }
        if (!$this->cacheValidatedForAll) {
            foreach ($this->pageManager->getManyFromTriple(self::TRIPLE_FORM_TYPE, $lang) as $page) {
                $form = $this->getFromRawData($page);
                if (!empty($form['bn_id_nature'])) {
                    $form['tag'] = $page['tag'];
                    $this->cachedForms[$form['bn_id_nature']] = $form;
                }
            }
            $this->cacheValidatedForAll = true;
        }

        return array_filter(
            $this->cachedForms,
            function ($pForm, $pKey) {
                return is_array($pForm) && intval($pKey) . '' === $pKey . '';
            },
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function getAllIds(): array
    {
        return array_map('strval', array_keys($this->formTags()));
    }

    /** Form ids mapped to their titles. */
    public function getAllTitles(): array
    {
        return array_map(fn ($form) => $form['bn_label_nature'], $this->getAll());
    }

    public function getMany($formsIds, string $lang = 'default'): array
    {
        if (count($formsIds) == 0) {
            return $this->getAll($lang);
        }

        $results = [];
        foreach ($formsIds as $formId) {
            $results[$formId] = $this->getOne($formId, $lang);
        }

        return $results;
    }

    /** Tags of the entries of form $formId. */
    protected function entryTags($formId): array
    {
        $rows = $this->dbService->loadAll(
            'SELECT DISTINCT tag FROM ' . $this->dbService->prefixTable('pages')
            . 'WHERE tag IN (SELECT resource FROM ' . $this->dbService->prefixTable('triples')
            . 'WHERE property="' . TripleStore::TYPE_URI . '" AND value="fiche_bazar") AND body LIKE \'%"id_typeannonce":"' . $this->dbService->escape((string)$formId) . '"%\''
        );

        return array_column($rows, 'tag');
    }

    /**
     * Creates a form and returns its id, which differs from the requested one when that id is taken.
     */
    public function create($data)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (empty($data['bn_id_nature']) || $this->getOne($data['bn_id_nature'])) {
            $data['bn_id_nature'] = $this->findNewId();
        }
        $data['bn_id_nature'] = (string)intval($data['bn_id_nature']);
        $data['bn_template'] = $this->convertWithSpecialParameters($data['bn_template'] ?? '', $data['bn_id_nature']);

        $tag = $this->availableTag($data['bn_label_nature'] ?? '');
        $saved = $this->savePage($tag, $data, []);
        if ($saved) {
            $this->registerFormPage($tag);
        }
        $this->forgetCaches();

        return $saved ? $data['bn_id_nature'] : false;
    }

    /** A page tag made from $name that no page uses yet. */
    protected function availableTag($name): string
    {
        $base = \URLify::slug((string)$name) ?: 'form';
        $tag = $base;
        for ($counter = 0; !empty($this->pageManager->getOne($tag, null, false, true)); $counter++) {
            $tag = $base . $counter;
        }

        return $tag;
    }

    protected function registerFormPage(string $tag): void
    {
        $this->aclService->save($tag, 'read', '@admins');
        $this->aclService->save($tag, 'write', '@admins');
        $this->aclService->save($tag, 'comment', 'comments-closed');
        $this->tripleStore->create($tag, TripleStore::TYPE_URI, self::TRIPLE_FORM_TYPE, '', '');
    }

    /**
     * Stores a row of the `nature` table as a form page under the same id, and returns the page tag.
     * Throws, saving nothing, when the stored form would not rebuild into the same fields.
     */
    public function importLegacyForm(array $row): string
    {
        foreach ($row as $key => $value) {
            $row[$key] = _convert($value, 'ISO-8859-15');
        }
        $legacy = $this->getFromRawData($row);
        $body = $this->toPageBody($row);
        $rebuilt = $this->getFromRawData(['body' => json_encode($body)]);
        $describe = fn ($form) => array_map(fn ($field) => $this->storableJson($field), $form['prepared']);
        if ($describe($rebuilt) != $describe($legacy)) {
            throw new \Exception("Form {$row['bn_id_nature']} would not keep its fields once stored as a page");
        }

        $tag = $this->availableTag($row['bn_label_nature'] ?? '');
        if ($this->pageManager->save($tag, json_encode($body), '', true) !== 0) {
            throw new \Exception("Form {$row['bn_id_nature']} could not be saved into page {$tag}");
        }
        $this->registerFormPage($tag);
        $this->forgetCaches();

        return $tag;
    }

    /** Saves $data over the form it names by `bn_id_nature`, keeping the form's translations. */
    public function update($data)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $id = (string)($data['bn_id_nature'] ?? $data['id'] ?? '');
        $tag = $this->getPageTagFromId($id);
        if ($tag === null) {
            return false;
        }
        $stored = $this->storedBody($tag);
        $data['bn_id_nature'] = $id;
        $data['lang'] = $stored['lang'] ?? null;
        $data['bn_template'] = $this->convertWithSpecialParameters($data['bn_template'] ?? '', $id);

        $saved = $this->savePage($tag, $data, $stored['__translation'] ?? []);
        $this->forgetCaches();

        return $saved;
    }

    /**
     * Replaces the translations of form $formId with $translations: per language, `title`, `description`
     * and `fields.<key>.<label|helper|hint|default>`. Anything else, and any unsupported language, is dropped.
     */
    public function updateTranslations($formId, array $translations): bool
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $form = $this->getOne($formId, 'all');
        if (empty($form['tag'])) {
            return false;
        }
        $body = $this->storedBody($form['tag']);
        $purifier = $this->wiki->services->get(HtmlPurifierService::class);
        $clean = fn ($value) => is_scalar($value) && trim((string)$value) !== '' ? $purifier->cleanHTML((string)$value) : null;
        $langs = $this->wiki->config['supported_langs'] ?? [];
        $result = [];
        foreach ($translations as $lang => $values) {
            if (!in_array($lang, $langs, true) || !is_array($values)) {
                continue;
            }
            $sanitized = array_filter([
                'title' => $clean($values['title'] ?? null),
                'description' => $clean($values['description'] ?? null),
            ], fn ($value) => $value !== null);
            foreach ($values['fields'] ?? [] as $key => $properties) {
                if (!isset($body['fields'][$key]) || !is_array($properties)) {
                    continue;
                }
                foreach (self::TRANSLATABLE_FIELD_PROPERTIES as $property) {
                    if (($value = $clean($properties[$property] ?? null)) !== null) {
                        $sanitized['fields'][$key][$property] = $value;
                    }
                }
            }
            if (!empty($sanitized)) {
                $result[$lang] = $sanitized;
            }
        }
        $body['__translation'] = $result;
        $saved = $this->pageManager->save($form['tag'], json_encode($body), '', true) === 0;
        $this->forgetCaches();

        return $saved;
    }

    public function clone($id)
    {
        $data = $this->getOne($id, 'all');
        if (!empty($data)) {
            unset($data['bn_id_nature']);
            $data['bn_label_nature'] = $data['bn_label_nature'] . ' (' . _t('BAZ_DUPLICATE') . ')';

            return $this->create($data);
        }

        return false;
    }

    /** Deletes form $id, its page and all its entries. */
    public function delete($id)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (strval(intval($id)) != strval($id)) {
            return null;
        }
        $tag = $this->getPageTagFromId($id);
        $this->clear($id);
        if ($tag !== null) {
            $this->pageManager->deleteManyOrphaned([$tag]);
        }
        $this->forgetCaches();

        return true;
    }

    /** Deletes every entry of form $id. */
    public function clear($id)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $this->pageManager->deleteManyOrphaned($this->entryTags($id));
    }

    public function findNewId()
    {
        $ids = array_map('intval', array_filter(
            array_merge(array_keys($this->cachedForms), array_keys($this->formTags())),
            fn ($key) => intval($key) . '' === $key . ''
        ));

        $lowIds = array_filter($ids, fn ($id) => $id < 1000);
        $vCandidate = (empty($lowIds) ? 1 : max($lowIds)) + 1;
        if ($vCandidate < 999) {
            return $this->firstFreeIdFrom($vCandidate);
        }

        $highIds = array_filter($ids, fn ($id) => $id > 10000);

        return $this->firstFreeIdFrom((empty($highIds) ? 10001 : max($highIds)) + 1);
    }

    /**
     * The first form id from the candidate on that no form uses.
     */
    private function firstFreeIdFrom(int $candidate): int
    {
        while (!empty($this->getOne((string)$candidate))) {
            $candidate++;
        }

        return $candidate;
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
        $tableau_template = [];
        $nblignes = 0;

        $chaine = explode("\n", $raw);
        foreach ($chaine as $ligne) {
            $ligne = trim($ligne);
            if (!empty($ligne) && !(strrpos($ligne, '#', -strlen($ligne)) !== false)) {
                $tablignechampsformulaire = array_map('trim', explode('***', $ligne));

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
                    $new_line .= is_array($value) ? join(',', $value) : $value;
                }
                $new_line .= '***';
            }
            $new_template_list[] = $new_line;
        }
        $template = implode("\r\n", array_map('trim', $new_template_list));

        return $template;
    }

    /**
     * The field names of a form that cannot be used as a property name, so cannot be stored or searched.
     */
    public function invalidPropertyNames(array $form): array
    {
        $names = [];
        foreach ($form['prepared'] ?? [] as $field) {
            $name = $field->getPropertyName();
            if (is_string($name) && $name !== '' && preg_match(BazarField::PROPERTY_NAME_PATTERN, $name) !== 1) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    public function prepareData($form)
    {
        $i = 0;
        $prepared = $result = [];

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
            $i++;
        }

        return $prepared;
    }

    /*
        Add a form to the cache if it is not existing
    */

    public function cacheForm($pFormId, $pForm)
    {
        $this->cachedForms[$pFormId] = $pForm;
    }

    /**
     * return field from field name or property name.
     */
    public function findFieldFromNameOrPropertyName(?string $name, ?string $formId): ?BazarField
    {
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

    public function isAvailableOnlyOneEntryOption(): bool
    {
        return true;
    }

    public function isAvailableOnlyOneEntryMessage(): bool
    {
        return true;
    }

    public function findTypeOfFields($formId, array $fieldTypes): array
    {
        $res = [];
        $form = $this->getOne($formId);
        if (empty($form)) {
            return $res;
        }

        foreach ($form['prepared'] as $field) {
            $class = get_class($field);
            $class = explode('\\', $class);
            $class = array_pop($class);
            if (in_array($class, $fieldTypes)) {
                $res[] = $field;
            }
        }

        return $res;
    }

    public function findFieldWithId(array $formId, $fieldId)
    {
        $res = [];
        foreach ($formId as $fId) {
            $form = $this->getOne($fId);
            if (empty($form)) {
                continue;
            }
            foreach ($form['prepared'] as $field) {
                if ($field->getPropertyName() === $fieldId) {
                    return $field;
                }
            }
        }

        return $res;
    }
}
