<?php

namespace YesWiki\Content\Service;

use Exception;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Exception\EntryValidationException;
use YesWiki\Content\Exception\ParsingMultipleException;
use YesWiki\Content\Exception\TagAlreadyUsedException;
use YesWiki\Content\Field\BazarField;
use YesWiki\Identity\Service\AccountJustCreated;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\Guard;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Search\Service\SearchManager;

class EntryManager
{
    protected ContainerInterface $container;
    protected AuthenticationService $authenticationService;
    protected PageManager $pageManager;
    protected TripleStore $tripleStore;
    protected AclService $aclService;
    protected UserManager $userManager;
    protected DbService $dbService;
    protected SemanticTransformer $semanticTransformer;
    protected HibernationService $hibernationService;
    protected Journal $journal;
    protected ParameterBagInterface $params;
    protected SearchManager $searchManager;

    public const VALIDATE_FLAG_ANTISPAM = 1 << 0;
    public const VALIDATE_FLAG_TITLE = 1 << 1;
    public const VALIDATE_FLAG_FORM_ID = 1 << 2;
    public const VALIDATE_FLAG_ALL = self::VALIDATE_FLAG_ANTISPAM | self::VALIDATE_FLAG_TITLE | self::VALIDATE_FLAG_FORM_ID;

    protected UrlFormatter $urlFormatter;

    public function __construct(
        ContainerInterface $container,
        AuthenticationService $authenticationService,
        PageManager $pageManager,
        TripleStore $tripleStore,
        AclService $aclService,
        UserManager $userManager,
        DbService $dbService,
        SemanticTransformer $semanticTransformer,
        ParameterBagInterface $params,
        SearchManager $searchManager,
        HibernationService $hibernationService,
        Journal $journal,
        UrlFormatter $urlFormatter,
    ) {
        $this->urlFormatter = $urlFormatter;
        $this->container = $container;
        $this->authenticationService = $authenticationService;
        $this->pageManager = $pageManager;
        $this->tripleStore = $tripleStore;
        $this->aclService = $aclService;
        $this->userManager = $userManager;
        $this->dbService = $dbService;
        $this->semanticTransformer = $semanticTransformer;
        $this->params = $params;
        $this->searchManager = $searchManager;
        $this->hibernationService = $hibernationService;
        $this->journal = $journal;
    }

    /**
     * Resolved when it is needed rather than injected: the notifier renders an entry through
     * EntryController, which is built on this, so asking for it in the constructor is a cycle.
     */
    private function notifier(): ContentNotifier
    {
        return $this->container->get(ContentNotifier::class);
    }

    /**
     * Announce that an entry changed -- from here, so that **every** write path announces it.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $entry
     */
    private function announce(string $event, array $form, array $entry, bool $imported): void
    {
        $this->container->get(EventDispatcher::class)->yesWikiDispatch($event, [
            'id' => $entry['tag'] ?? '',
            'data' => $entry,

            'form' => $form,
            'imported' => $imported,
        ]);
    }

    /**
     * Returns true if the provided page is a Bazar fiche.
     *
     * @param string $tag
     */
    public function isEntry($tag): bool
    {
        if (empty($tag)) {
            return false;
        }

        return $this->pageManager->isType((string)$tag, PageType::ENTRY);
    }

    /**
     * return array with list of page's tag for all entries.
     *
     * @return list<string>
     */
    public function getAllEntriesTags(): array
    {
        return $this->pageManager->tagsOfType(PageType::ENTRY);
    }

    /**
     * Get one specified fiche.
     *
     * @param string      $tag
     * @param bool        $semantic
     * @param string      $time                   pour consulter une fiche dans l'historique
     * @param bool        $cache                  if false, don't use the page cache
     * @param bool        $bypassAcls             if true, all fields are loaded regardless of acls
     * @param string|null $userNameForCheckingACL userName used to get entry, if empty uses the connected user
     *
     * @return array<string, mixed>|null the entry, or null when $tag names no entry
     *
     * @throws \Exception
     */
    public function getOne($tag, $semantic = false, $time = null, $cache = true, $bypassAcls = false, ?string $userNameForCheckingACL = null): ?array
    {
        $page = $this->pageManager->getOne($tag, empty($time) ? null : $time, $cache, $bypassAcls, $userNameForCheckingACL);

        $isEntry = $page === null ? $this->isEntry($tag) : (($page['type'] ?? null) === PageType::ENTRY);
        if (!$isEntry) {
            return null;
        }

        $debug = (bool)$this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)->getValue('debug');
        $data = $this->getDataFromPage($page ?? [], $semantic, $debug);

