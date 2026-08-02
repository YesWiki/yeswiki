<?php

namespace YesWiki\Content\Service;

use Exception;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Exception\EntryValidationException;
use YesWiki\Content\Exception\ParsingMultipleException;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Field\ImageField;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\Guard;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Mailer;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Search\Service\SearchManager;

class EntryManager
{
    protected ContainerInterface $container;
    protected $mailer;
    protected $authenticationService;
    protected $pageManager;
    protected $tripleStore;
    protected $aclService;
    protected $userManager;
    protected $dbService;
    protected $semanticTransformer;
    protected $hibernationService;
    protected $activityPubService;
    protected AdministrativeLogService $administrativeLogService;
    protected $params;
    protected $searchManager;

    private $cachedEntriestags;

    public const TRIPLES_ENTRY_ID = 'fiche_bazar';

    public const VALIDATE_FLAG_ANTISPAM = 1 << 0;
    public const VALIDATE_FLAG_TITLE = 1 << 1;
    public const VALIDATE_FLAG_FORM_ID = 1 << 2;
    public const VALIDATE_FLAG_ALL = self::VALIDATE_FLAG_ANTISPAM | self::VALIDATE_FLAG_TITLE | self::VALIDATE_FLAG_FORM_ID;

    protected UrlFormatter $urlFormatter;

    public function __construct(
        ContainerInterface $container,
        Mailer $mailer,
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
        ActivityPubService $activityPubService,
        AdministrativeLogService $administrativeLogService,
        UrlFormatter $urlFormatter,
    ) {
        $this->urlFormatter = $urlFormatter;
        $this->container = $container;
        $this->mailer = $mailer;
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
        $this->activityPubService = $activityPubService;
        $this->administrativeLogService = $administrativeLogService;
        $this->cachedEntriestags = [];
    }

    /**
     * Returns true if the provided page is a Bazar fiche.
     */
    public function isEntry($tag): bool
    {
        if (empty($tag)) {
            return false;
        }
        if (!isset($this->cachedEntriestags[$tag])) {
            $this->cachedEntriestags[$tag] = !is_null($this->tripleStore->exist($tag, TripleStore::TYPE_URI, self::TRIPLES_ENTRY_ID, '', ''));
        }

        return $this->cachedEntriestags[$tag];
    }

    /**
     * return array with list of page's tag for all entries.
     */
    public function getAllEntriesTags(): array
    {
        $result = $this->tripleStore->getMatching(null, TripleStore::TYPE_URI, self::TRIPLES_ENTRY_ID);
        if (is_array($result)) {
            $result = array_filter(array_map(function ($item) {
                return $item['resource'] ?? null;
            }, $result), function ($item) {
                return !empty($item);
            });
        } else {
            $result = [];
        }

        return $result;
    }

    /**
     * Get one specified fiche.
     *
     * @param bool        $semantic
     * @param string      $time                   pour consulter une fiche dans l'historique
     * @param bool        $cache                  if false, don't use the page cache
     * @param bool        $bypassAcls             if true, all fields are loaded regardless of acls
     * @param string|null $userNameForCheckingACL userName used to get entry, if empty uses the connected user
     *
     * @return mixed|null
     *
     * @throws \Exception
     */
    public function getOne($tag, $semantic = false, $time = null, $cache = true, $bypassAcls = false, ?string $userNameForCheckingACL = null): ?array
    {
        if (!$this->isEntry($tag)) {
            return null;
        }

        $page = $this->pageManager->getOne($tag, empty($time) ? null : $time, $cache, $bypassAcls, $userNameForCheckingACL);
        $debug = (bool)$this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)->getValue('debug');
        $data = $this->getDataFromPage($page, $semantic, $debug);

