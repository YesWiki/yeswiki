<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use YesWiki\Core\Attach;
use YesWiki\Core\Field\BazarField;
use YesWiki\Wiki;
use YesWiki\Search\Service\SearchManager;
use YesWiki\Identity\Service\AclService;

class FormManager
{
    // Forms are `pages` rows typed via this triple, the same convention EntryManager
    // already uses for bazar entries (TRIPLES_ENTRY_ID).
    public const TRIPLES_FORM_TYPE = 'form';

    // A form's tag is renameable; when it's renamed, the old tag is kept resolvable via
    // this triple (resource=old tag, value=new tag) so previously published references to
    // it (e.g. an ActivityPub actor URL that was ever built from the tag rather than the
    // stable numeric id) don't dead-end. See ADR / CONTEXT.md "Forms keep a stable
    // identifier distinct from their (renameable) tag".
    public const FORMER_TAG_URI = 'http://outils-reseaux.org/_vocabulary/formerTag';

    // bounds resolveTag()'s alias-chain walk (renamed twice, three times, ...) so a cycle
    // (which should never happen, but shouldn't be trusted blindly either) can't hang
    private const MAX_ALIAS_HOPS = 10;

    protected $wiki;
    protected $dbService;
    protected $activityPubService;
    protected $httpSignatureService;
    protected $entryManager;
    protected $hibernationService;
    protected $fieldFactory;
    protected $params;
    protected $pageManager;
    protected $tripleStore;
    protected $aclService;
    protected $cachedForms;
    protected $cacheValidatedForAll;
    protected $attach;