        if ($data !== [] && empty($data['created_at'])) {
            $data['created_at'] = $this->pageManager->getCreateTime($tag);
        }

        return $data;
    }

    /**
     * @param int|string                $pFormID
     * @param array<string, mixed>|null $pData
     *
     * @return array<string, mixed>
     */
    protected function removeUnknownFields($pFormID, $pData)
    {
        $vAuthorizedFields = [...$pData ?? []];

        $extraFields = [
            'tag', 'form_id', 'created_at',
            'updated_at', 'status', 'url',
            '-is-external-', 'external-data',
        ];

        foreach ($extraFields as $key) {
            if (isset($pData[$key])) {
                $vAuthorizedFields[$key] = $pData[$key];
            }
        }

        return $vAuthorizedFields;
    }

    /**
     * getDataFromPage.
     *
     * @param array<string, mixed> $page         content of page from sql
     * @param bool                 $debug        to throw exception in case of error
     * @param string               $fieldMapping to pass fieldMapping parameter directly to appendDisplayData
     *
     * @return array<string, mixed> data formated
     */
    public function getDataFromPage($page, bool $semantic = false, bool $debug = false, string $fieldMapping = ''): array
    {
        $data = [];
        if (!empty($page['body'])) {
            $data = $this->decode($page['body']);

            if (empty($data)) {
                return [];
            }

            $data = $this->removeUnknownFields($data['form_id'], $data);

            $form = $this->container->get(FormManager::class)->getOne($data['form_id']);

            $vRegisteredData = [...$data];

            $extraFields = [
                'tag', 'form_id', 'created_at',
                'updated_at', 'status', 'url',
            ];

            foreach ($extraFields as $key) {
                if (isset($data[$key])) {
                    $vRegisteredData[$key] = $data[$key];
                }
            }

            $data = $vRegisteredData;

            if ($debug) {
                if (empty($data['tag'])) {
                    trigger_error('empty \'tag\' in EntryManager::getDataFromPage in body of page \''
                        . $page['tag'] . '\'. Edit it to create tag', E_USER_WARNING);
                }
                if (empty($page['tag'])) {
                    trigger_error('empty $page[\'tag\'] in EntryManager::getDataFromPage! ', E_USER_WARNING);
                }
            }

            if (!isset($data['tag'])) {
                $data['tag'] = $page['tag'];
            }

            $this->appendDisplayData($data, $semantic, $fieldMapping, $page);
        } elseif ($debug) {
            trigger_error('empty \'body\' in EntryManager::getDataFromPage for page \'' . ($page['tag'] ?? '!!empty tag!!') . '\'', E_USER_WARNING);
        }

        return $data;
    }

    /**
     * Validate the fiche's data.
     *
     * @param array<string, mixed> $data
     * @param int                  $pFlags
     *
     * @throws \Exception
     */
    public function validate($data, $pFlags = self::VALIDATE_FLAG_ALL): void
    {
        if ($pFlags & self::VALIDATE_FLAG_ANTISPAM) {
            if (!isset($data['antispam']) || !$data['antispam'] == 1) {
                throw new \Exception(_t('BAZ_PROTECTION_ANTISPAM'));
            }
        }

        if ($pFlags & self::VALIDATE_FLAG_TITLE) {
            if (empty($data['title'] ?? null) && empty($data['bf_titre'] ?? null)) {
                throw new EntryValidationException(_t('BAZ_FICHE_NON_SAUVEE_PAS_DE_TITRE'));
            }
        }

        if ($pFlags & self::VALIDATE_FLAG_FORM_ID) {
            if (!isset($data['form_id'])) {
                throw new \Exception(_t('BAZ_NO_FORMS_FOUND'));
            }
        }
    }

    /**
     * Create a new fiche.
     *
     * @param bool                 $semantic
     * @param string|null          $sourceUrl
     * @param int|string           $formId
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed> the entry as it was stored
     *
     * @throws \Exception
     */
    public function create($formId, $data, $semantic = false, $sourceUrl = null)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $data['form_id'] = "$formId";

        if ($semantic) {
            $data = $this->semanticTransformer->convertFromSemanticData(
                $this->container->get(FormManager::class)->getOne($formId),
                $data
            );
        }

        $this->refuseTagOfAnExistingPage($data['tag'] ?? null);

        $this->validate($data, self::VALIDATE_FLAG_ANTISPAM);

        $form = $this->container->get(FormManager::class)->getOne($data['form_id']);

        if (ContentTypeSchema::isBuiltIn($form[ContentTypeSchema::CONTENT_TYPE] ?? null)) {
            throw new \Exception("Form '{$data['form_id']}' describes the built-in '" . $form[ContentTypeSchema::CONTENT_TYPE] . "' Content type: it has no entries to create");
        }

        $data = $this->assignRestrictedFields($data, [], $form);

        $data = $this->formatDataBeforeSave($data);

        $this->refuseTagOfAnExistingPage($data['tag'] ?? null);

        $this->validate($data, self::VALIDATE_FLAG_TITLE | self::VALIDATE_FLAG_FORM_ID);

        $justCreated = $this->container->get(AccountJustCreated::class);
        if ($justCreated->isRecorded()) {
            $olduser = $this->authenticationService->getLoggedUser();
            $this->authenticationService->logout();

            $user = $this->userManager->getOneByName((string)$justCreated->name());
            if (!empty($user)) {
                $this->authenticationService->login($user);
            }
        }

        $ignoreAcls = true;
        if ($this->params->has('bazarIgnoreAcls')) {
            $ignoreAcls = (bool)$this->params->get('bazarIgnoreAcls');
        }

        $sendmail = $this->removeSendmail($data);

        $saved = $this->pageManager->save(
            $data['tag'],
            $data,
            '',
            $ignoreAcls,
            $data['updated_at'],
            PageType::ENTRY
        );

        if ($saved == 0) {
            $formProperties = $this->container->get(FormPropertiesService::class);
            $formProperties->applyEntryAcls($form, $data, true);
            $formProperties->applyEntryMetadatas($form, $data);
        }

        if ($sourceUrl) {
            $this->tripleStore->create(
                $data['tag'],
                TripleStore::SOURCE_URL_URI,
                $sourceUrl,
                '',
                ''
            );
        }

        if ($justCreated->isRecorded() && !empty($olduser)) {
            $this->authenticationService->logout();
            $oldUserClass = $this->userManager->getOneByName($olduser['name']);
            if (!empty($oldUserClass)) {
                $this->authenticationService->login($oldUserClass, $olduser['remember'] ?? 1);
            }
        }
        // the account has been written as, and the activation mail decided; nothing later in the
        // request has any business believing an account was just created (ADR-0024)
        $justCreated->forget();

        $this->pageManager->cacheType($data['tag'], PageType::ENTRY);

        $this->sendMailToNotifiedEmails($sendmail, $data, true);

        if ($this->params->get('BAZ_ENVOI_MAIL_ADMIN')) {
            $this->notifier()->notifyAdmins($data, true);
        }

        $this->announce('entry.created', $form, $data, (bool)$sourceUrl);

        return $data;
    }

    /** Creating Content never writes over a page that is already there, whoever asks. */
    private function refuseTagOfAnExistingPage(?string $tag): void
    {
        if (!empty($tag) && $this->pageManager->getOne($tag, null, true, true)) {
            throw new TagAlreadyUsedException(_t('BAZ_ENTRY_TAG_ALREADY_USED', ['tag' => $tag]));
        }
    }

    /**
     * Update an entry with the provided data.
     *
     * @param bool                 $semantic
     * @param bool                 $replace  If true, all the data will be provided (no merge with the previous data)
     * @param string               $tag
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed> the entry as it was stored
     *
     * @throws \Exception
     */
    public function update($tag, $data, $semantic = false, $replace = false)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$this->aclService->hasAccess('write', $tag)) {
            throw new \Exception(_t('BAZ_ERROR_EDIT_UNAUTHORIZED'));
        }

        $data['tag'] = $tag;

        $previousData = $this->getOne($data['tag'], false, null, false, true);
        if ($previousData === null) {
            throw new \Exception("cannot update entry '{$data['tag']}': it does not exist");
        }
        $data['form_id'] = $previousData['form_id'];

        $this->validate($data, self::VALIDATE_FLAG_ANTISPAM);

        $form = $this->container->get(FormManager::class)->getOne($data['form_id']);

        $data = $this->assignRestrictedFields($data, $previousData, $form);

        if (!$replace) {
            $data = $this->mergeFields($previousData, $data, $form);
        }

        if ($semantic) {
            $data = $this->semanticTransformer->convertFromSemanticData($form, $data);
        }

        $data = $this->formatDataBeforeSave($data);

        $this->validate($data, self::VALIDATE_FLAG_TITLE | self::VALIDATE_FLAG_FORM_ID);

        $sendmail = $this->removeSendmail($data);

        $this->pageManager->save($data['tag'], $data, '');

        $formProperties = $this->container->get(FormPropertiesService::class);
        $formProperties->applyEntryAcls($form, $data);
        $formProperties->applyEntryMetadatas($form, $data);

        $this->sendMailToNotifiedEmails($sendmail, $data, false, $previousData);

        if ($this->params->get('BAZ_ENVOI_MAIL_ADMIN')) {
            $this->notifier()->notifyAdmins($data, false);
        }

        $isExternalEntry = !empty($this->tripleStore->getMatching($data['tag'], TripleStore::SOURCE_URL_URI, null, '=', '=', ''));
        $this->announce('entry.updated', $form, $data, $isExternalEntry);

        return $data;
    }

    /**
     * Replace the field values which are restricted at reading and writing.
     *
     * @param array<string, mixed> $data         the provided data to update
     * @param array<string, mixed> $previousData the provided previousData to update
     * @param array<string, mixed> $form         the entry form
     *
     * @return array<string, mixed> the data with the restricted values added
     */
    protected function assignRestrictedFields(array $data, array $previousData, array $form)
    {
        $restrictedFields = [];

        $vDefaults = [];

        foreach ($form['prepared'] as $field) {
            if ($field instanceof BazarField) {
                $propName = $field->getPropertyName();

                if (!empty($propName) && !$field->canEdit($data)) {
                    $restrictedFields[] = $propName;

                    $vDefaults[$propName] = $field->getDefault();
                }
            }
        }

        if (!empty($restrictedFields)) {
            foreach ($restrictedFields as $propName) {
                if (isset($previousData[$propName])) {
                    $data[$propName] = $previousData[$propName];
                }

                if (trim($data[$propName] ?? '') == '' && trim($vDefaults[$propName]) != '') {
                    $data[$propName] = $vDefaults[$propName];
                }
            }
        }

        return $data;
    }

    /**
     * Add the $previousData attributes which match the actual form and which are not in $data.
     *
     * @param array<string, mixed> $previousData the data saved in the entry
     * @param array<string, mixed> $form         the entry form
     * @param array<string, mixed> $data         the provided data to update
     *
     * @return array<string, mixed> the data with the merged values
     *
     * @throws \Exception
     */
    protected function mergeFields(array $previousData, array $data, array $form)
    {
        foreach ($form['prepared'] as $field) {
            if ($field instanceof BazarField) {
                $propName = $field->getPropertyName();
                if (!empty($propName) && !isset($data[$propName]) && isset($previousData[$propName])) {
                    $data[$propName] = $previousData[$propName];
                }
            }
        }

        return $data;
    }

    /**
     * Delete a fiche.
     *
     * @param string $tag
     *
     * @throws \Exception
     */
    public function delete($tag, bool $forceEvenIfNotOwner = false): void
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$forceEvenIfNotOwner && !$this->aclService->isAdmin() && !$this->aclService->isOwner($tag)) {
            throw new \Exception(_t('DELETEPAGE_NOT_DELETED') . _t('DELETEPAGE_NOT_OWNER'));
        }

        $entryToDelete = $this->getOne($tag, false, null, true, $forceEvenIfNotOwner);
        if (empty($entryToDelete)) {
            throw new \Exception("Not existing entry : $tag");
        }

        $form = $this->container->get(FormManager::class)->getOne($entryToDelete['form_id']);

        $isExternalEntry = !empty($this->tripleStore->getMatching($tag, TripleStore::SOURCE_URL_URI, null, '=', '=', ''));

        $this->pageManager->deleteOrphaned($tag);
        $this->announce('entry.deleted', $form, $entryToDelete, $isExternalEntry);
    }

    /** Legacy entry body keys => their plain-English replacements (ticket 27, ADR-0010). */
    public const LEGACY_ENTRY_KEYS = [
        'id_fiche' => 'tag',
        'id_typeannonce' => 'form_id',
        'bf_titre' => 'title',
        'date_creation_fiche' => 'created_at',
        'date_maj_fiche' => 'updated_at',
        'statut_fiche' => 'status',
    ];

    /**
     * Normalizes an already-decoded entry body (ticket 09: `pages.body` is a JSON object for every Content type, and PageManager hands it back decoded).
     *
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>|null null when there was no body to normalise
     */
    public function decode($body)
    {
        $data = $body;
        if (is_array($data)) {
            foreach (self::LEGACY_ENTRY_KEYS as $legacyKey => $key) {
                if (array_key_exists($legacyKey, $data) && !array_key_exists($key, $data)) {
                    $data[$key] = $data[$legacyKey];
                }

                if ($legacyKey !== 'bf_titre') {
                    unset($data[$legacyKey]);
                }
            }
        }

        return $data;
    }

    /**
     * prepare la requete d'insertion ou de MAJ de la fiche en supprimant de la valeur POST les valeurs inadequates et en formattant les champs.
     *
     * @param array<string, mixed> $data current raw entry values
     *
     * @return array<string, mixed> with extra calculated fields like tag, and time, and handled fields with acls
     *
     * @throws \Exception
     */
    public function formatDataBeforeSave($data): array
    {
        $data['form_id'] = isset($data['form_id']) ? $data['form_id'] : $this->container->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get()->get('form_id');

        $form = $this->container->get(FormManager::class)->getOne($data['form_id']);
        if (empty($form)) {
            throw new \Exception('No form with id: ' . $data['form_id']);
        }

        foreach ($form['prepared'] as $bazarField) {
            if ($bazarField instanceof BazarField
                && !$bazarField->requiresTagBeforeFormatting()
            ) {
                $tab = $bazarField->formatValuesBeforeSaveIfEditable($data);

                if (isset($tab['fields-to-remove']) and is_array($tab['fields-to-remove'])) {
                    foreach ($tab['fields-to-remove'] as $field) {
                        if (isset($data[$field])) {
                            unset($data[$field]);
                        }
                    }
                    unset($tab['fields-to-remove']);
                }
                $data = array_merge($data, $tab);
            }
        }

        $formProperties = $this->container->get(FormPropertiesService::class);

        if ($formProperties->createsUser($form)) {
            $tab = $formProperties->applyUserCreation($form, $data);
            if (!empty($tab)) {
                foreach ($tab['fields-to-remove'] ?? [] as $field) {
                    unset($data[$field]);
                }
                unset($tab['fields-to-remove']);
                $data = array_merge($data, $tab);
            }
        }

        $data['title'] = $formProperties->computeTitle($form, $data);

        if (!isset($data['tag'])) {
            if (empty($data['title'])) {
                throw new EntryValidationException(_t('BAZ_FICHE_NON_SAUVEE_PAS_DE_TITRE') . ' (received fields: ' . implode(', ', array_keys($data)) . ')');
            }
            $data['tag'] = $formProperties->generateTag($data['title']);
        } elseif (empty($data['tag'])) {
            throw new \Exception('$data[\'tag\'] is set but with empty value !');
        }

        foreach ($form['prepared'] as $bazarField) {
            if ($bazarField->requiresTagBeforeFormatting()) {
                $tab = $bazarField->formatValuesBeforeSaveIfEditable($data);

                if (is_array($tab)) {
                    if (isset($tab['fields-to-remove']) and is_array($tab['fields-to-remove'])) {
                        foreach ($tab['fields-to-remove'] as $field) {
                            if (isset($data[$field])) {
                                unset($data[$field]);
                            }
                        }
                        unset($tab['fields-to-remove']);
                    }
                    $data = array_merge($data, $tab);
                }
            }
        }

        $result = $this->dbService->loadSingle(
            'SELECT MIN(' . $this->dbService->quoteIdentifier('time') . ') as firsttime FROM '
            . $this->dbService->prefixTable('pages') . 'WHERE tag = ?',
            [$data['tag']]
        );
        $data['created_at'] = $data['created_at'] ?? $result['firsttime'] ?? date('Y-m-d H:i:s', time());

        if ($this->aclService->isAdmin()) {
            $data['status'] = '1';
        } else {
            $data['status'] = $this->params->get('BAZ_ETAT_VALIDATION');
        }

        if (empty($data['form_id'])) {
            throw new \Exception('$data[\'form_id\'] is empty !');
        }

        if (empty($data['tag'])) {
            throw new \Exception('$data[\'tag\'] is empty !');
        }

        $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s', time());

        unset($data['valider']);
        unset($data['MAX_FILE_SIZE']);
        unset($data['antispam']);
        unset($data[FormPropertiesService::COMMENTS_TOGGLE_POST_KEY]);
        unset($data['mot_de_passe_wikini']);
        unset($data['mot_de_passe_repete_wikini']);
        unset($data['html_data']);
        unset($data['url']);
        unset($data['incomingurl']);

        unset($data['-is-external-']);
        unset($data['external-data']);

        if (isset($data['owner'])) {
            unset($data['owner']);
        }

        if (YW_CHARSET != 'UTF-8') {
            $data = array_map(function ($value) {
                return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
            }, $data);
        }

        $data = $this->removeUnknownFields($data['form_id'], $data);

        foreach ($form['prepared'] as $vBazarField) {
            if ($vBazarField instanceof BazarField) {
                $vPropertyName = $vBazarField->getPropertyName();

                if (!empty($vPropertyName) && $vBazarField->isRequired() && $vBazarField->isEmpty($data[$vPropertyName] ?? null)) {
                    throw new EntryValidationException(_t('BAZ_CHAMPS_REQUIS') . ' : ' . ($vBazarField->getLabel() ?: $vPropertyName));
                }
            }
        }

        return $data;
    }

    /**
     * Apply field mappings to an entry.
     *
     * @param array                $pEntry
     * @param string|array         $pFieldMappings
     * @param array<string, mixed> $pEntry
     * @param string|null          $pFieldMappings
     * @param array<string, mixed> $pPage
     *
     * @return mixed the entry with modified fields
     *
     * @throws \Exception
     */
    public function applyFieldMappings(&$pEntry, $pFieldMappings, $pPage)
    {
        if (empty($pFieldMappings)) {
            return $pEntry;
        }

        $vFieldMappings = $this->getMultipleParameters($pFieldMappings, ',', '=');

        if (!empty($vFieldMappings)) {
            try {
                foreach ($vFieldMappings as $vKey => $vData) {
                    if (!empty($vKey)) {
                        if (isset($pEntry[$vData])) {
                            $pEntry[$vKey] = $this->container->get(Guard::class)->isFieldDataAuthorizedForFieldMapping($pPage, $pEntry, $vData);
                        }
                    } else {
                        echo '<div class="alert alert-danger">' . _t('BAZ_CORRESPONDANCE_ERROR') . '</div>';
                    }
                }
            } catch (ParsingMultipleException $th) {
                echo '<div class="alert alert-danger">' . str_replace("\n", '<br/>', _t('BAZ_CORRESPONDANCE_ERROR2')) . '</div>';
            }
        }

        return $pEntry;
    }

    /**
     * Append data needed for display TODO move this to a class dedicated to display.
     *
     * @param array                $pEntry
     * @param bool                 $pSemantic
     * @param string               $pFieldMappings
     * @param array                $pPage,         appendDisplayData is called in environment with access to $pPage
     *                                             helping to get owner without asking another time to the page manager to get it
     * @param array<string, mixed> $pEntry
     * @param string|null          $pFieldMappings
     * @param array<string, mixed> $pPage
     *
     * @return void
     *
     * @throws \Exception
     */
    public function appendDisplayData(&$pEntry, $pSemantic, $pFieldMappings, array $pPage)
    {
        $pEntry['user'] = $pPage['user'] ?? null;

        $pEntry['owner'] = $pPage['owner'] ?? null;

        $pEntry['updated_at'] = $pEntry['updated_at'] ?? $pPage['time'] ?? null;

        $pEntry = $this->applyFieldMappings($pEntry, $pFieldMappings, $pPage);

        $pEntry['html_data'] = $this->getHtmlDataAttributes($pEntry);

        if (!isset($pEntry['url'])) {
            $pEntry['url'] = $this->urlFormatter->href('', $pEntry['tag']);
        }

        if ($pSemantic) {
            $form = $this->container->get(FormManager::class)->getOne($pEntry['form_id']);
            $pEntry['semantic'] = $this->semanticTransformer->convertToSemanticData($form, $pEntry);
        }
    }

    /**
     * extract multiples parameters from argument.
     *
     * @param non-empty-string $firstseparator
     * @param non-empty-string $secondseparator
     *
     * @return array<string, string>
     *
     * @throws ParsingMultipleException
     */
    public function getMultipleParameters(string $param, string $firstseparator = ',', string $secondseparator = '='): array
    {
        $tabparam = [];

        if (strpos($param, $secondseparator) === false) {
            throw new ParsingMultipleException("Not able to parse multiple parameters because '$secondseparator' is not included in furnished param.");
        }
        $params = explode($firstseparator, $param);
        $params = array_map('trim', $params);
        foreach ($params as $value) {
            if (empty($value)) {
                throw new ParsingMultipleException('One parameter should not be empty !');
            }
            $tab = explode($secondseparator, $value);
            $tab = array_map('trim', $tab);
            if (count($tab) > 1) {
                $tabparam[$tab[0]] = $tab[1];
            } else {
                throw new ParsingMultipleException("One parameter does not contain '$secondseparator'!");
            }
        }

        return $tabparam;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function removeSendmail(array &$data): ?string
    {
        $sendmail = null;
        if (isset($data['sendmail'])) {
            $sendmail = $data['sendmail'];
            unset($data['sendmail']);
        }

        return $sendmail;
    }

    /**
     * @param array<string, mixed>|null $data
     * @param array<string, mixed>|null $previousEntry
     *
     * @return void
     */
    private function sendMailToNotifiedEmails(?string $sendmail, ?array $data, bool $isCreation, ?array $previousEntry = null)
    {
        if ($sendmail) {
            $emailsFieldnames = array_unique(explode(',', $sendmail));
            foreach ($emailsFieldnames as $emailFieldName) {
                if (!empty($data[$emailFieldName])) {
                    $this->notifier()->notifyEmail($data[$emailFieldName], $data, $isCreation, $previousEntry);
                }
            }
        }
    }

    /**
     * remove attributes from entries only for admins !!!
     *
     * @param array<string, mixed> $params
     * @param list<string>         $attributesNames
     *
     * @return bool true if attributesNames are foond and replaced
     */
    public function removeAttributes($params, array $attributesNames, bool $applyOnAllRevisions = false): bool
    {
        return !empty($this->removeAttributesAndReturnList($params, $attributesNames, $applyOnAllRevisions));
    }

    /**
     * remove attributes from entries only for admins !!!
     *
     * @param array<string, mixed> $params
     * @param list<string>         $attributesNames
     *
     * @return list<string> the tags of the entries whose attributes were removed
     */
    public function removeAttributesAndReturnList($params, array $attributesNames, bool $applyOnAllRevisions = false): array
    {
        return $this->manageAttributes($params, $attributesNames, $applyOnAllRevisions, 'remove');
    }

    /**
     * rename attributes from entries only for admins !!!
     *
     * @param array<string, mixed>  $params
     * @param array<string, string> $attributesNames [$oldName => $newName]
     *
     * @return bool true if attributesNames are foond and replaced
     */
    public function renameAttributes($params, array $attributesNames, bool $applyOnAllRevisions = false): bool
    {
        return !empty($this->renameAttributesAndReturnList($params, $attributesNames, $applyOnAllRevisions));
    }

    /**
     * rename attributes from entries only for admins !!!
     *
     * @param array<string, mixed>  $params
     * @param array<string, string> $attributesNames [$oldName => $newName]
     *
     * @return list<string> the tags of the entries whose attributes were renamed
     */
    public function renameAttributesAndReturnList($params, array $attributesNames, bool $applyOnAllRevisions = false): array
    {
        return $this->manageAttributes($params, $attributesNames, $applyOnAllRevisions, 'rename');
    }

    /**
     * manage attributes from entries only for admins !!!
     *
     * @param array<string, mixed> $params
     * @param array<mixed>         $attributesNames
     *
     * @return list<string> the tags of the entries that changed
     */
    private function manageAttributes($params, array $attributesNames, bool $applyOnAllRevisions = false, string $mode = 'remove'): array
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$this->aclService->isAdmin()) {
            return [];
        }

        if (empty($attributesNames)) {
            throw new \Exception('$attributesNames sould not be empty !');
        } elseif ($mode === 'rename') {
            if (!empty(array_filter(
                $attributesNames,
                function ($attributeName) {
                    return !is_array($attributeName) || count($attributeName) != 1 || !is_scalar($attributeName[array_keys($attributeName)[0]]);
                }
            ))) {
                throw new \Exception('$attributesNames sould be array of arrays with only one elem !');
            }
        } elseif (
            !empty(array_filter(
                $attributesNames,
                function ($attributeName) {
                    return !is_scalar($attributeName);
                }
            ))
        ) {
            throw new \Exception('$attributesNames sould be array of string !');
        }

        $attributesQueries = [];
        foreach ($attributesNames as $attributeName) {
            if ($mode === 'rename') {
                foreach ($attributeName as $oldName => $newName) {
                    $attributesQueries[$oldName] = '*';
                }
            } else {
                $attributesQueries[$attributeName] = '*';
            }
        }

        $params['queries'] = ($params['queries'] ?? []) + $attributesQueries;
        $requete = $this->searchManager->prepareSearchRequest($params, false, $applyOnAllRevisions);

        if ($requete->isEmpty()) {
            return [];
        }

        $pages = $this->dbService->loadAll($requete->sql, $requete->params);

        if (empty($pages)) {
            return [];
        }

        $entriesIds = [];
        foreach ($pages as $page) {
            $entry = $this->decode(PageBody::decode($page['body']));
            if ($entry === null) {
                continue;
            }

            foreach ($attributesNames as $attributeName) {
                if ($mode === 'rename') {
                    foreach ($attributeName as $oldName => $newName) {
                        if (isset($entry[$oldName])) {
                            $entry[$newName] = $entry[$oldName];
                            unset($entry[$oldName]);
                            $tag = $entry['tag'] ?? null;
                            if (is_string($tag) && $tag !== '' && !in_array($tag, $entriesIds, true)) {
                                $entriesIds[] = $tag;
                            }
                        }
                    }
                } else {
                    if (isset($entry[$attributeName])) {
                        unset($entry[$attributeName]);
                        $tag = $entry['tag'] ?? null;
                        if (is_string($tag) && $tag !== '' && !in_array($tag, $entriesIds, true)) {
                            $entriesIds[] = $tag;
                        }
                    }
                }
            }

            if (YW_CHARSET != 'UTF-8') {
                $entry = array_map(function ($value) {
                    return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                }, $entry);
            }
            if ($applyOnAllRevisions) {
                $this->dbService->query(
                    'UPDATE' . $this->dbService->prefixTable('pages') . 'SET body = ? WHERE id = ?',
                    [PageBody::encode($entry), $page['id']]
                );
            } else {
                $this->pageManager->save($entry['tag'], $entry);
            }
        }

        return $entriesIds;
    }

    /**
     * @param string $sourceTag
     * @param string $destinationTag
     */
    public function duplicate($sourceTag, $destinationTag): bool
    {
        $result = false;
        $this->journal->audit('content.duplicate', $destinationTag, ['from' => $sourceTag]);

        return $result;
    }

    /**
     * @param array<mixed> $array
     */
    protected function is_multidimensional_array(array $array): bool
    {
        foreach ($array as $item) {
            if (is_array($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function buildHtmlDataAttributes(array $data): string
    {
        $htmldata = '';
        foreach ($data as $key => $value) {
            $attributeValue = '';

            if (is_array($value)) {
                if ($this->is_multidimensional_array($value)) {
                    $attributeValue = json_encode($value);
                } else {
                    $attributeValue = '[' . implode(',', $value) . ']';
                }
            } else {
                $attributeValue = $value;
            }

            $htmldata .= 'data-' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '="' .
                     htmlspecialchars($attributeValue, ENT_QUOTES, 'UTF-8') . '" ';
        }

        return $htmldata;
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<mixed>|string  $formtab
     *
     * @return string
     */
    protected function getHtmlDataAttributes($entry, $formtab = '')
    {
        $htmldata = '';
        $filterFieldIds = [
            'form_id',
            'owner',
            'created_at',
            'date_debut_validite_fiche',
            'date_fin_validite_fiche',
            'tag',
            'status',
            'updated_at',
        ]
        ;
        $notFilterFieldIds = ['bf_titre'];
        $notFilterFieldClasses = [
            'YesWiki\Content\Field\MapField', 'YesWiki\Content\Field\HiddenField', 'YesWiki\Content\Field\FileField', 'YesWiki\Content\Field\ImageField', 'YesWiki\Content\Field\LabelField', 'YesWiki\Content\Field\LinkField', 'YesWiki\Content\Field\TextareaField',
        ];
        if (isset($entry['form_id'])) {
            $form = isset($formtab[$entry['form_id']]) ? $formtab[$entry['form_id']] : $this->container->get(FormManager::class)->getOne($entry['form_id']);
            foreach ($entry as $key => $value) {
                if (!empty($value)) {
                    if (
                        in_array(
                            $key,
                            $filterFieldIds
                        )
                    ) {
                        $htmldata .= 'data-' . htmlspecialchars($key) . '="' .
                        htmlspecialchars($value) . '" ';
                    } else {
                        if (isset($form['prepared'])) {
                            foreach ($form['prepared'] as $field) {
                                $propertyName = $field->getPropertyName();
                                if ($propertyName === $key) {
                                    if (
                                        !in_array(get_class($field), $notFilterFieldClasses)
                                        && !in_array($propertyName, $notFilterFieldIds)
                                    ) {
                                        $htmldata .= $this->buildHtmlDataAttributes([$key => $value]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $htmldata;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function search($params = [], bool $filterOnReadACL = false, bool $useGuard = false): array
    {
        return $this->searchManager->search($params, $filterOnReadACL, $useGuard);
    }
}
