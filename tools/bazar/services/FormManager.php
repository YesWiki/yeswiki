<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\ImageField;
use YesWiki\Core\Entity\Translations;
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

    /** The attributes of a field a translator retypes, by field type; any other type offers DEFAULT_TRANSLATABLE_ATTRIBUTES. */
    public const TRANSLATABLE_ATTRIBUTES = [
        'labelhtml' => ['form_text', 'view_text'],
        'label' => ['form_text', 'view_text'],
        'tabs' => ['form_titles', 'view_titles'],
        'onglets' => ['form_titles', 'view_titles'],
    ];

    public const DEFAULT_TRANSLATABLE_ATTRIBUTES = ['label', 'hint', 'default'];

    /** Doryphore's names for a form's properties => the names a form page stores them under, the ones Ectoplasme uses. */
    public const LEGACY_BODY_KEYS = [
        'bn_id_nature' => 'id',
        'bn_ce_i18n' => 'lang',
        'bn_label_nature' => 'label',
        'bn_template' => 'template',
        'bn_description' => 'description',
        'bn_condition' => 'condition',
        'bn_sem_template' => 'sem_template',
        'bn_sem_reverse_template' => 'sem_reverse_template',
        'bn_only_one_entry' => 'only_one_entry',
        'bn_only_one_entry_message' => 'only_one_entry_message',
        'bn_activitypub_enable' => 'activitypub_enable',
    ];

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

    /** One template row => one field object keyed by attribute name, or null when it has no type; empty attributes are left out. */
    public function rowToObject(array $row): ?array
    {
        $type = trim((string)($row[0] ?? ''));
        if ($type === '') {
            return null;
        }
        $indexToKey = $this->fieldFactory->getAttributeIndexToKeyMap($type);
        $object = ['type' => $type];
        foreach ($row as $index => $value) {
            if (!is_int($index) || $index < 1) {
                continue;
            }
            $value = trim(is_array($value) ? implode(',', $value) : (string)$value);
            if ($value !== '') {
                $object[$indexToKey[$index] ?? (string)$index] = $value;
            }
        }

        return $object;
    }

    /** One field object => one template row padded to 16 cells, or null when it has no type. */
    public function objectToRow(array $object): ?array
    {
        $type = trim((string)($object['type'] ?? $object[0] ?? ''));
        if ($type === '') {
            return null;
        }
        $keyToIndex = $this->fieldFactory->getAttributeKeyToIndexMap($type);
        $row = array_fill(0, 16, '');
        $row[0] = $type;
        foreach ($object as $key => $value) {
            if ($key === 'type' || $key === 0 || $key === '0') {
                continue;
            }
            $index = $keyToIndex[$key] ?? (is_numeric($key) ? (int)$key : null);
            if ($index !== null && $index >= 1) {
                $row[$index] = trim(is_array($value) ? implode(',', $value) : (string)$value);
            }
        }
        ksort($row);

        return $row;
    }

    /** Template rows from any template a caller hands over: `***` syntax, a JSON array of field objects, rows, or field objects. */
    public function templateRows($template): array
    {
        if (is_string($template)) {
            return $this->parseTemplate($template);
        }
        $rows = [];
        foreach (is_array($template) ? $template : [] as $field) {
            if (!is_array($field)) {
                continue;
            }
            $row = !array_key_exists('type', $field) && array_key_exists(0, $field) ? $field : $this->objectToRow($field);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** Form properties under the names a form page stores them with, whichever names the caller used; Doryphore's `bn_*` names win. */
    protected function canonicalProperties(array $data): array
    {
        foreach (self::LEGACY_BODY_KEYS as $legacyKey => $key) {
            if (array_key_exists($legacyKey, $data)) {
                $data[$key] = $data[$legacyKey];
                unset($data[$legacyKey]);
            }
        }
        if (!isset($data['label']) && isset($data['title'])) {
            $data['label'] = $data['title'];
        }
        if (isset($data['lang'])) {
            $data['lang'] = Wiki::sanitizeLang($data['lang'], '') ?: null;
        }

        return $data;
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
     * A form ready for use from a form page, a `nature` row or posted data: Doryphore's `bn_*` names, `template` rows,
     * `prepared` fields and `bn_template` in `***` syntax, plus the names a form page stores (`id`, `label`, `description`).
     */
    public function getFromRawData($form)
    {
        if (isset($form['body'])) {
            $form = json_decode($form['body'], true);
            if (!is_array($form)) {
                return null;
            }
        } else {
            foreach ($form as $key => $value) {
                $form[$key] = _convert($value, 'ISO-8859-15');
            }
        }
        $properties = $this->canonicalProperties($form);
        $id = (string)($properties['id'] ?? '');
        $activitypub = $properties['activitypub_enable'] ?? '0';
        $rows = $this->withDefaultImages($this->templateRows($properties['template'] ?? ''), $id);

        $form = [
            'bn_id_nature' => $id,
            'bn_label_nature' => (string)($properties['label'] ?? ''),
            'bn_description' => (string)($properties['description'] ?? ''),
            'bn_condition' => (string)($properties['condition'] ?? ''),
            'bn_sem_template' => (string)($properties['sem_template'] ?? ''),
            'bn_sem_reverse_template' => (string)($properties['sem_reverse_template'] ?? ''),
            'bn_activitypub_enable' => (string)(int)in_array($activitypub, [1, true, '1'], true),
            'bn_only_one_entry' => ($properties['only_one_entry'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'bn_only_one_entry_message' => (string)($properties['only_one_entry_message'] ?? ''),
            'id' => $id,
            'label' => (string)($properties['label'] ?? ''),
            'title' => (string)($properties['label'] ?? ''),
            'description' => (string)($properties['description'] ?? ''),
            'lang' => $properties['lang'] ?? null,
            Translations::BODY_KEY => $properties[Translations::BODY_KEY] ?? [],
            'template' => $rows,
        ];
        $form['prepared'] = $this->prepareData($form);
        $form['bn_template'] = $this->encodeTemplate($rows);

        return $form;
    }

    /** The JSON a form page stores, under the names Ectoplasme stores a form with; `$translations` is the stored translation tree. */
    public function toPageBody(array $data, array $translations = []): array
    {
        $properties = $this->canonicalProperties($data);
        $template = [];
        foreach ($this->templateRows($properties['template'] ?? '') as $row) {
            $object = $this->rowToObject($row);
            if ($object !== null) {
                $template[] = $object;
            }
        }
        $body = [
            'id' => (string)($properties['id'] ?? ''),
            'lang' => $properties['lang'] ?? $this->wiki->lang,
            'label' => (string)($properties['label'] ?? ''),
            'template' => $template,
            'description' => (string)($properties['description'] ?? ''),
            'condition' => (string)($properties['condition'] ?? ''),
            'sem_template' => (string)($properties['sem_template'] ?? ''),
            'sem_reverse_template' => (string)($properties['sem_reverse_template'] ?? ''),
            'only_one_entry' => ($properties['only_one_entry'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'only_one_entry_message' => (string)($properties['only_one_entry_message'] ?? ''),
            'activitypub_enable' => (string)(int)$this->activityPubService->isEnabled(['bn_activitypub_enable' => (string)($properties['activitypub_enable'] ?? '0')]),
        ];
        if ($translations !== []) {
            $body[Translations::BODY_KEY] = $translations;
        }

        return $body;
    }

    /** Saves form $data into the page $tag, carrying $stored's handles and translations onto the new fields. */
    protected function savePage(string $tag, array $data, array $stored = []): bool
    {
        $body = $this->toPageBody($data);
        $body['template'] = $this->withHandles($body['template'], is_array($stored['template'] ?? null) ? $stored['template'] : []);

        return $this->pageManager->save($tag, $this->encodeBody(Translations::pruned(Translations::carriedOver($stored, $body))), '', true) === 0;
    }

    /**
     * $template with a stable handle on every field, the way Ectoplasme gives one: taken from the stored field with the same name,
     * else from an unnamed stored field of the same type and content, else of the same type and rank, else new.
     */
    protected function withHandles(array $template, array $stored): array
    {
        $storedHandles = array_values(array_filter(array_map(fn ($field) => is_array($field) ? (string)($field['handle'] ?? '') : '', $stored)));
        $used = [];
        $claim = function (?string $handle) use (&$used): ?string {
            if ($handle === null || $handle === '' || isset($used[$handle])) {
                return null;
            }
            $used[$handle] = true;

            return $handle;
        };
        $content = fn (array $field) => array_diff_key($field, ['handle' => 0]);

        $byName = [];
        foreach ($stored as $field) {
            if (is_array($field) && ($field['name'] ?? '') !== '' && !empty($field['handle'])) {
                $byName[$field['name']] ??= $field['handle'];
            }
        }
        foreach ($template as $index => $field) {
            $handle = ($field['name'] ?? '') !== '' ? $claim($byName[$field['name']] ?? null) : null;
            if ($handle === null && ($field['name'] ?? '') === '') {
                foreach ($stored as $candidate) {
                    if (is_array($candidate) && ($candidate['name'] ?? '') === '' && $content($candidate) == $content($field) && ($handle = $claim($candidate['handle'] ?? null)) !== null) {
                        break;
                    }
                }
            }
            if ($handle !== null) {
                $template[$index]['handle'] = $handle;
            }
        }
        foreach ($template as $index => $field) {
            if (isset($field['handle']) || ($field['name'] ?? '') !== '') {
                continue;
            }
            foreach ($stored as $candidate) {
                if (is_array($candidate) && ($candidate['name'] ?? '') === '' && ($candidate['type'] ?? null) === $field['type'] && !isset($used[$candidate['handle'] ?? '']) && ($handle = $claim($candidate['handle'] ?? null)) !== null) {
                    $template[$index]['handle'] = $handle;
                    break;
                }
            }
        }
        foreach ($template as $index => $field) {
            if (!isset($field['handle'])) {
                do {
                    $handle = bin2hex(random_bytes(4));
                } while (isset($used[$handle]) || in_array($handle, $storedHandles, true));
                $template[$index]['handle'] = $claim($handle);
            }
        }

        return $template;
    }

    /** The attributes of a field of $type a translator retypes, under the field's own names. */
    public function translatableAttributes(string $type): array
    {
        return self::TRANSLATABLE_ATTRIBUTES[$type] ?? self::DEFAULT_TRANSLATABLE_ATTRIBUTES;
    }

    /**
     * What form $formId offers to translate, keyed by the field's name or, for a field without one, its handle:
     * a caption, and the source text of each translatable attribute under the name its translations are stored with.
     */
    public function translatableFields($formId): array
    {
        $form = $this->getOne($formId, 'all');
        if (empty($form['tag'])) {
            return [];
        }
        $fields = [];
        foreach ($this->storedBody($form['tag'])['template'] ?? [] as $field) {
            $identity = (string)($field['name'] ?? '') ?: (string)($field['handle'] ?? '');
            if ($identity === '' || isset($fields[$identity])) {
                continue;
            }
            $attributes = [];
            foreach ($this->translatableAttributes((string)($field['type'] ?? '')) as $attribute) {
                if (trim((string)($field[$attribute] ?? '')) !== '') {
                    $attributes[$attribute === 'hint' ? 'helper' : $attribute] = (string)$field[$attribute];
                }
            }
            if ($attributes !== []) {
                $caption = ($field['name'] ?? '') !== '' ? $field['name'] : $field['type'] . ' : ' . mb_strimwidth(trim(strip_tags((string)reset($attributes))), 0, 60, '…');
                $fields[$identity] = ['caption' => $caption, 'attributes' => $attributes];
            }
        }

        return $fields;
    }

    protected function encodeBody(array $body): string
    {
        return json_encode((object)Translations::forEncoding($body));
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
        $data = $this->canonicalProperties($data);
        if (empty($data['id']) || $this->getOne($data['id'])) {
            $data['id'] = $this->findNewId();
        }
        $data['id'] = (string)intval($data['id']);
        $data['template'] = $this->convertWithSpecialParameters($this->encodeTemplate($this->templateRows($data['template'] ?? '')), $data['id']);

        $tag = $this->availableTag($data['label'] ?? '');
        $saved = $this->savePage($tag, $data);
        if ($saved) {
            $this->registerFormPage($tag);
        }
        $this->forgetCaches();

        return $saved ? $data['id'] : false;
    }

    /** A page tag made from $name that no page uses yet. */
    protected function availableTag($name): string
    {
        $base = \URLify::slug((string)$name) ?: 'form';
        $tag = $base;
        for ($counter = 2; !empty($this->pageManager->getOne($tag, null, false, true)); $counter++) {
            $tag = $base . '-' . $counter;
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
     * Stores a row of the `nature` table as a form page under the same id, in the page $tag when given, and returns the tag.
     * Throws, saving nothing, when the stored form would not rebuild into the same fields.
     */
    public function importLegacyForm(array $row, ?string $tag = null): string
    {
        foreach ($row as $key => $value) {
            $row[$key] = _convert($value, 'ISO-8859-15');
        }
        $legacy = $this->getFromRawData($row);
        $rebuilt = $this->getFromRawData(['body' => $this->encodeBody($this->toPageBody($row))]);
        $describe = fn ($form) => array_map(fn ($field) => json_decode(json_encode($field), true), array_values($form['prepared']));
        if ($describe($rebuilt) != $describe($legacy)) {
            throw new \Exception("Form {$row['bn_id_nature']} would not keep its fields once stored as a page");
        }

        $isNew = $tag === null;
        $tag ??= $this->availableTag($row['bn_label_nature'] ?? '');
        if (!$this->savePage($tag, $row)) {
            throw new \Exception("Form {$row['bn_id_nature']} could not be saved into page {$tag}");
        }
        if ($isNew) {
            $this->registerFormPage($tag);
        }
        $this->forgetCaches();

        return $tag;
    }

    /** Saves $data over the form it names by `bn_id_nature` or `id`, keeping the form's translations. */
    public function update($data)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $data = $this->canonicalProperties($data);
        $id = (string)($data['id'] ?? '');
        $tag = $this->getPageTagFromId($id);
        if ($tag === null) {
            return false;
        }
        $stored = $this->storedBody($tag);
        $data['lang'] = $stored['lang'] ?? $data['lang'] ?? null;
        $data['template'] = $this->convertWithSpecialParameters($this->encodeTemplate($this->templateRows($data['template'] ?? '')), $id);

        $saved = $this->savePage($tag, $data, $stored);
        $this->forgetCaches();

        return $saved;
    }

    /**
     * Replaces the translations of form $formId with $translations, given per language as `title`, `description`
     * and `fields.<name or handle>.<attribute>` for what translatableFields() offers. Only the form's translation targets are replaced:
     * a language that is not one keeps what it had, anything else is dropped, and with no target nothing is saved.
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
        $targets = $this->wiki->translationTargets($form['lang']);
        if ($targets === []) {
            return false;
        }
        $body = $this->storedBody($form['tag']);
        $purifier = $this->wiki->services->get(HtmlPurifierService::class);
        $offered = $this->translatableFields($form['bn_id_nature']);
        foreach ($targets as $lang) {
            $values = is_array($translations[$lang] ?? null) ? $translations[$lang] : [];
            $paths = [
                'label' => $values['title'] ?? null,
                'description' => $values['description'] ?? null,
            ];
            foreach (is_array($values['fields'] ?? null) ? $values['fields'] : [] as $identity => $properties) {
                foreach (array_keys($offered[$identity]['attributes'] ?? []) as $stored) {
                    $attribute = $stored === 'helper' ? 'hint' : $stored;
                    $paths["template.{$identity}.{$attribute}"] = is_array($properties) ? ($properties[$stored] ?? null) : null;
                }
            }
            $clean = [];
            foreach ($paths as $path => $text) {
                if (is_scalar($text) && trim((string)$text) !== '') {
                    $clean[$path] = $purifier->cleanHTML((string)$text);
                }
            }
            $body = Translations::with($body, $lang, $clean);
        }
        $saved = $this->pageManager->save($form['tag'], $this->encodeBody(Translations::pruned($body)), '', true) === 0;
        $this->forgetCaches();

        return $saved;
    }

    public function clone($id)
    {
        $data = $this->getOne($id, 'all');
        if (!empty($data)) {
            unset($data['bn_id_nature'], $data['id'], $data[Translations::BODY_KEY]);
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
        $raw = (string)$raw;
        if (str_starts_with(ltrim($raw), '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $this->templateRows($decoded);
            }
        }
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
                        while (count($tableau_template[$nblignes]) > 16 && end($tableau_template[$nblignes]) === '') {
                            array_pop($tableau_template[$nblignes]);
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
