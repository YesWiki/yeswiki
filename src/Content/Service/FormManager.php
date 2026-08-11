<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\FieldRole;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Field\BazarField;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Search\Service\SearchManager;

class FormManager
{
    // A form's tag is renameable; when it's renamed, the old tag is kept resolvable via
    // this triple (resource=old tag, value=new tag) so previously published references to
    // it (e.g. an ActivityPub actor URL that was ever built from the tag rather than the
    // stable numeric id) don't dead-end. See ADR / CONTEXT.md "Forms keep a stable
    // identifier distinct from their (renameable) tag".
    public const FORMER_TAG_URI = 'http://outils-reseaux.org/_vocabulary/formerTag';

    // bounds resolveTag()'s alias-chain walk (renamed twice, three times, ...) so a cycle
    // (which should never happen, but shouldn't be trusted blindly either) can't hang
    private const MAX_ALIAS_HOPS = 10;

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
    /** @var array<string, string|null> content type => the tag of its form, false-free memo */
    private array $cachedContentTypeTags = [];
    protected AttachedFilePaths $paths;
    protected ImageResizer $resizer;

    protected ContainerInterface $container;

    public function __construct(
        ContainerInterface $container,
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
        $this->container = $container;
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
        $this->cachedContentTypeTags = [];
        $this->hibernationService = $hibernationService;
        $this->paths = $this->container->get(AttachedFilePaths::class);
        $this->resizer = $this->container->get(ImageResizer::class);
    }

    protected function getBasePath()
    {
        $basePath = $this->paths->uploadPath();

        return $basePath . (substr($basePath, -1) != '/' ? '/' : '');
    }

    protected function cleanCacheDefaultImage($prefix)
    {
        $cache_path = $this->paths->cachePath();
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
                    $this->resizer->resize($tempFile, $default_image_filename, $fieldObject['image_height'] ?? '', $fieldObject['image_width'] ?? '', 'crop');
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
            WHERE latest = 'Y' AND {$jsonExtract} = ?
            LIMIT 1";
        $row = $this->dbService->loadSingle($sql, [(string)(int)$id]);

        return $row['tag'] ?? null;
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
        $body = $page['body'] ?? [];
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

        // Locked fields are enforced on read as well as on write: a stored template can
        // arrive without them by a route that never touches this service -- reverting a
        // form page to a pre-lock revision, a migration, a direct PageManager::save().
        // Enforcing here means such a template presents complete and is persisted
        // complete by the next ordinary write (ticket 10).
        $body['template'] = ContentTypeSchema::enforce(
            $body['template'],
            $body[ContentTypeSchema::CONTENT_TYPE] ?? null
        );

        $body = ContentTypeSchema::stripInapplicableProperties(
            $body,
            $body[ContentTypeSchema::CONTENT_TYPE] ?? null
        );

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

        return $this->loadFormFromTag($tag, $formId);
    }