    public function __construct(
        Wiki $wiki,
        DbService $dbService,
        EntryManager $entryManager,
        FieldFactory $fieldFactory,
        ParameterBagInterface $params,
        HibernationService $hibernationService,
        ActivityPubService $activityPubService,
        HttpSignatureService $httpSignatureService,
        PageManager $pageManager,
        TripleStore $tripleStore,
        AclService $aclService,
    ) {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->activityPubService = $activityPubService;
        $this->httpSignatureService = $httpSignatureService;
        $this->entryManager = $entryManager;
        $this->fieldFactory = $fieldFactory;
        $this->params = $params;
        $this->pageManager = $pageManager;
        $this->tripleStore = $tripleStore;
        $this->aclService = $aclService;

        $this->cachedForms = [];
        $this->cacheValidatedForAll = false;
        $this->hibernationService = $hibernationService;
        $this->attach = new Attach($this->wiki);
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

    /**
     * Write-side handling of image fields' default image: the designer posts the
     * default as `filename|data:image/...;base64,...`; the base64 part is written to
     * files/defaultimage{id}_{name}.jpg and only the filename stays in the stored
     * field object. Operates on (and returns) the native array of field objects.
     */
    protected function convertWithSpecialParameters(array $template, $id)
    {
        foreach ($template as $index => $fieldObject) {
            if (($fieldObject['type'] ?? '') !== 'image') {
                continue;
            }
            $basePath = $this->getBasePath();
            $default_image_prefix = "defaultimage{$id}_" . ($fieldObject['name'] ?? '');
            $this->cleanCacheDefaultImage($default_image_prefix);
            $default_image_filename = $basePath . $default_image_prefix . '.jpg';
            $default_image = explode('|', $fieldObject['image_default'] ?? '');
            if (count($default_image) == 2) {
                $fieldObject['image_default'] = $default_image[0];
                $imgext = explode('image/', explode(';', $default_image[1])[0])[1];
                $tmpFile = tempnam('cache', 'dfltimg');
                $tempFile = $tmpFile . '.' . $imgext;
                rename($tmpFile, $tempFile);
                try {
                    $ifp = fopen($tempFile, 'wb');
                    fwrite($ifp, base64_decode(explode(',', $default_image[1])[1]));
                    fclose($ifp);
                    $this->attach->redimensionner_image($tempFile, $default_image_filename, $fieldObject['image_height'] ?? '', $fieldObject['image_width'] ?? '', 'crop');
                } finally {
                    unlink($tempFile);
                }
            } else {
                unset($fieldObject['image_default']);
                if (file_exists($default_image_filename)) {
                    unlink($default_image_filename);
                }
            }
            $template[$index] = $fieldObject;
        }

        return $template;
    }

    /**
     * Read-side counterpart: embeds the stored default image back into each image
     * field object (`image_default` becomes `filename|data:image/jpg;base64,...`) so
     * the designer and API consumers can display it.
     */
    protected function prepare_with_special_parameters($form)
    {
        $basePath = $this->getBasePath();
        $template = $form['template'];
        foreach ($template as $index => $fieldObject) {
            if (($fieldObject['type'] ?? '') !== 'image') {
                continue;
            }
            $default_image_filename = $basePath . "defaultimage{$form['id']}_" . ($fieldObject['name'] ?? '') . '.jpg';
            if (file_exists($default_image_filename)) {
                $fieldObject['image_default'] = ($fieldObject['image_default'] ?? '') . '|data:image/jpg;base64,' . base64_encode(file_get_contents($default_image_filename));
            } else {
                unset($fieldObject['image_default']);
            }
            $template[$index] = $fieldObject;
        }

        return $template;
    }

    /**
     * Resolves a form identifier (numeric stable id, current tag, or a former tag left
     * behind by a rename) to the form's current `pages.tag`, or null if none matches.
     */
    private function resolveTag(string $formId, int $aliasHops = 0): ?string
    {
        if ($aliasHops > self::MAX_ALIAS_HOPS) {
            return null;
        }
        if (is_numeric($formId)) {
            return $this->resolveTagFromNumericId($formId);
        }
        if ($this->pageManager->tagExists($formId)) {
            return $formId;
        }
        $newerTag = $this->tripleStore->getOne($formId, self::FORMER_TAG_URI, '', '');
        if ($newerTag === null) {
            return null;
        }

        return $this->resolveTag($newerTag, $aliasHops + 1);
    }

    /**
     * Legacy `bn_*` body keys => their plain-English replacements (ticket 27,
     * ADR-0010). The RenameFormBodyKeys migration converts stored latest revisions;
     * this map is the read-side insurance for anything older (pre-migration bodies,
     * old form revisions loaded for history views or reverts).
     */
    public const LEGACY_BODY_KEYS = [
        'bn_id_nature' => 'id',
        'bn_ce_i18n' => 'lang',
        'bn_label_nature' => 'label',
        'bn_template' => 'template',
        'bn_description' => 'description',
        'bn_sem_context' => 'sem_context',
        'bn_sem_type' => 'sem_type',
        'bn_sem_use_template' => 'sem_use_template',
        'bn_sem_template' => 'sem_template',
        'bn_sem_reverse_template' => 'sem_reverse_template',
        'bn_only_one_entry' => 'only_one_entry',
        'bn_only_one_entry_message' => 'only_one_entry_message',
        'bn_condition' => 'condition',
    ];

    private function resolveTagFromNumericId(string $id): ?string
    {
        $jsonExtract = $this->dbService->jsonExtract('body', '$.id');
        $sql = "SELECT tag FROM {$this->dbService->prefixTable('pages')}
            WHERE latest = 'Y' AND {$jsonExtract} = '" . $this->dbService->escape((string)(int)$id) . "'
            LIMIT 1";
        $row = $this->dbService->loadSingle($sql);

        return $row['tag'] ?? null;
    }

    private function resolveIdFromTag(string $tag): ?string
    {
        $jsonExtract = $this->dbService->jsonExtract('body', '$.id');
        $sql = "SELECT {$jsonExtract} AS id FROM {$this->dbService->prefixTable('pages')}
            WHERE tag = '" . $this->dbService->escape($tag) . "' AND latest = 'Y'
            LIMIT 1";
        $row = $this->dbService->loadSingle($sql);

        return $row['id'] ?? null;
    }

    /**
     * Converts a fetched `pages` row (as returned by PageManager) for a form into the
     * flat form array (plain-English keys, ADR-0010). ActivityPub credentials, which
     * live in `metadata.activitypub` (not `body`, so they aren't echoed by generic
     * JSON/API dumps of a page's body), are merged back in under `activitypub_*` keys
     * so no consumer needs to know where they're actually stored.
     */
    private function pageToFormArray(array $page): array
    {
        $body = json_decode($page['body'] ?? '', true) ?? [];
        $activitypub = $page['metadatas']['activitypub'] ?? [];

        // read-side legacy-key insurance (pre-migration bodies, old revisions)
        foreach (self::LEGACY_BODY_KEYS as $legacyKey => $key) {
            if (array_key_exists($legacyKey, $body) && !array_key_exists($key, $body)) {
                $body[$key] = $body[$legacyKey];
            }
            unset($body[$legacyKey]);
        }

        // the template is a native JSON array of field objects; anything older (a
        // legacy `***` or JSON string) is normalized through the codec
        if (!is_array($body['template'] ?? null)) {
            $body['template'] = json_decode($this->normalizeTemplate($body['template'] ?? ''), true) ?? [];
        }

        $body['activitypub_enable'] = (string)($activitypub['enabled'] ?? '0');
        $body['activitypub_username'] = $activitypub['username'] ?? '';
        $body['activitypub_private_key'] = $activitypub['private_key'] ?? null;
        $body['activitypub_public_key'] = $activitypub['public_key'] ?? null;
        $body['tag'] = $page['tag'];

        return $body;
    }

    public function getOne($formId): ?array
    {
        if (isset($this->cachedForms[$formId])) {
            return $this->cachedForms[$formId];
        }

        $tag = $this->resolveTag((string)$formId);
        if ($tag === null) {
            return null;
        }

        $page = $this->pageManager->getOne($tag, null, true, true);
        if (!$page) {
            return null;
        }

        $form = $this->getFromRawData($this->pageToFormArray($page));

        $this->cachedForms[$formId] = $form;
        if (!empty($form['id'])) {
            $this->cachedForms[$form['id']] = $form;
        }

        return $form;
    }

    /**
     * Builds the full in-memory form from raw data (a pageToFormArray() result, or a
     * POST from the form editor): `template` normalized to the native array of field
     * objects (with image defaults embedded for display), `prepared` built from it.
     * The positional arrays feeding the field constructors stay internal -- they are
     * neither stored on the form nor exposed by the API (ADR-0010).
     */
    public function getFromRawData($form)
    {
        if (!is_array($form['template'] ?? null)) {
            $form['template'] = json_decode($this->normalizeTemplate($form['template'] ?? ''), true) ?? [];
        }
        $form['template'] = $this->prepare_with_special_parameters($form);
        $form['prepared'] = $this->prepareData($form);

        return $form;
    }

    public function getAll(): array
    {
        if (!$this->cacheValidatedForAll) {
            $triples = $this->tripleStore->getMatching(null, TripleStore::TYPE_URI, self::TRIPLES_FORM_TYPE);
            foreach ($triples as $triple) {
                $page = $this->pageManager->getOne($triple['resource'], null, true, true);
                if (!$page) {
                    continue;
                }
                $form = $this->getFromRawData($this->pageToFormArray($page));
                if (!empty($form['id'])) {
                    // save only not empty formId
                    $this->cachedForms[$form['id']] = $form;
                }
            }
            $this->cacheValidatedForAll = true;
        }

        return array_filter(
            $this->cachedForms,
            // require a valid numeric-id key *and* an actual form array : consumers (e.g.
            // SearchManager::searchWithLists()) expect every entry to be a real form
            function ($pForm, $pKey) {
                return is_array($pForm) && intval($pKey) . '' === $pKey . '';
            },
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function getAllIds(): array
    {
        if ($this->cacheValidatedForAll) {
            return array_keys($this->getAll());
        }

        $triples = $this->tripleStore->getMatching(null, TripleStore::TYPE_URI, self::TRIPLES_FORM_TYPE);
        $ids = [];
        foreach ($triples as $triple) {
            $id = $this->resolveIdFromTag($triple['resource']);
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getMany($formsIds): array
    {
        if (count($formsIds) == 0) {
            return $this->getAll();
        }

        $results = [];

        foreach ($formsIds as $formId) {
            if (empty($this->cachedForms[$formId])) {
                $form = $this->getOne($formId);
                // don't persist a "form not found" result into the shared cache : a
                // subsequent getAll() only overwrites cache entries for ids that actually
                // exist as form pages, so a cached null here would otherwise leak into
                // every later getAll() call for the rest of the request
                if ($form !== null) {
                    $this->cachedForms[$formId] = $form;
                }
            } else {
                $form = $this->cachedForms[$formId];
            }
            $results[$formId] = $form;
        }

        return $results;
    }

    /**
     * Builds the storable `body` array (everything except ActivityPub credentials, which
     * live in metadata -- see buildActivitypubMetadata()) from raw `bn_*`-keyed input
     * (either an admin form submission or another form's data via clone()). Fields that
     * aren't part of the admin-editable set (bn_sem_context/type/use_template, historically
     * seeded, not exposed in the edit UI) are included only if present in $data, so that
     * update()'s array_merge() over the existing body leaves them untouched rather than
     * wiping them on every edit.
     */
    private function buildBody(array $data): array
    {
        $body = [
            'id' => (string)$data['id'],
            'lang' => $data['lang'] ?? 'fr-FR',
            'label' => $data['label'] ?? '',
            'template' => $this->templateToStorage($data['template'] ?? ''),
            'description' => $data['description'] ?? '',
            // entry_title_template can never be empty (ADR-0010): the historical
            // implicit convention (a visitor-typed bf_titre field) is its default
            'entry_title_template' => trim((string)($data['entry_title_template'] ?? '')) ?: FormPropertiesService::DEFAULT_TITLE_TEMPLATE,
            'sem_template' => $data['sem_template'] ?? '',
            'sem_reverse_template' => $data['sem_reverse_template'] ?? '',
            'only_one_entry' => (isset($data['only_one_entry']) && $data['only_one_entry'] === 'Y') ? 'Y' : 'N',
            'only_one_entry_message' => empty($data['only_one_entry_message']) ? '' : $data['only_one_entry_message'],
            'condition' => $data['condition'] ?? '',
        ];
        // the other entry_* form properties are included when present, so update()'s
        // array_merge over the existing body leaves unposted ones untouched
        foreach (FormPropertiesService::OPTIONAL_PROPERTIES as $property) {
            if (isset($data[$property])) {
                $body[$property] = $data[$property];
            }
        }
        foreach (['sem_context', 'sem_type', 'sem_use_template'] as $legacySeedField) {
            if (isset($data[$legacySeedField])) {
                $body[$legacySeedField] = $data[$legacySeedField];
            }
        }

        return $body;
    }

    /**
     * Builds the `metadata.activitypub` array, generating a fresh keypair the first time a
     * form is enabled for ActivityPub and preserving the existing one afterwards (the admin
     * edit form has no input for the keys themselves, only for enable/username).
     */
    private function buildActivitypubMetadata(array $data, array $existingActivitypub): array
    {
        $enabled = (int)$this->activityPubService->isEnabled($data);
        $activitypub = [
            'enabled' => (string)$enabled,
            'username' => $data['activitypub_username'] ?? ($existingActivitypub['username'] ?? ''),
            'private_key' => $existingActivitypub['private_key'] ?? null,
            'public_key' => $existingActivitypub['public_key'] ?? null,
        ];
        if ($enabled && empty($activitypub['private_key'])) {
            [$privateKey, $publicKey] = $this->httpSignatureService->generateKeyPair();
            $activitypub['private_key'] = $privateKey;
            $activitypub['public_key'] = $publicKey;
        }

        return $activitypub;
    }

    /**
     * New form tags are lowercase slugs (ADR-0010); existing tags are never rewritten.
     */
    private function slugify(string $title): string
    {
        $slug = (new AsciiSlugger())->slug($title)->lower()->toString();

        return $slug !== '' ? $slug : 'form';
    }

    private function encodeBody(array $body): string
    {
        return json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // TODO Pass a Form object instead of a raw array
    public function create($data)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        // If ID is not set or if it is already used, find a new ID
        if (empty($data['id']) || $this->getOne($data['id'])) {
            $data['id'] = $this->findNewId();
        }

        // Canonicalize the template and process any posted default images
        $data['template'] = $this->convertWithSpecialParameters($this->templateToStorage($data['template'] ?? ''), $data['id']);

        $tag = $this->pageManager->suggestFreeTag($this->slugify($data['label'] ?? ''));

        // reset cache
        $this->cacheValidatedForAll = false;

        $saved = $this->pageManager->save($tag, $this->encodeBody($this->buildBody($data)), '', true);

        if ($saved === 0) {
            $this->pageManager->setMetadata($tag, ['activitypub' => $this->buildActivitypubMetadata($data, [])]);
            $this->aclService->save($tag, 'write', '@admins');
            $this->tripleStore->create($tag, TripleStore::TYPE_URI, self::TRIPLES_FORM_TYPE, '', '');
        }

        return $saved;
    }

    /**
     * Overwrites a form's stored ActivityPub keypair, keeping `enabled`/`username` as
     * already set. Used by MigrateNatureToPages to restore a form's real, previously
     * published keypair after create() -- which has no way to know a "new" form is actually
     * an existing one being migrated, and so always generates a fresh keypair -- would
     * otherwise silently rotate it out from under any already-federating form.
     */
    public function setActivitypubKeypair($formId, string $privateKey, string $publicKey): void
    {
        $tag = $this->resolveTag((string)$formId);
        if ($tag === null) {
            throw new \Exception("Cannot set ActivityPub keypair on form '$formId': no such form");
        }

        $page = $this->pageManager->getOne($tag, null, true, true);
        $activitypub = $page['metadatas']['activitypub'] ?? [];
        $activitypub['private_key'] = $privateKey;
        $activitypub['public_key'] = $publicKey;
        $this->pageManager->setMetadata($tag, ['activitypub' => $activitypub]);

        unset($this->cachedForms[$formId], $this->cachedForms[$tag]);
    }

    public function update($data)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $tag = $this->resolveTag((string)$data['id']);
        if ($tag === null) {
            throw new \Exception("Cannot update form '{$data['id']}': no such form");
        }

        $existingPage = $this->pageManager->getOne($tag, null, true, true);
        $existingBody = json_decode($existingPage['body'] ?? '', true) ?? [];

        $data['template'] = $this->convertWithSpecialParameters($this->templateToStorage($data['template'] ?? ''), $data['id']);

        // reset cache
        $this->cacheValidatedForAll = false;
        unset($this->cachedForms[$data['id']], $this->cachedForms[$tag]);

        $body = array_merge($existingBody, $this->buildBody($data));

        // a posted-but-cleared entry_* property (null/''/false) is REMOVED from the
        // body -- absence in the POST leaves the stored value untouched
        foreach (FormPropertiesService::OPTIONAL_PROPERTIES as $property) {
            if (array_key_exists($property, $data) && in_array($data[$property], [null, '', false], true)) {
                unset($body[$property]);
            }
        }

        $saved = $this->pageManager->save($tag, $this->encodeBody($body), '', true);

        $this->pageManager->setMetadata($tag, [
            'activitypub' => $this->buildActivitypubMetadata($data, $existingPage['metadatas']['activitypub'] ?? []),
        ]);

        return $saved;
    }

    /**
     * Renames a form's tag (its identity in `pages.tag`), keeping its stable numeric id
     * (and therefore its entries' association, image filenames, and ActivityPub actor URLs,
     * all keyed off that id) untouched. The old tag is kept resolvable via a
     * FORMER_TAG_URI triple. Returns the tag actually used (suggestFreeTag()-resolved if
     * $desiredNewTag collided with existing Content).
     */
    public function renameTag($formId, string $desiredNewTag): string
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $oldTag = $this->resolveTag((string)$formId);
        if ($oldTag === null) {
            throw new \Exception("Cannot rename form '$formId': no such form");
        }

        $newTag = $this->pageManager->suggestFreeTag($desiredNewTag);

        $this->pageManager->renameTag($oldTag, $newTag);
        $this->tripleStore->delete($oldTag, TripleStore::TYPE_URI, self::TRIPLES_FORM_TYPE, '', '');
        $this->tripleStore->create($newTag, TripleStore::TYPE_URI, self::TRIPLES_FORM_TYPE, '', '');
        $this->tripleStore->create($oldTag, self::FORMER_TAG_URI, $newTag, '', '');

        // reset cache
        $this->cacheValidatedForAll = false;
        $this->cachedForms = [];

        return $newTag;
    }

    public function clone($id)
    {
        $data = $this->getOne($id);
        if (!empty($data)) {
            unset($data['id']);
            $data['label'] = $data['label'] . ' (' . _t('BAZ_DUPLICATE') . ')';

            return $this->create($data);
        }

        // raise error?
        return false;
    }

    public function delete($id)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        // tests of if $formId is int
        if (strval(intval($id)) != strval($id)) {
            return null;
        }

        $tag = $this->resolveTag((string)$id);
        if ($tag === null) {
            return null;
        }

        foreach ($this->getEntryTagsForForm((string)$id) as $entryTag) {
            $this->entryManager->delete($entryTag, true);
        }

        $this->pageManager->deleteOrphaned($tag);

        // reset cache
        $this->cacheValidatedForAll = false;
        unset($this->cachedForms[$id], $this->cachedForms[$tag]);

        return true;
    }

    private function getEntryTagsForForm(string $numericId): array
    {
        $jsonExtract = $this->dbService->jsonExtract('p.body', '$.form_id');
        $sql = "SELECT p.tag FROM {$this->dbService->prefixTable('pages')} p
            WHERE p.latest = 'Y' AND {$jsonExtract} = '" . $this->dbService->escape($numericId) . "'
            AND EXISTS (
                SELECT 1 FROM {$this->dbService->prefixTable('triples')} t
                WHERE t.resource = p.tag
                    AND t.property = '" . $this->dbService->escape(TripleStore::TYPE_URI) . "'
                    AND t.value = '" . $this->dbService->escape(EntryManager::TRIPLES_ENTRY_ID) . "'
            )";

        return array_column($this->dbService->loadAll($sql), 'tag');
    }

    public function findNewId()
    {
        $ids = array_map('intval', $this->getAllIds());

        $lowBand = array_filter($ids, function ($id) {
            return $id < 1000;
        });
        $candidate = (empty($lowBand) ? 0 : max($lowBand)) + 1;
        if ($candidate < 999) {
            return $candidate;
        }

        $highBand = array_filter($ids, function ($id) {
            return $id > 10000;
        });

        return (empty($highBand) ? 10000 : max($highBand)) + 1;
    }

    /**
     * Parses a stored template into the internal positional field arrays consumed by the
     * Field constructors (via their FIELD_* index constants).
     *
     * The canonical storage format is a JSON array of named-attribute field objects
     * (`[{"type": "texte", "name": "bf_titre", "label": "..."}]`) -- attribute keys are
     * the FIELD_* constant names of the handling field class (see
     * FieldFactory::getAttributeIndexToKeyMap()); positions with no named constant
     * round-trip as numeric string keys. Note the converse: a named key that is NOT
     * in the handling class's map (e.g. a typo hand-edited into the code tab) has no
     * positional slot and is silently dropped on the next write. The historical
     * positional `***`-separated
     * syntax is still READ here -- old page revisions, remote imports from older wikis --
     * but is never written back: every write path re-encodes to JSON.
     *
     * @param string $raw stored template (JSON, or legacy `***` syntax)
     *
     * @return array list of positional field arrays, each padded to 16 entries
     */
    public function parseTemplate($raw)
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return [];
        }

        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $tableau_template = [];
                foreach ($decoded as $fieldObject) {
                    if (is_array($fieldObject)) {
                        $positional = $this->namedToPositional($fieldObject);
                        if ($positional !== null) {
                            $tableau_template[] = $positional;
                        }
                    }
                }

                return $tableau_template;
            }
            // not valid JSON after all -- fall through to the legacy parser
        }

        // Legacy positional syntax, one field per line, `***`-separated
        $tableau_template = [];
        $nblignes = 0;
        $chaine = explode("\n", $raw);
        foreach ($chaine as $ligne) {
            $ligne = trim($ligne);
            // on ignore les lignes vides ou commencant par # (commentaire)
            if (!empty($ligne) && !(strrpos($ligne, '#', -strlen($ligne)) !== false)) {
                // on decoupe chaque ligne par le separateur *** (c'est historique)
                $tablignechampsformulaire = array_map('trim', explode('***', $ligne));

                if (count($tablignechampsformulaire) > 3) {
                    $tableau_template[$nblignes] = $tablignechampsformulaire;
                    for ($i = 0; $i < 16; $i++) {
                        if (!isset($tableau_template[$nblignes][$i])) {
                            $tableau_template[$nblignes][$i] = '';
                        }
                    }
                    // drop empty slots beyond the 16 the field constructors read, so a
                    // line's trailing `***` separators parse identically to the JSON form
                    while (count($tableau_template[$nblignes]) > 16 && end($tableau_template[$nblignes]) === '') {
                        array_pop($tableau_template[$nblignes]);
                    }

                    $nblignes++;
                }
            }
        }

        return $tableau_template;
    }

    /**
     * Serializes the internal positional field arrays to the canonical stored form: a
     * pretty-printed JSON array of named-attribute field objects (the inverse of
     * parseTemplate()'s JSON branch). Empty slots are omitted; slots with no named
     * FIELD_* constant on the handling class keep their numeric position as a string
     * key so unknown/extension data survives round-trips losslessly.
     */
    public function encodeTemplate($template_list)
    {
        $fieldObjects = [];
        foreach ($template_list as $positional) {
            $fieldObject = $this->positionalToNamed((array)$positional);
            if ($fieldObject !== null) {
                $fieldObjects[] = $fieldObject;
            }
        }

        return json_encode($fieldObjects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** One JSON field object => one positional array padded to 16 entries, or null if typeless. */
    private function namedToPositional(array $fieldObject): ?array
    {
        $type = trim((string)($fieldObject['type'] ?? $fieldObject[0] ?? ''));
        if ($type === '') {
            return null;
        }

        $keyToIndex = $this->fieldFactory->getAttributeKeyToIndexMap($type);
        $positional = array_fill(0, 16, '');
        $positional[0] = $type;
        foreach ($fieldObject as $key => $value) {
            if ($key === 'type' || $key === 0 || $key === '0') {
                continue;
            }
            $index = $keyToIndex[$key] ?? (is_numeric($key) ? (int)$key : null);
            if ($index === null || $index < 1) {
                continue;
            }
            $positional[$index] = trim(is_array($value) ? join(',', $value) : (string)$value);
        }
        ksort($positional);

        return $positional;
    }

    /** One positional array => one JSON field object with named keys, or null if typeless. */
    private function positionalToNamed(array $positional): ?array
    {
        $type = trim((string)($positional[0] ?? ''));
        if ($type === '') {
            return null;
        }

        $indexToKey = $this->fieldFactory->getAttributeIndexToKeyMap($type);
        $fieldObject = ['type' => $type];
        foreach ($positional as $index => $value) {
            if (!is_int($index) || $index < 1) {
                continue;
            }
            $value = trim(is_array($value) ? join(',', $value) : (string)$value);
            if ($value === '') {
                continue;
            }
            $fieldObject[$indexToKey[$index] ?? (string)$index] = $value;
        }

        return $fieldObject;
    }

    /**
     * Re-encodes any template input (designer JSON or legacy `***` syntax, e.g. a form
     * imported from an older remote wiki) to the canonical JSON storage format.
     */
    public function normalizeTemplate($template): string
    {
        return $this->encodeTemplate($this->parseTemplate((string)$template));
    }

    /**
     * Native template (array of field objects) => list of positional arrays. For the
     * few boundaries that still need the constructors' positional wire format
     * (ExternalBazarService munging remote payloads, legacy migrations).
     */
    public function templateToPositionalList(array $template): array
    {
        $list = [];
        foreach ($template as $fieldObject) {
            $positional = is_array($fieldObject) && !array_is_list($fieldObject)
                ? $this->namedToPositional($fieldObject)
                : (is_array($fieldObject) ? $fieldObject : null);
            if ($positional !== null) {
                $list[] = $positional;
            }
        }

        return $list;
    }

    /** Inverse: list of positional arrays => native template (array of field objects). */
    public function positionalListToTemplate(array $list): array
    {
        $template = [];
        foreach ($list as $positional) {
            $fieldObject = is_array($positional) && array_is_list($positional)
                ? $this->positionalToNamed($positional)
                : (is_array($positional) ? $positional : null);
            if ($fieldObject !== null) {
                $template[] = $fieldObject;
            }
        }

        return $template;
    }

    /**
     * Template input (JSON string from the designer/API, legacy `***` syntax from an
     * import, or an already-decoded array) => the native array of named-attribute field
     * objects stored inside the page body.
     */
    private function templateToStorage($template): array
    {
        // arrays go through the same positional round-trip canonicalization as
        // string input (empty-slot dropping, key resolution) — no bypass
        if (is_array($template)) {
            $template = json_encode($template, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return json_decode($this->normalizeTemplate($template), true) ?? [];
    }

    public function prepareData($form)
    {
        $i = 0;
        $prepared = [];

        foreach ($form['template'] as $fieldObject) {
            // the field constructors consume positional arrays through their FIELD_*
            // constants -- an internal wire format derived here and nowhere exposed
            $field = $this->namedToPositional($fieldObject);
            if ($field === null) {
                continue;
            }
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
        return true;
    }

    /**
     * check if the bn_only_one_entry_message is available.
     */
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

    public function findByActivityPubUsername($username)
    {
        $forms = $this->getAll();
        foreach ($forms as $form) {
            if ($form['activitypub_username'] === $username) {
                return $form;
            }
        }

        return null;
    }
}
