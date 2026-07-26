<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\ImageField;
use YesWiki\Core\Attach;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\HibernationService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Wiki;

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

    protected function convertWithSpecialParameters($template, $id_nature)
    {
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

        // NOTE: unlike the pre-pages version of this method, the return value is NOT
        // SQL-escaped: callers now json_encode() it into the `pages.body` column, and
        // PageManager::save() does its own SQL escaping of that JSON string as a whole.
        // Escaping here too would double-escape and corrupt the stored JSON.
        return $template;
    }

    protected function prepare_with_special_parameters($form)
    {
        $basePath = $this->getBasePath();
        $template_list = $this->parseTemplate($form['bn_template']);
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

    private function resolveTagFromNumericId(string $id): ?string
    {
        $jsonExtract = $this->dbService->jsonExtract('body', '$.bn_id_nature');
        $sql = "SELECT tag FROM {$this->dbService->prefixTable('pages')}
            WHERE latest = 'Y' AND {$jsonExtract} = '" . $this->dbService->escape((string)(int)$id) . "'
            LIMIT 1";
        $row = $this->dbService->loadSingle($sql);

        return $row['tag'] ?? null;
    }

    private function resolveIdFromTag(string $tag): ?string
    {
        $jsonExtract = $this->dbService->jsonExtract('body', '$.bn_id_nature');
        $sql = "SELECT {$jsonExtract} AS id FROM {$this->dbService->prefixTable('pages')}
            WHERE tag = '" . $this->dbService->escape($tag) . "' AND latest = 'Y'
            LIMIT 1";
        $row = $this->dbService->loadSingle($sql);

        return $row['id'] ?? null;
    }

    /**
     * Converts a fetched `pages` row (as returned by PageManager) for a form into the flat,
     * `bn_*`-keyed array shape every other part of the bazar tool already expects --
     * unchanged from when forms lived in the `nature` table. ActivityPub credentials, which
     * live in `metadata.activitypub` (not `body`, so they aren't echoed by generic JSON/API
     * dumps of a page's body), are merged back in under their historical `bn_activitypub_*`
     * keys so no consumer needs to know where they're actually stored.
     */
    private function pageToFormArray(array $page): array
    {
        $body = json_decode($page['body'] ?? '', true) ?? [];
        $activitypub = $page['metadatas']['activitypub'] ?? [];

        $body['bn_activitypub_enable'] = (string)($activitypub['enabled'] ?? '0');
        $body['bn_activitypub_username'] = $activitypub['username'] ?? '';
        $body['bn_activitypub_private_key'] = $activitypub['private_key'] ?? null;
        $body['bn_activitypub_public_key'] = $activitypub['public_key'] ?? null;
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
        if (!empty($form['bn_id_nature'])) {
            $this->cachedForms[$form['bn_id_nature']] = $form;
        }

        return $form;
    }

    public function getFromRawData($form)
    {
        list($template_list, $modify) = $this->prepare_with_special_parameters($form);
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
            $triples = $this->tripleStore->getMatching(null, TripleStore::TYPE_URI, self::TRIPLES_FORM_TYPE);
            foreach ($triples as $triple) {
                $page = $this->pageManager->getOne($triple['resource'], null, true, true);
                if (!$page) {
                    continue;
                }
                $form = $this->getFromRawData($this->pageToFormArray($page));
                if (!empty($form['bn_id_nature'])) {
                    // save only not empty formId
                    $this->cachedForms[$form['bn_id_nature']] = $form;
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
            'bn_id_nature' => (string)$data['bn_id_nature'],
            'bn_ce_i18n' => $data['bn_ce_i18n'] ?? 'fr-FR',
            'bn_label_nature' => $data['bn_label_nature'] ?? '',
            'bn_template' => $data['bn_template'] ?? '',
            'bn_description' => $data['bn_description'] ?? '',
            'bn_sem_template' => $data['bn_sem_template'] ?? '',
            'bn_sem_reverse_template' => $data['bn_sem_reverse_template'] ?? '',
            'bn_only_one_entry' => (isset($data['bn_only_one_entry']) && $data['bn_only_one_entry'] === 'Y') ? 'Y' : 'N',
            'bn_only_one_entry_message' => empty($data['bn_only_one_entry_message']) ? '' : $data['bn_only_one_entry_message'],
            'bn_condition' => $data['bn_condition'] ?? '',
        ];
        foreach (['bn_sem_context', 'bn_sem_type', 'bn_sem_use_template'] as $legacySeedField) {
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
            'username' => $data['bn_activitypub_username'] ?? ($existingActivitypub['username'] ?? ''),
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

    private function slugify(string $title): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $title);
        $slug = preg_replace('/[^A-Za-z0-9]+/', '', $ascii === false ? $title : $ascii);

        return $slug !== '' ? $slug : 'Form';
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
        if (empty($data['bn_id_nature']) || $this->getOne($data['bn_id_nature'])) {
            $data['bn_id_nature'] = $this->findNewId();
        }

        $tag = $this->pageManager->suggestFreeTag($this->slugify($data['bn_label_nature'] ?? ''));

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

        $tag = $this->resolveTag((string)$data['bn_id_nature']);
        if ($tag === null) {
            throw new \Exception("Cannot update form '{$data['bn_id_nature']}': no such form");
        }

        $existingPage = $this->pageManager->getOne($tag, null, true, true);
        $existingBody = json_decode($existingPage['body'] ?? '', true) ?? [];

        $data['bn_template'] = $this->convertWithSpecialParameters($data['bn_template'], $data['bn_id_nature']);

        // reset cache
        $this->cacheValidatedForAll = false;
        unset($this->cachedForms[$data['bn_id_nature']], $this->cachedForms[$tag]);

        $body = array_merge($existingBody, $this->buildBody($data));

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
            unset($data['bn_id_nature']);
            $data['bn_label_nature'] = $data['bn_label_nature'] . ' (' . _t('BAZ_DUPLICATE') . ')';

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
        $jsonExtract = $this->dbService->jsonExtract('p.body', '$.id_typeannonce');
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
                    $new_line .= is_array($value) ? join(',', $value) : $value;
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

        $form['template'] = $form['template'];

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
            if ($form['bn_activitypub_username'] === $username) {
                return $form;
            }
        }

        return null;
    }
}