    /**
     * The form held by a page whose tag is already known.
     *
     * Split out of getOne() for the caller that found the tag by querying `pages` in the
     * first place: getOne() would put it through resolveTag(), whose non-numeric branch asks
     * `SELECT 1 FROM pages WHERE tag = …` to find out whether the page exists -- about a page
     * this caller has just read out of that table, and whose row is loaded on the next line
     * regardless.
     *
     * @param int|string|null $alsoCacheAs the identifier the caller asked by, when it differs
     *                                     from the form's own id (a tag, or a former tag)
     *
     * @return array<string, mixed>|null
     */
    private function loadFormFromTag(string $tag, $alsoCacheAs = null): ?array
    {
        if (isset($this->cachedForms[$tag])) {
            return $this->cachedForms[$tag];
        }

        $page = $this->pageManager->getOne($tag, null, true, true);
        if (!$page) {
            return null;
        }

        $form = $this->getFromRawData($this->pageToFormArray($page));

        if ($alsoCacheAs !== null) {
            $this->cachedForms[$alsoCacheAs] = $form;
        }
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

    /**
     * The form describing a built-in Content type -- the Page form, the User form, the
     * File form (ticket 10). There is exactly one per type, enforced by create(): which
     * form applies to a row is decided by the row's `type` column (ticket 27), so Content
     * does not carry a `form_id` the way a bazar entry does.
     *
     * **This is on the render path of every page in the wiki**, through
     * ContentTypeResolver::formFor(). It used to scan `getAll()`, which fully prepares
     * every form in the wiki -- 29 queries and ~23 ms on a 14-form wiki -- to find one.
     * Now it asks which page holds that form and loads that one, so the cost is the form
     * you asked for rather than all of them.
     *
     * @return array<string, mixed>|null
     */
    public function getByContentType(string $contentType): ?array
    {
        if (!ContentTypeSchema::isKnownType($contentType) || $contentType === ContentTypeSchema::TYPE_ENTRY) {
            return null;
        }

        if (!$this->cacheValidatedForAll) {
            // memoised: this runs on every page render, and without it each caller re-asked
            // which page holds the form. getOne() below has its own cache, so the lookup was
            // the whole of what repeated
            if (!array_key_exists($contentType, $this->cachedContentTypeTags)) {
                $this->cachedContentTypeTags[$contentType] = $this->tagOfContentTypeForm($contentType);
            }
            $tag = $this->cachedContentTypeTags[$contentType];

            return $tag === null ? null : $this->loadFormFromTag($tag);
        }

        foreach ($this->getAll() as $form) {
            if (($form[ContentTypeSchema::CONTENT_TYPE] ?? null) === $contentType) {
                return $form;
            }
        }

        return null;
    }

    public function getAll(): array
    {
        if (!$this->cacheValidatedForAll) {
            foreach ($this->pageManager->tagsOfType(PageType::FORM) as $formTag) {
                $page = $this->pageManager->getOne($formTag, null, true, true);
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

    /**
     * The tag of the single form describing $contentType, or null.
     *
     * Ordered by tag and limited to one so that a wiki which somehow holds two forms for
     * the same built-in type picks the same one this method's getAll() branch would --
     * create() refuses to make a second, but a restored revision or a hand-edited body can
     * still produce one, and answering differently depending on which branch ran would be
     * worse than answering arbitrarily but consistently.
     */
    private function tagOfContentTypeForm(string $contentType): ?string
    {
        $contentTypeExpr = $this->dbService->jsonExtract('body', '$.' . ContentTypeSchema::CONTENT_TYPE);

        $row = $this->dbService->loadSingle(
            'SELECT tag FROM ' . $this->dbService->prefixTable('pages')
            . " WHERE latest = 'Y' AND " . $this->dbService->quoteIdentifier('type')
            . ' = ?'
            . " AND {$contentTypeExpr} = ?"
            . ' ORDER BY tag LIMIT 1',
            [PageType::FORM, $contentType]
        );

        return $row === null ? null : (string)$row['tag'];
    }

    /**
     * Every form's id and label, and nothing else -- `id => label`, ordered by tag.
     *
     * `getAll()` is the wrong tool for a caller that only wants to name the forms. It loads
     * each form's page row, normalises legacy body keys, runs the stored template through
     * ContentTypeSchema::enforce(), re-reads every image field's default off disk to
     * base64-encode it, and instantiates every field object through FieldFactory -- which
     * for a `liste` or `checkbox` field loads the list behind it, costing more queries
     * again. On this wiki's 14 forms that is 29 queries and ~24 ms.
     *
     * `{{linkrss}}` paid all of it on **every page load**, to print a `<link>` tag per form
     * in the document head. This is one query, whatever the wiki holds.
     *
     * Reads the shared cache when `getAll()` has already been called this request, so a
     * screen that genuinely needs whole forms does not pay for a second trip.
     *
     * @return array<int, string> id => label. Keyed like getAll(): the ids are numeric
     *                            strings, so PHP has already coerced them to int.
     */
    public function getAllLabels(): array
    {
        $labels = [];

        if ($this->cacheValidatedForAll) {
            foreach ($this->getAll() as $formId => $form) {
                $labels[(int)$formId] = (string)($form['label'] ?? '');
            }

            return $labels;
        }

        // COALESCE onto the pre-ticket-27 key names for the same reason pageToFormArray()
        // keeps its legacy-key insurance: a form page restored from an old revision can
        // still be carrying `bn_id_nature` / `bn_label_nature`
        $id = $this->dbService->jsonExtract('body', '$.id');
        $legacyId = $this->dbService->jsonExtract('body', '$.bn_id_nature');
        $label = $this->dbService->jsonExtract('body', '$.label');
        $legacyLabel = $this->dbService->jsonExtract('body', '$.bn_label_nature');

        $rows = $this->dbService->loadAll(
            "SELECT COALESCE({$id}, {$legacyId}) AS form_id,"
            . " COALESCE({$label}, {$legacyLabel}, '') AS form_label"
            . ' FROM ' . $this->dbService->prefixTable('pages')
            . " WHERE latest = 'Y' AND " . $this->dbService->quoteIdentifier('type')
            . ' = ?'
            . ' ORDER BY tag',
            [PageType::FORM]
        );

        foreach ($rows as $row) {
            $formId = (string)($row['form_id'] ?? '');
            // same filter as getAll(): a form page without a usable numeric id is not a form
            // any consumer can address, and every caller here keys off that id
            if ($formId === '' || (string)intval($formId) !== $formId) {
                continue;
            }
            $labels[(int)$formId] = (string)($row['form_label'] ?? '');
        }

        return $labels;
    }

    /**
     * Every form's id.
     *
     * One query, through the same projection getAllLabels() uses -- it was a `tagsOfType()`
     * plus one `resolveIdFromTag()` per form, so a wiki with fifty forms asked fifty-one
     * questions to list fifty numbers.
     *
     * @return list<int>
     */
    public function getAllIds(): array
    {
        return array_keys($this->getAllLabels());
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
        $contentType = $this->resolveContentType($data);
        $body = [
            'id' => (string)$data['id'],
            'lang' => $data['lang'] ?? 'fr-FR',
            'label' => $data['label'] ?? '',
            ContentTypeSchema::CONTENT_TYPE => $contentType,
            'template' => $this->templateToStorage($data['template'] ?? '', $contentType),
            'description' => $data['description'] ?? '',
            // entry_title_template can never be empty (ADR-0010). A built-in Content type
            // names itself with one of its own locked fields; only a bazar form falls back
            // to the historical implicit convention of a visitor-typed bf_titre field.
            'entry_title_template' => trim((string)($data['entry_title_template'] ?? ''))
                ?: (ContentTypeSchema::defaultTitleTemplate($contentType) ?? FormPropertiesService::DEFAULT_TITLE_TEMPLATE),
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
        // which field plays which role (ticket 11) -- like the entry_* properties above,
        // included only when posted so an update leaves an unposted map alone
        if (isset($data[FieldRole::FORM_PROPERTY])) {
            $body[FieldRole::FORM_PROPERTY] = FieldRole::normalizeMap($data[FieldRole::FORM_PROPERTY]);
        }
        foreach (['sem_context', 'sem_type', 'sem_use_template'] as $legacySeedField) {
            if (isset($data[$legacySeedField])) {
                $body[$legacySeedField] = $data[$legacySeedField];
            }
        }

        return ContentTypeSchema::stripInapplicableProperties($body, $contentType);
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
        $data[ContentTypeSchema::CONTENT_TYPE] = $this->resolveContentType($data);

        // A built-in Content type has exactly one form: which form describes a row is
        // decided by the row's TYPE_URI triple, so a second Page form would leave
        // getByContentType() picking arbitrarily between them.
        $builtIn = $data[ContentTypeSchema::CONTENT_TYPE];
        if ($builtIn !== ContentTypeSchema::TYPE_ENTRY && $this->getByContentType($builtIn) !== null) {
            throw new \Exception("A '{$builtIn}' form already exists; there is exactly one per Content type");
        }
        $data['template'] = $this->convertWithSpecialParameters(
            $this->templateToStorage($data['template'] ?? '', $data[ContentTypeSchema::CONTENT_TYPE]),
            $data['id']
        );

        $tag = $this->pageManager->suggestFreeTag($this->slugify($data['label'] ?? ''));

        // reset cache
        $this->cacheValidatedForAll = false;
        $this->cachedContentTypeTags = [];

        $saved = $this->pageManager->save($tag, $this->buildBody($data), '', true, null, PageType::FORM);

        if ($saved === 0) {
            $this->pageManager->cacheType($tag, PageType::FORM);
            $this->pageManager->setMetadata($tag, ['activitypub' => $this->buildActivitypubMetadata($data, [])]);
            $this->aclService->save($tag, 'write', '@admins');
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
        $existingBody = $existingPage['body'] ?? [];

        $data[ContentTypeSchema::CONTENT_TYPE] = $this->resolveContentType($data, $existingBody);
        $data['template'] = $this->convertWithSpecialParameters(
            $this->templateToStorage($data['template'] ?? '', $data[ContentTypeSchema::CONTENT_TYPE]),
            $data['id']
        );

        // reset cache
        $this->cacheValidatedForAll = false;
        $this->cachedContentTypeTags = [];
        unset($this->cachedForms[$data['id']], $this->cachedForms[$tag]);

        $body = array_merge($existingBody, $this->buildBody($data));

        // a posted-but-cleared entry_* property (null/''/false) is REMOVED from the
        // body -- absence in the POST leaves the stored value untouched
        foreach (FormPropertiesService::OPTIONAL_PROPERTIES as $property) {
            if (array_key_exists($property, $data) && in_array($data[$property], [null, '', false], true)) {
                unset($body[$property]);
            }
        }

        $saved = $this->pageManager->save($tag, $body, '', true);

        $this->pageManager->setMetadata($tag, [
            'activitypub' => $this->buildActivitypubMetadata($data, $existingPage['metadatas']['activitypub'] ?? []),
        ]);

        $this->dispatchFormEvent('form.updated', (string)$data['id'], $tag);

        return $saved;
    }

    /**
     * A form is Content, so saving one already dispatches `page.updated` -- and a listener
     * could sniff the type triple to tell which pages are forms. `form.updated` exists
     * anyway because a form is a **schema**, and something changing a schema should say so
     * rather than leave every listener to work it out.
     *
     * Ticket 18 is the first consumer: a form's template decides which fields exist, what
     * each contributes to the search index and which Field ACL guards it, so a change here
     * invalidates the indexed text of every entry under the form. Nothing about that is
     * derivable from `page.updated` without re-deriving the type.
     *
     * Deliberately not wired to `webhooks`, which the CONTEXT decision scopes to comment and
     * entry events.
     */
    private function dispatchFormEvent(string $event, string $formId, string $tag): void
    {
        $this->container->get(EventDispatcher::class)->yesWikiDispatch($event, [
            'id' => $formId,
            'data' => ['form_id' => $formId, 'tag' => $tag],
        ]);
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

        // renameTag() moves the row, and the type is a column on it, so the type moves with
        // it -- nothing to delete and re-create the way the triple needed
        $this->pageManager->renameTag($oldTag, $newTag);
        $this->tripleStore->create($oldTag, self::FORMER_TAG_URI, $newTag, '', '');

        // reset cache
        $this->cacheValidatedForAll = false;
        $this->cachedContentTypeTags = [];
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

    /**
     * Delete every entry of a form, keeping the form itself -- the "empty this form"
     * admin action.
     *
     * This existed as a call site without an implementation: FormController::empty() and
     * ::delete() both called it, so both fataled with "Call to undefined method". delete()
     * removes the entries itself, so its call was redundant as well as fatal.
     *
     * @param int|string $id the form's numeric id
     *
     * @return int the number of entries deleted
     */
    /**
     * Refuse an operation that would leave a built-in Content type without its form.
     *
     * Deleting the Page form does not delete a webmaster's data structure -- it removes
     * the schema every page in the wiki is edited and listed through. There is no route
     * back to it except re-running the migration, so this is not a confirmation dialog's
     * job (ticket 10).
     */
    private function refuseIfBuiltIn(string $id, string $operation): void
    {
        $form = $this->getOne($id);
        $contentType = $form[ContentTypeSchema::CONTENT_TYPE] ?? null;
        if (ContentTypeSchema::isBuiltIn($contentType)) {
            throw new \Exception("Cannot {$operation} form '{$id}': it describes the built-in '{$contentType}' Content type");
        }
    }

    /** @param int|string $id */
    public function clear($id): int
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        if (strval(intval($id)) != strval($id)) {
            return 0;
        }

        $this->refuseIfBuiltIn((string)$id, 'empty');

        $deleted = 0;
        foreach ($this->getEntryTagsForForm((string)$id) as $entryTag) {
            $this->entryManager->delete($entryTag, true);
            $deleted++;
        }

        return $deleted;
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

        $this->refuseIfBuiltIn((string)$id, 'delete');

        foreach ($this->getEntryTagsForForm((string)$id) as $entryTag) {
            $this->entryManager->delete($entryTag, true);
        }

        $this->pageManager->deleteOrphaned($tag);

        // reset cache
        $this->cacheValidatedForAll = false;
        $this->cachedContentTypeTags = [];
        unset($this->cachedForms[$id], $this->cachedForms[$tag]);

        $this->dispatchFormEvent('form.deleted', (string)$id, $tag);

        return true;
    }

    private function getEntryTagsForForm(string $numericId): array
    {
        $jsonExtract = $this->dbService->jsonExtract('p.body', '$.form_id');
        $sql = "SELECT p.tag FROM {$this->dbService->prefixTable('pages')} p
            WHERE p.latest = 'Y' AND {$jsonExtract} = ?
            AND p.{$this->dbService->quoteIdentifier('type')} = ?";

        return array_column($this->dbService->loadAll($sql, [$numericId, PageType::ENTRY]), 'tag');
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
     * (legacy migrations; ExternalBazarService was the other one, deleted by ticket 34).
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
    private function templateToStorage($template, ?string $contentType = null): array
    {
        // arrays go through the same positional round-trip canonicalization as
        // string input (empty-slot dropping, key resolution) — no bypass
        if (is_array($template)) {
            $template = json_encode($template, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $stored = json_decode($this->normalizeTemplate($template), true) ?? [];

        // Every write vector -- the designer, the API, CSV import, duplication, a
        // hand-edited template -- lands here, so this is where a Content type's locked
        // fields are put back if they went missing (ticket 10).
        return ContentTypeSchema::enforce($stored, $contentType);
    }

    /**
     * Which Content type a form describes. Immutable once a form has one: retyping a
     * User form into an ordinary entry form would be a way to unlock its core fields.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $existingBody
     */
    private function resolveContentType(array $data, array $existingBody = []): string
    {
        $existing = $existingBody[ContentTypeSchema::CONTENT_TYPE] ?? null;
        if (ContentTypeSchema::isKnownType($existing)) {
            return (string)$existing;
        }
        $requested = $data[ContentTypeSchema::CONTENT_TYPE] ?? null;

        return ContentTypeSchema::isKnownType($requested) ? (string)$requested : ContentTypeSchema::TYPE_ENTRY;
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