        return $data;
    }

    /*
    * Remove unknown fields
    *
    *	Remove fields that are not part of the form definition and that are not used by YesWiki framework
    *
    */

    protected function removeUnknownFields($pFormID, $pData)
    {
        /*
        We remove this code because it removes fields that are unknown in the form definition
        Recurrent event use extra fields...
        We should refactor date fields so that all informations are contained in one field as an array

                // Keep only the fields defined in the form definition

                $form = $this->container->get(FormManager::class)->getOne($pFormID);

                $vAuthorizedFields = [];

                foreach ($form['prepared'] as $field) {
                    if ($field instanceof BazarField) {
                        $propName = $field->getPropertyName();
                        // be carefull : BazarField's objects, that do not save data (as ACL, Label, Hidden), do not have propertyName
                        if (!empty($propName)) {
                            if (isset($pData[$propName])) {
                                $vAuthorizedFields[$propName] = $pData[$propName];
                            }
                        }
                    }
                }
        */
        $vAuthorizedFields = [...$pData ?? []];

        // Add extra fields that doesn't belong to the form definition
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

    /** getDataFromPage.
     * @param array  $page          , content of page from sql
     * @param bool   $debug,        to throw exception in case of error
     * @param string $fieldMapping, to pass fieldMapping parameter directly to appendDisplayData
     *
     * @return array data formated
     */
    public function getDataFromPage($page, bool $semantic = false, bool $debug = false, string $fieldMapping = ''): array
    {
        $data = [];
        if (!empty($page['body'])) {
            $data = $this->decode($page['body']);

            // if a wiki page is included in a bazar entry, this could be empty
            if (empty($data)) {
                return [];
            }

            $data = $this->removeUnknownFields($data['form_id'], $data);

            // Keep only the fields defined in the form definition
            $form = $this->container->get(FormManager::class)->getOne($data['form_id']);

            $vRegisteredData = [...$data];
            /* CORRECT BUG FOR RECURRENT EVENT
            We remove this code because it removes fields that are unknown in the form definition
            Recurrent event use extra fields...
            We should refactor date fields so that all informations are contained in one field as an array

                        foreach ($form['prepared'] as $field) {
                            if ($field instanceof BazarField) {
                                $propName = $field->getPropertyName();
                                // be carefull : BazarField's objects, that do not save data (as ACL, Label, Hidden), do not have propertyName
                                // see BazarField->formatValuesBeforeSave() for details
                                // so do not save the previous data even if existing
                                if (!empty($propName)) {
                                    if (isset($data[$propName])) {
                                        $vRegisteredData[$propName] = $data[$propName];
                                    }
                                }
                            }
                        }
            */
            // Add extra fields that doesn't belong to the form definition
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

            // cas ou on ne trouve pas les valeurs tag
            if (!isset($data['tag'])) {
                $data['tag'] = $page['tag'];
            }

            // TODO call this function only when necessary
            $this->appendDisplayData($data, $semantic, $fieldMapping, $page);
        } elseif ($debug) {
            trigger_error('empty \'body\' in EntryManager::getDataFromPage for page \'' . ($page['tag'] ?? '!!empty tag!!') . '\'', E_USER_WARNING);
        }

        return $data;
    }

    /**
     * Escape a search term the way it appears *inside* a stored body, for the SQL
     * REGEXP/LIKE patterns SearchManager matches against the JSON text.
     *
     * The flags must be PageBody's: bodies are written by PageBody::encode() with
     * unescaped unicode, so escaping the term to `\u00e9` here would never match the
     * `é` actually stored (ticket 09).
     *
     * @return string $formatedValue
     */
    private function convertToRawJSONStringForREGEXP(string $rawValue): string
    {
        $encoded = (string)json_encode($rawValue, PageBody::JSON_FLAGS);
        $valueJSON = substr($encoded, 1, strlen($encoded) - 2);
        $formattedValue = str_replace(['\\', '\''], ['\\\\', '\\\''], $valueJSON);

        return $this->dbService->escape($formattedValue);
    }

    /**
     * Validate the fiche's data.
     *
     * @throws \Exception
     */
    public function validate($data, $pFlags = self::VALIDATE_FLAG_ALL)
    {
        if ($pFlags & self::VALIDATE_FLAG_ANTISPAM) {
            if (!isset($data['antispam']) || !$data['antispam'] == 1) {
                throw new \Exception(_t('BAZ_PROTECTION_ANTISPAM'));
            }
        }

        if ($pFlags & self::VALIDATE_FLAG_TITLE) {
            // `title` is always computed from the form's entry_title_template; before
            // formatDataBeforeSave() ran, the raw source field may be all we have
            if (empty($data['title'] ?? null) && empty($data['bf_titre'] ?? null)) {
                throw new EntryValidationException(_t('BAZ_FICHE_NON_SAUVEE_PAS_DE_TITRE'));
            }
        }

        if ($pFlags & self::VALIDATE_FLAG_FORM_ID) {
            // form metadata
            if (!isset($data['form_id'])) {
                throw new \Exception(_t('BAZ_NO_FORMS_FOUND'));
            }
        }
    }

    /**
     * Create a new fiche.
     *
     * @param bool        $semantic
     * @param string|null $sourceUrl
     *
     * @return array
     *
     * @throws \Exception
     */
    public function create($formId, $data, $semantic = false, $sourceUrl = null)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $data['form_id'] = "$formId"; // Must be a string

        if ($semantic) {
            $data = $this->semanticTransformer->convertFromSemanticData($formId, $data);
        }

        // We need to check antispam before if it is removed from data
        $this->validate($data, self::VALIDATE_FLAG_ANTISPAM);

        // not possible to init the formManager in the constructor because of circular reference problem
        $form = $this->container->get(FormManager::class)->getOne($data['form_id']);

        // A built-in Content type's form does not describe entries: a page is created by
        // editing a page, an account by signing up, a file by uploading it. Creating an
        // "entry" here would produce a row typed fiche_bazar carrying a Page form's id --
        // which belongs to no list at all, since the Page form owns the untyped rows
        // (ticket 10).
        if (ContentTypeSchema::isBuiltIn($form[ContentTypeSchema::CONTENT_TYPE] ?? null)) {
            throw new \Exception("Form '{$data['form_id']}' describes the built-in '" . $form[ContentTypeSchema::CONTENT_TYPE] . "' Content type: it has no entries to create");
        }

        // replace the field values which are restricted at reading and writing with default values
        $data = $this->assignRestrictedFields($data, [], $form);

        // Let's format the data
        $data = $this->formatDataBeforeSave($data);

        // We need to check bf_titre and form_id once the data are formated
        $this->validate($data, self::VALIDATE_FLAG_TITLE | self::VALIDATE_FLAG_FORM_ID);

        // on change provisoirement d'utilisateur
        if (isset($GLOBALS['created_user_name'])) {
            $olduser = $this->authenticationService->getLoggedUser();
            $this->authenticationService->logout();

            // On s'identifie de facon a attribuer la propriete de la fiche a
            // l'utilisateur qui vient d etre cree
            $user = $this->userManager->getOneByName($GLOBALS['created_user_name']);
            $this->authenticationService->login($user);
        }

        $ignoreAcls = true;
        if ($this->params->has('bazarIgnoreAcls')) {
            $ignoreAcls = $this->params->get('bazarIgnoreAcls');
        }

        // get the sendmail and remove it before saving
        $sendmail = $this->removeSendmail($data);

        // on sauve les valeurs d'une fiche dans une PageWiki, retourne 0 si succès
        $saved = $this->pageManager->save(
            $data['tag'],
            $data,
            '',
            $ignoreAcls, // Ignore les ACLs
            $data['updated_at']
        );

        // on cree un triple pour specifier que la page wiki creee est une fiche
        // bazar
        if ($saved == 0) {
            $this->tripleStore->create(
                $data['tag'],
                TripleStore::TYPE_URI,
                self::TRIPLES_ENTRY_ID,
                '',
                ''
            );

            // Form properties needing the saved page: entry ACLs (+ the author's
            // comments-toggle choice) and presentation metadata -- they write to the
            // page's metadata, not into the entry body (ADR-0010)
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

        // on remet l'utilisateur initial s'il y en avait un
        if (isset($GLOBALS['created_user_name']) && !empty($olduser)) {
            $this->authenticationService->logout();
            $oldUserClass = $this->userManager->getOneByName($olduser['name']);
            if (!empty($oldUserClass)) {
                $this->authenticationService->login($oldUserClass, $olduser['remember'] ?? 1);
            }
        }

        $this->cachedEntriestags[$data['tag']] = true;

        // if sendmail has referenced email fields, send an email to their adresses
        $this->sendMailToNotifiedEmails($sendmail, $data, true);

        if ($this->params->get('BAZ_ENVOI_MAIL_ADMIN')) {
            // Envoi d'un mail aux administrateurs
            $this->mailer->notifyAdmins($data, true);
        }

        if ($this->activityPubService->isEnabled($form) && !$sourceUrl) {
            // Notify followers about the new object
            $this->activityPubService->notifyFollowers($form, $data, 'Create');
        }

        return $data;
    }

    /**
     * Update an entry with the provided data.
     *
     * @param bool $semantic
     * @param bool $replace  If true, all the data will be provided (no merge with the previous data)
     *
     * @return array
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

        // replace tag with $tag to prevent errors before getOne
        $data['tag'] = $tag;
        // if there are some restricted fields, load the previous data by bypassing the rights
        $previousData = $this->getOne($data['tag'], false, null, false, true);
        $data['form_id'] = $previousData['form_id'];

        // We need to check antispam before data are modified

        $this->validate($data, self::VALIDATE_FLAG_ANTISPAM);

        // not possible to init the formManager in the constructor because of circular reference problem
        $form = $this->container->get(FormManager::class)->getOne($data['form_id']);

        // replace the field values which are restricted at reading and writing
        $data = $this->assignRestrictedFields($data, $previousData, $form);

        if (!$replace) {
            // merge the field values which match to the actual form and which are not in $data
            $data = $this->mergeFields($previousData, $data, $form);
        }

        if ($semantic) {
            $data = $this->semanticTransformer->convertFromSemanticData($data['form_id'], $data);
        }

        // Let's get formatted values (it will format each values and take into account access right and defaut values)
        $data = $this->formatDataBeforeSave($data);

        // Title can be automatic, we need to check it now. Check also form_id (necessary ?)

        $this->validate($data, self::VALIDATE_FLAG_TITLE | self::VALIDATE_FLAG_FORM_ID);

        // get the sendmail and remove it before saving
        $sendmail = $this->removeSendmail($data);
        // on sauve les valeurs d'une fiche dans une PageWiki, pour garder l'historique
        $this->pageManager->save($data['tag'], $data, '');

        $formProperties = $this->container->get(FormPropertiesService::class);
        $formProperties->applyEntryAcls($form, $data);
        $formProperties->applyEntryMetadatas($form, $data);

        // if sendmail has referenced email fields, send an email to their adresses
        $this->sendMailToNotifiedEmails($sendmail, $data, false, $previousData);

        if ($this->params->get('BAZ_ENVOI_MAIL_ADMIN')) {
            // Envoi d'un mail aux administrateurs
            $this->mailer->notifyAdmins($data, false);
        }

        $isExternalEntry = !empty($this->tripleStore->getMatching($data['tag'], TripleStore::SOURCE_URL_URI, null, '=', '=', ''));
        if ($this->activityPubService->isEnabled($form) && !$isExternalEntry) {
            // Notify followers about the updated object (skip if external)
            $this->activityPubService->notifyFollowers($form, $data, 'Update');
        }

        return $data;
    }

    /**
     * Replace the field values which are restricted at reading and writing. These values must be loaded to save them
     * without user modification.
     * As the fields are rectricted at reading, the right must be bypassed to load them.
     *
     * @param array $data         the provided data to update
     * @param array $previousData the provided previousData to update
     * @param array $form         the entry form
     *
     * @return array the data with the restricted values added
     */
    protected function assignRestrictedFields(array $data, array $previousData, array $form)
    {
        // check if there are some restricted fields at writing
        $restrictedFields = [];

        $vDefaults = [];

        foreach ($form['prepared'] as $field) {
            if ($field instanceof BazarField) {
                $propName = $field->getPropertyName();
                // be carefull : BazarField's objects, that do not save data (as ACL, Label, Hidden), do not have propertyName
                // see BazarField->formatValuesBeforeSave() for details
                // so do not save the previous data even if existing
                if (!empty($propName) && !$field->canEdit($data)) {
                    $restrictedFields[] = $propName;

                    $vDefaults[$propName] = $field->getDefault();
                }
            }
        }

        if (!empty($restrictedFields)) {
            // get the value of the restricted fields in the previous data
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
     * @param array $previousData the data saved in the entry
     * @param array $form         the entry form
     * @param array $data         the provided data to update
     *
     * @return array the data with the merged values
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
     * @throws \Exception
     */
    public function publish($entryId, $accepted)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        // not possible to init the Guard in the constructor because of circular reference problem
        if ($this->container->get(Guard::class)->isAllowed('valider_fiche')) {
            if ($accepted) {
                $this->dbService->query('UPDATE ' . $this->dbService->prefixTable('fiche') . " SET bf_statut_fiche=1 WHERE bf_id_fiche='" . $this->dbService->escape($entryId) . "'");
            } else {
                $this->dbService->query('UPDATE ' . $this->dbService->prefixTable('fiche') . " SET bf_statut_fiche=2 WHERE bf_id_fiche='" . $this->dbService->escape($entryId) . "'");
            }
            // TODO envoie mail annonceur
        }
    }

    /**
     * Delete a fiche.
     *
     * @throws \Exception
     */
    public function delete($tag, bool $forceEvenIfNotOwner = false)
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
        $this->administrativeLogService->log(
            $this->authenticationService->getLoggedUserName(),
            'Suppression de la page ->""' . $tag . '""'
        );
        if ($this->activityPubService->isEnabled($form) && !$isExternalEntry) {
            // Notify followers about the deleted object
            $this->activityPubService->notifyFollowers($form, $entryToDelete, 'Delete');
        }

        unset($this->cachedEntriestags[$tag]);
    }

    /*
     * Convert body to JSON object
     */
    /**
     * Legacy entry body keys => their plain-English replacements (ticket 27,
     * ADR-0010). RenameEntryBodyKeys converts stored latest revisions; this map is
     * the read-side insurance for anything older (old revisions, remote payloads).
     */
    public const LEGACY_ENTRY_KEYS = [
        'id_fiche' => 'tag',
        'id_typeannonce' => 'form_id',
        'bf_titre' => 'title',
        'date_creation_fiche' => 'created_at',
        'date_maj_fiche' => 'updated_at',
        'statut_fiche' => 'status',
    ];

    /**
     * Normalizes an already-decoded entry body (ticket 09: `pages.body` is a JSON object
     * for every Content type, and PageManager hands it back decoded).
     *
     * @param array<string, mixed>|null $body
     */
    public function decode($body)
    {
        $data = $body;
        if (is_iterable($data)) {
            foreach (self::LEGACY_ENTRY_KEYS as $legacyKey => $key) {
                if (array_key_exists($legacyKey, $data) && !array_key_exists($key, $data)) {
                    $data[$key] = $data[$legacyKey];
                }
                // bf_titre stays as ordinary field data; it only ALSO feeds `title`
                if ($legacyKey !== 'bf_titre') {
                    unset($data[$legacyKey]);
                }
            }
        }

        return $data;
    }

    /**
     * prepare la requete d'insertion ou de MAJ de la fiche en supprimant
     * de la valeur POST les valeurs inadequates et en formattant les champs.
     *
     * @param $data current raw entry values
     *
     * @return array with extra calculated fields like tag, and time, and handled fields with acls
     *
     * @throws \Exception
     */
    public function formatDataBeforeSave($data): array
    {
        // Let's set the value of form_id

        $data['form_id'] = isset($data['form_id']) ? $data['form_id'] : $this->container->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get()->get('form_id');

        // not possible to init the formManager in the constructor because of circular reference problem
        $form = $this->container->get(FormManager::class)->getOne($data['form_id']);
        if (empty($form)) {
            throw new \Exception('No form with id: ' . $data['form_id']);
        }

        // We first need to ensure default values for uneditable fields are set
        // so we can use it later to build the automatic title if necessary

        foreach ($form['prepared'] as $bazarField) {
            if ($bazarField instanceof BazarField
                && !$bazarField->requiresTagBeforeFormatting() // Some fields like ImageField and File Field need the tag to be defined before to call formatValuesBeforeSave. So we will handle them later.
            ) {
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

        $formProperties = $this->container->get(FormPropertiesService::class);

        // Account creation (entry_creates_user): must run before title/tag generation
        // so the created user's name is available (owner attribution, `user` ACLs)
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

        // The entry title is always computed from the form's entry_title_template
        // (ADR-0010); `title` is the canonical key, `bf_titre` is just a field a
        // template may reference
        $data['title'] = $formProperties->computeTitle($form, $data);

        // Let's generate the entry tag if necessary: a lowercase slug of the title
        if (!isset($data['tag'])) {
            if (empty($data['title'])) {
                throw new EntryValidationException(_t('BAZ_FICHE_NON_SAUVEE_PAS_DE_TITRE') . ' (received fields: ' . implode(', ', array_keys($data)) . ')');
            }
            $data['tag'] = $formProperties->generateTag($data['title']);
        } elseif (empty($data['tag'])) {
            throw new \Exception('$data[\'tag\'] is set but with empty value !');
        }

        // We can now handle fields like ImageField and File Field that require tag in order to format their values

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

        // Get creation date if it exists, initialize it otherwise
        $tag = $this->dbService->escape($data['tag']);
        $result = $this->dbService->loadSingle('SELECT MIN(time) as firsttime FROM ' . $this->dbService->prefixTable('pages') . "WHERE tag='" . $tag . "'");
        $data['created_at'] = $data['created_at'] ?? $result['firsttime'] ?? date('Y-m-d H:i:s', time());

        // Entry status
        if ($this->aclService->isAdmin()) {
            $data['status'] = '1';
        } else {
            $data['status'] = $this->params->get('BAZ_ETAT_VALIDATION');
        }

        // Let's ensure $data['form_id'] is not empty
        if (empty($data['form_id'])) {
            throw new \Exception('$data[\'form_id\'] is empty !');
        }

        // Let's ensure $data['tag'] is not empty
        if (empty($data['tag'])) {
            throw new \Exception('$data[\'tag\'] is empty !');
        }

        $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s', time());

        // on enleve les champs hidden ou non necessaires a la fiche
        // (submission artifacts are stripped, never stored -- ADR-0010)
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

        // on nettoie le champ owner qui n'est pas sauvegardé (champ owner de la page)
        if (isset($data['owner'])) {
            unset($data['owner']);
        }

        // on encode en utf-8 pour reussir a encoder en json TODO: still necessary ?
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
                    // named by its label, and typed as the visitor's problem rather than
                    // the site's: this used to surface as "an unexpected error occurred,
                    // contact the administrator" quoting an internal field identifier
                    throw new EntryValidationException(_t('BAZ_CHAMPS_REQUIS') . ' : ' . ($vBazarField->getLabel() ?: $vPropertyName));
                }
            }
        }

        return $data;
    }

    /**
     * Apply field mappings to an entry.
     *
     * @param array        $pEntry
     * @param string|array $pFieldMappings
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

        if (is_string($pFieldMappings)) {
            $vFieldMappings = $this->getMultipleParameters($pFieldMappings, ',', '=');
        } else {
            $vFieldMappings = $pFieldMappings;
        }

        // champs correspondants
        if (!empty($vFieldMappings)) {
            try {
                foreach ($vFieldMappings as $vKey => $vData) {
                    if (isset($vKey)) {
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
     * Append data needed for display
     * TODO move this to a class dedicated to display.
     *
     * @param array  $pEntry
     * @param bool   $pSemantic
     * @param string $pFieldMappings
     * @param array  $pPage,         appendDisplayData is called in environment with access to $pPage
     *                               helping to get owner without asking another time to the page manager to get it
     *
     * @throws \Exception
     */
    public function appendDisplayData(&$pEntry, $pSemantic, $pFieldMappings, array $pPage)
    {
        // user
        $pEntry['user'] = $pPage['user'] ?? null;
        // owner
        $pEntry['owner'] = $pPage['owner'] ?? null;

        $pEntry = $this->applyFieldMappings($pEntry, $pFieldMappings, $pPage);

        // HTML data
        $pEntry['html_data'] = $this->getHtmlDataAttributes($pEntry);

        // pEntry URL
        if (!isset($pEntry['url'])) {
            // could already be defined for entries from external json
            $pEntry['url'] = $this->urlFormatter->href('', $pEntry['tag']);
        }

        // Données sémantiques
        if ($pSemantic) {
            // not possible to init the formManager in the constructor because of circular reference problem
            $form = $this->container->get(FormManager::class)->getOne($pEntry['form_id']);
            $pEntry['semantic'] = $this->semanticTransformer->convertToSemanticData($form, $pEntry);
        }
    }

    /**
     * extract multiples parameters from argument.
     *
     * @param string $firstseparator
     * @param string $secondseparator
     *
     * @throws ParsingMultipleException
     */
    public function getMultipleParameters(string $param, $firstseparator = ',', $secondseparator = '='): array
    {
        // This function's aim is to fetch (key , value) couples stored in a multiple parameter
        // $param is the parameter where we have to fecth the couples
        // $firstseparator is the separator between the couples (usually ',')
        // $secondseparator is the separator between key and value in each couple (usually '=')
        // Returns the table of (key , value) couples
        // If fails to explode the data, then throws ParsingMultipleException
        $tabparam = [];
        // check if first and second separators are at least somewhere
        if (strpos($param, $secondseparator) === false) {
            throw new ParsingMultipleException("Not able to parse multiple parameters because '$secondseparator' is not included in furnished param.");
        }
        $params = explode($firstseparator, $param);
        $params = array_map('trim', $params);
        if (count($params) == 0) {
            throw new ParsingMultipleException('There is no parameter to parse !');
        }
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

    private function removeSendmail(array &$data): ?string
    {
        $sendmail = null;
        if (isset($data['sendmail'])) {
            $sendmail = $data['sendmail'];
            unset($data['sendmail']);
        }

        return $sendmail;
    }

    private function sendMailToNotifiedEmails(?string $sendmail, ?array $data, bool $isCreation, ?array $previousEntry = null)
    {
        if ($sendmail) {
            $emailsFieldnames = array_unique(explode(',', $sendmail));
            foreach ($emailsFieldnames as $emailFieldName) {
                if (!empty($data[$emailFieldName])) {
                    $this->mailer->notifyEmail($data[$emailFieldName], $data, $isCreation, $previousEntry);
                }
            }
        }
    }

    /**
     * sanitize formsIds and get forms.
     *
     * @return array $forms
     */
    private function getFormsFromIds($formsIds): array
    {
        $formManager = $this->container->get(FormManager::class); // not load in contruct to prevent circular loading
        if (!empty($formsIds)) {
            if (is_scalar($formsIds)) {
                $formsIds = [$formsIds];
            }
            if (is_array($formsIds)) {
                $formsIds = array_filter($formsIds, function ($formId) {
                    return is_scalar($formId) && (strval(intval($formId)) == strval($formId));
                });
            } else {
                $formsIds = null;
            }
        }
        if (!empty($formsIds)) {
            return $formManager->getMany($formsIds);
        }

        return $formManager->getAll();
    }

    /**
     * remove attributes from entries only for admins !!!
     *
     * @param array $params
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
     * @param array $params
     *
     * @return array with entry's ids if attributesNames are found and replaced
     */
    public function removeAttributesAndReturnList($params, array $attributesNames, bool $applyOnAllRevisions = false): array
    {
        return $this->manageAttributes($params, $attributesNames, $applyOnAllRevisions, 'remove');
    }

    /**
     * rename attributes from entries only for admins !!!
     *
     * @param array $params
     * @param array $attributesNames [$oldName => $newName]
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
     * @param array $params
     * @param array $attributesNames [$oldName => $newName]
     *
     * @return array with entry's ids if attributesNames are found and replaced
     */
    public function renameAttributesAndReturnList($params, array $attributesNames, bool $applyOnAllRevisions = false): array
    {
        return $this->manageAttributes($params, $attributesNames, $applyOnAllRevisions, 'rename');
    }

    /**
     * manage attributes from entries only for admins !!!
     *
     * @param array $params
     *
     * @return array with entry's ids if attributesNames are found and replaced
     */
    private function manageAttributes($params, array $attributesNames, bool $applyOnAllRevisions = false, string $mode = 'remove'): array
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$this->aclService->isAdmin()) {
            return [];
        }

        /* sanitize params */
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
        // add search for attributes
        $params['queries'] = ($params['queries'] ?? []) + $attributesQueries;
        $requete = $this->searchManager->prepareSearchRequest($params, false, $applyOnAllRevisions);

        if ($requete === '') {
            return [];
        }

        $pages = $this->dbService->loadAll($requete);

        if (empty($pages)) {
            return [];
        }

        $entriesIds = [];
        foreach ($pages as $page) {
            // raw SQL rows here, so the body is still stored text
            $entry = $this->decode(PageBody::decode($page['body']));

            foreach ($attributesNames as $attributeName) {
                if ($mode === 'rename') {
                    foreach ($attributeName as $oldName => $newName) {
                        if (isset($entry[$oldName])) {
                            $entry[$newName] = $entry[$oldName];
                            unset($entry[$oldName]);
                            if (!empty($entry['tag']) && !in_array($entry['tag'], $entriesIds)) {
                                $entriesIds[] = $entry['tag'];
                            }
                        }
                    }
                } else {
                    if (isset($entry[$attributeName])) {
                        unset($entry[$attributeName]);
                        if (!empty($entry['tag']) && !in_array($entry['tag'], $entriesIds)) {
                            $entriesIds[] = $entry['tag'];
                        }
                    }
                }
            }

            // save
            // on encode en utf-8 pour reussir a encoder en json
            if (YW_CHARSET != 'UTF-8') {
                $entry = array_map(function ($value) {
                    return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                }, $entry);
            }
            if ($applyOnAllRevisions) {
                $this->dbService->query('UPDATE' . $this->dbService->prefixTable('pages') . "SET body = '" . $this->dbService->escape(PageBody::encode($entry)) . "'" .
                    " WHERE id = '" . $this->dbService->escape($page['id']) . "';");
            } else {
                $this->pageManager->save($entry['tag'], $entry);
            }
        }

        return $entriesIds;
    }

    private function duplicate($sourceTag, $destinationTag): bool
    {
        $result = false;
        $this->administrativeLogService->log($this->authenticationService->getLoggedUserName(), 'Duplication de la fiche ""' . $sourceTag . '"" vers la fiche ""' . $destinationTag . '""');

        return $result;
    }

    protected function is_multidimensional_array(array $array): bool
    {
        foreach ($array as $item) {
            if (is_array($item)) {
                return true;
            }
        }

        return false;
    }

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

            // Always HTML-escape the key and the attribute value
            $htmldata .= 'data-' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '="' .
                     htmlspecialchars($attributeValue, ENT_QUOTES, 'UTF-8') . '" ';
        }

        return $htmldata;
    }

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
        if (is_array($entry) && isset($entry['form_id'])) {
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

    /* SEARCH : DEPRECATED use SearchManager->search instead */

    public function search($params = [], bool $filterOnReadACL = false, bool $useGuard = false): array
    {
        return $this->searchManager->search($params, $filterOnReadACL, $useGuard);
    }
}
