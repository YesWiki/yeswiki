<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\FieldRole;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Field\BazarField;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Files\Service\ImageResizer;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\KeyPairGenerator;
use YesWiki\Kernel\Service\TripleStore;

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

    protected DbService $dbService;
    protected KeyPairGenerator $keyPairGenerator;
    protected EntryManager $entryManager;
    protected HibernationService $hibernationService;
    protected FieldFactory $fieldFactory;
    protected ParameterBagInterface $params;
    protected PageManager $pageManager;
    protected TripleStore $tripleStore;
    protected AclService $aclService;
    /** @var array<int|string, array<string, mixed>> forms already loaded this request, keyed by id and by tag */
    protected array $cachedForms = [];
    protected bool $cacheValidatedForAll = false;
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
        KeyPairGenerator $keyPairGenerator,
        PageManager $pageManager,
        TripleStore $tripleStore,
        AclService $aclService,
        private readonly Storage $storage,
        private readonly LocalFiles $localFiles,
    ) {
        $this->container = $container;
        $this->dbService = $dbService;
        $this->keyPairGenerator = $keyPairGenerator;
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

    protected function getBasePath(): string
    {
        $basePath = $this->paths->uploadPath();

        return $basePath . (substr($basePath, -1) != '/' ? '/' : '');
    }

    /** @param string $prefix */
    protected function cleanCacheDefaultImage($prefix): void
    {
        $cachePath = rtrim($this->paths->cachePath(), '/');
        foreach ($this->storage->files($cachePath) as $path) {
            if (str_starts_with(basename($path), $prefix)) {
                $this->storage->delete($path);
            }
        }
    }

    /**
     * Write-side handling of image fields' default image: the designer posts the default as `filename|data:image/...;base64,...`; the base64 part is written to files/defaultimage{id}_{name}.jpg and only the filename stays in the stored field object.
     *
     * @param array<int, array<string, mixed>> $template
     * @param int|string                       $id       the form's stable numeric id
     *
     * @return array<int, array<string, mixed>>
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
                // The decoded upload is a scratch file: the resizer wants a real path (ADR-0022
                // lists Zebra_Image among the libraries that do), and the result is what the wiki
                // keeps. Storage makes it and removes it, including when the resize throws.
                $this->storage->withTemporaryFile($imgext, function (string $tempFile) use ($default_image, $default_image_filename, $fieldObject) {
                    $ifp = $this->localFiles->openForWriting($tempFile);
                    if ($ifp === null) {
                        throw new \RuntimeException("could not open $tempFile for writing");
                    }
                    fwrite($ifp, base64_decode(explode(',', $default_image[1])[1]));
                    fclose($ifp);
                    $this->resizer->resize($tempFile, $default_image_filename, $fieldObject['image_height'] ?? '', $fieldObject['image_width'] ?? '', 'crop');
                });
            } else {
                unset($fieldObject['image_default']);
                if ($this->storage->exists($default_image_filename)) {
                    $this->storage->delete($default_image_filename);
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
     *
     * @param array<string, mixed> $form
     *
     * @return array<int, array<string, mixed>>
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
            $storedImage = $this->storage->exists($default_image_filename) ? $this->storage->read($default_image_filename) : false;
            if ($storedImage !== false) {
                $fieldObject['image_default'] = ($fieldObject['image_default'] ?? '') . '|data:image/jpg;base64,' . base64_encode($storedImage);
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

    /** Legacy `bn_*` body keys => their plain-English replacements (ticket 27, ADR-0010). */
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
     * Converts a fetched `pages` row (as returned by PageManager) for a form into the flat form array (plain-English keys, ADR-0010).
     */
    /**
     * @param array<string, mixed> $page
     *
     * @return array<string, mixed>
     */
    private function pageToFormArray(array $page): array
    {
        $body = $page['body'] ?? [];
        $activitypub = $page['metadatas']['activitypub'] ?? [];

        foreach (self::LEGACY_BODY_KEYS as $legacyKey => $key) {
            if (array_key_exists($legacyKey, $body) && !array_key_exists($key, $body)) {
                $body[$key] = $body[$legacyKey];
            }
            unset($body[$legacyKey]);
        }

        if (!is_array($body['template'] ?? null)) {
            $body['template'] = json_decode($this->normalizeTemplate($body['template'] ?? ''), true) ?? [];
        }

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

    /**
     * @param int|string $formId numeric id, current tag, or a former tag
     *
     * @return array<string, mixed>|null
     */
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
     * @param int|string|null $alsoCacheAs the identifier the caller asked by, when it differs from the form's own id (a tag, or a former tag)
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
     * Builds the full in-memory form from raw data (a pageToFormArray() result, or a POST from the form editor): `template` normalized to the native array of field objects (with image defaults embedded for display), `prepared` built from it.
     *
     * @param array<string, mixed> $form
     *
     * @return array<string, mixed>
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
     * The form describing a built-in Content type -- the Page form, the User form, the File form (ticket 10).
     *
     * @return array<string, mixed>|null
     */
    public function getByContentType(string $contentType): ?array
    {
        if (!ContentTypeSchema::isKnownType($contentType) || $contentType === ContentTypeSchema::TYPE_ENTRY) {
            return null;
        }

        if (!$this->cacheValidatedForAll) {
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

    /** @return array<int|string, array<string, mixed>> every form, keyed by its numeric id */
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
                    $this->cachedForms[$form['id']] = $form;
                }
            }
            $this->cacheValidatedForAll = true;
        }

        return array_filter(
            $this->cachedForms,
            function ($pForm, $pKey) {
                return intval($pKey) . '' === $pKey . '';
            },
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /** The tag of the single form describing $contentType, or null. */
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
     * @return array<int, string> id => label. Keyed like getAll(): the ids are numeric strings, so PHP has already coerced them to int.
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
     * @return list<int>
     */
    public function getAllIds(): array
    {
        return array_keys($this->getAllLabels());
    }

    /**
     * @param array<int|string> $formsIds
     *
     * @return array<int|string, array<string, mixed>|null> null for an id no form answers to
     */
    public function getMany($formsIds): array
    {
        if (count($formsIds) == 0) {
            return $this->getAll();
        }

        $results = [];

        foreach ($formsIds as $formId) {
            if (empty($this->cachedForms[$formId])) {
                $form = $this->getOne($formId);
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
     * Builds the storable `body` array (everything except ActivityPub credentials, which live in metadata -- see buildActivitypubMetadata()) from raw `bn_*`-keyed input (either an admin form submission or another form's data via clone()).
     */
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
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
            'entry_title_template' => trim((string)($data['entry_title_template'] ?? ''))
                ?: (ContentTypeSchema::defaultTitleTemplate($contentType) ?? FormPropertiesService::DEFAULT_TITLE_TEMPLATE),
            'sem_template' => $data['sem_template'] ?? '',
            'sem_reverse_template' => $data['sem_reverse_template'] ?? '',
            'only_one_entry' => (isset($data['only_one_entry']) && $data['only_one_entry'] === 'Y') ? 'Y' : 'N',
            'only_one_entry_message' => empty($data['only_one_entry_message']) ? '' : $data['only_one_entry_message'],
        ];
        foreach (FormPropertiesService::OPTIONAL_PROPERTIES as $property) {
            if (isset($data[$property])) {
                $body[$property] = $data[$property];
            }
        }
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
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $existingActivitypub
     *
     * @return array<string, mixed>
     */
    private function buildActivitypubMetadata(array $data, array $existingActivitypub): array
    {
        $enabled = (int)(($data['activitypub_enable'] ?? null) === '1');
        $activitypub = [
            'enabled' => (string)$enabled,
            'username' => $data['activitypub_username'] ?? ($existingActivitypub['username'] ?? ''),
            'private_key' => $existingActivitypub['private_key'] ?? null,
            'public_key' => $existingActivitypub['public_key'] ?? null,
        ];
        if ($enabled && empty($activitypub['private_key'])) {
            [$privateKey, $publicKey] = $this->keyPairGenerator->generate();
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

    /**
     * @param array<string, mixed> $data
     *
     * @return int PageManager::save()'s status, 0 on success
     */
    // TODO Pass a Form object instead of a raw array
    public function create($data)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        if (empty($data['id']) || $this->getOne($data['id'])) {
            $data['id'] = $this->findNewId();
        }

        $data[ContentTypeSchema::CONTENT_TYPE] = $this->resolveContentType($data);

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

    /** Overwrites a form's stored ActivityPub keypair, keeping `enabled`/`username` as already set. */
    /** @param int|string $formId */
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

    /**
     * @param array<string, mixed> $data
     *
     * @return int PageManager::save()'s status, 0 on success
     */
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
     * A form is Content, so saving one already dispatches `page.updated` -- and a listener could sniff the type triple to tell which pages are forms.
     */
    private function dispatchFormEvent(string $event, string $formId, string $tag): void
    {
        $this->container->get(EventDispatcher::class)->yesWikiDispatch($event, [
            'id' => $formId,
            'data' => ['form_id' => $formId, 'tag' => $tag],
        ]);
    }

    /**
     * Renames a form's tag (its identity in `pages.tag`), keeping its stable numeric id (and therefore its entries' association, image filenames, and ActivityPub actor URLs, all keyed off that id) untouched.
     */
    /** @param int|string $formId */
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
        $this->tripleStore->create($oldTag, self::FORMER_TAG_URI, $newTag, '', '');

        $this->cacheValidatedForAll = false;
        $this->cachedContentTypeTags = [];
        $this->cachedForms = [];

        return $newTag;
    }

    /**
     * @param int|string $id
     *
     * @return int|false PageManager::save()'s status for the copy, false when there is nothing to copy
     */
    public function clone($id)
    {
        $data = $this->getOne($id);
        if (!empty($data)) {
            unset($data['id']);
            $data['label'] = $data['label'] . ' (' . _t('BAZ_DUPLICATE') . ')';

            return $this->create($data);
        }

        return false;
    }

    /**
     * Delete every entry of a form, keeping the form itself -- the "empty this form" admin action.
     *
     * @param int|string $id the form's numeric id
     *
     * @return int the number of entries deleted
     */
    /** Refuse an operation that would leave a built-in Content type without its form. */
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

    /**
     * @param int|string $id
     *
     * @return bool|null true when the form was deleted, null when $id names no deletable form
     */
    public function delete($id)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

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

        $this->cacheValidatedForAll = false;
        $this->cachedContentTypeTags = [];
        unset($this->cachedForms[$id], $this->cachedForms[$tag]);

        $this->dispatchFormEvent('form.deleted', (string)$id, $tag);

        return true;
    }

    /** @return list<string> the tags of every entry under this form */
    private function getEntryTagsForForm(string $numericId): array
    {
        $jsonExtract = $this->dbService->jsonExtract('p.body', '$.form_id');
        $sql = "SELECT p.tag FROM {$this->dbService->prefixTable('pages')} p
            WHERE p.latest = 'Y' AND {$jsonExtract} = ?
            AND p.{$this->dbService->quoteIdentifier('type')} = ?";

        return array_column($this->dbService->loadAll($sql, [$numericId, PageType::ENTRY]), 'tag');
    }

    /** @return int an id no form is using yet */
    public function findNewId(): int
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
     * Parses a stored template into the internal positional field arrays consumed by the Field constructors (via their FIELD_* index constants).
     *
     * @param string $raw stored template (JSON, or legacy `***` syntax)
     *
     * @return array<int, array<int, string>> positional field arrays, each padded to 16 entries
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
        }

        $tableau_template = [];
        $nblignes = 0;
        $chaine = explode("\n", $raw);
        foreach ($chaine as $ligne) {
            $ligne = trim($ligne);
            if (!empty($ligne) && !(strrpos($ligne, '#', -strlen($ligne)) !== false)) {
                $tablignechampsformulaire = array_map('trim', explode('***', $ligne));

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

        return $tableau_template;
    }

    /**
     * Serializes the internal positional field arrays to the canonical stored form: a pretty-printed JSON array of named-attribute field objects (the inverse of parseTemplate()'s JSON branch).
     *
     * @param array<int, mixed> $template_list positional field arrays
     */
    public function encodeTemplate($template_list): string
    {
        $fieldObjects = [];
        foreach ($template_list as $positional) {
            $fieldObject = $this->positionalToNamed((array)$positional);
            if ($fieldObject !== null) {
                $fieldObjects[] = $fieldObject;
            }
        }

        $json = json_encode($fieldObjects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '' : $json;
    }

    /**
     * One JSON field object => one positional array padded to 16 entries, or null if typeless.
     *
     * @param array<array-key, mixed> $fieldObject
     *
     * @return array<int, string>|null
     */
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

    /**
     * One positional array => one JSON field object with named keys, or null if typeless.
     *
     * @param array<array-key, mixed> $positional
     *
     * @return array<string, string>|null
     */
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

    /** Re-encodes any template input (designer JSON or legacy `***` syntax, e.g. */
    /** @param mixed $template stored template: JSON, legacy `***` syntax, or an already-decoded array */
    public function normalizeTemplate($template): string
    {
        return $this->encodeTemplate($this->parseTemplate((string)$template));
    }

    /** Native template (array of field objects) => list of positional arrays. */
    /**
     * @param array<array-key, mixed> $template
     *
     * @return list<array<array-key, mixed>> positional field arrays
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

    /**
     * Inverse: list of positional arrays => native template (array of field objects).
     *
     * @param array<array-key, mixed> $list positional field arrays
     *
     * @return list<array<array-key, mixed>> field objects
     */
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
    /** @return array<int, array<string, mixed>> */
    private function templateToStorage(mixed $template, ?string $contentType = null): array
    {
        if (is_array($template)) {
            $template = json_encode($template, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $stored = json_decode($this->normalizeTemplate($template), true) ?? [];

        return ContentTypeSchema::enforce($stored, $contentType);
    }

    /**
     * Which Content type a form describes.
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

    /**
     * @param array<string, mixed> $form
     *
     * @return array<int, BazarField>
     */
    public function prepareData($form)
    {
        $i = 0;
        $prepared = [];

        foreach ($form['template'] as $fieldObject) {
            $field = $this->namedToPositional($fieldObject);
            if ($field === null) {
                continue;
            }
            $classField = $this->fieldFactory->create($field);

            if ($classField) {
                $prepared[$i] = $classField;
            } elseif (function_exists($field[0])) {
                $functionName = $field[0];
                $field[0] = 'old';
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

    /**
     * @param int|string           $pFormId
     * @param array<string, mixed> $pForm
     */
    public function cacheForm($pFormId, $pForm): void
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

    /**
     * @param int|string $formId
     * @param string[]   $fieldTypes short class names, e.g. 'SelectEntryField'
     *
     * @return list<BazarField>
     */
    public function findTypeOfFields($formId, array $fieldTypes): array
    {
        $res = [];
        $form = $this->getOne($formId);
        if (empty($form)) {
            return $res;
        }

        foreach ($form['prepared'] as $field) {
            if (!$field instanceof BazarField) {
                continue;
            }
            $class = get_class($field);
            $class = explode('\\', $class);
            $class = array_pop($class);
            if (in_array($class, $fieldTypes)) {
                $res[] = $field;
            }
        }

        return $res;
    }

    /**
     * The first field of these forms whose property name is $fieldId.
     *
     * @param array<int|string> $formId  form ids to search, in order
     * @param string            $fieldId
     *
     * @return BazarField|array{} the empty array when no form carries that field
     */
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

    /**
     * @param string $username
     *
     * @return array<string, mixed>|null
     */
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
