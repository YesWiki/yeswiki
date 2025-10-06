<?php

namespace YesWiki\Bazar\Service;

use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Exception\ParsingMultipleException;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\TitleField;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\Mailer;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

class EntryManager
{
    protected $wiki;
    protected $mailer;
    protected $authController;
    protected $pageManager;
    protected $tripleStore;
    protected $aclService;
    protected $userManager;
    protected $dbService;
    protected $semanticTransformer;
    protected $securityController;
    protected $params;
    protected $searchManager;

    private $cachedEntriestags;

    public const TRIPLES_ENTRY_ID = 'fiche_bazar';

    public const VALIDATE_FLAG_ANTISPAM = 1 << 0;
    public const VALIDATE_FLAG_BF_TITRE = 1 << 1;
    public const VALIDATE_FLAG_ID_TYPEANNONCE = 1 << 2;
    public const VALIDATE_FLAG_ALL = self::VALIDATE_FLAG_ANTISPAM | self::VALIDATE_FLAG_BF_TITRE | self::VALIDATE_FLAG_ID_TYPEANNONCE;

    public function __construct(
        Wiki $wiki,
        Mailer $mailer,
        AuthController $authController,
        PageManager $pageManager,
        TripleStore $tripleStore,
        AclService $aclService,
        UserManager $userManager,
        DbService $dbService,
        SemanticTransformer $semanticTransformer,
        ParameterBagInterface $params,
        SearchManager $searchManager,
        SecurityController $securityController
    ) {
        $this->wiki = $wiki;
        $this->mailer = $mailer;
        $this->authController = $authController;
        $this->pageManager = $pageManager;
        $this->tripleStore = $tripleStore;
        $this->aclService = $aclService;
        $this->userManager = $userManager;
        $this->dbService = $dbService;
        $this->semanticTransformer = $semanticTransformer;
        $this->params = $params;
        $this->searchManager = $searchManager;
        $this->securityController = $securityController;
        $this->cachedEntriestags = [];
    }

    /**
     * Returns true if the provided page is a Bazar fiche.
     *
     * @param $tag
     */
    public function isEntry($tag): bool
    {
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
     * @param $tag
     * @param bool        $semantic
     * @param string      $time                   pour consulter une fiche dans l'historique
     * @param bool        $cache                  if false, don't use the page cache
     * @param bool        $bypassAcls             if true, all fields are loaded regardless of acls
     * @param string|null $userNameForCheckingACL userName used to get entry, if empty uses the connected user
     *
     * @return mixed|null
     *
     * @throws Exception
     */
    public function getOne($tag, $semantic = false, $time = null, $cache = true, $bypassAcls = false, ?string $userNameForCheckingACL = null): ?array
    {
        if (!$this->isEntry($tag)) {
            return null;
        }

        $page = $this->pageManager->getOne($tag, empty($time) ? null : $time, $cache, $bypassAcls, $userNameForCheckingACL);
        $debug = ($this->wiki->GetConfigValue('debug') == 'yes');
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
		// Keep only the fields defined in the form definition

        $form = $this->wiki->services->get(FormManager::class)->getOne($pFormID);

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

        // Add extra fields that doesn't belong to the form definition

        if (isset($pData['id_fiche'])) {
            $vAuthorizedFields['id_fiche'] = $pData['id_fiche'];
        }
        if (isset($pData['id_typeannonce'])) {
            $vAuthorizedFields['id_typeannonce'] = $pData['id_typeannonce'];
        }
        if (isset($pData['date_creation_fiche'])) {
            $vAuthorizedFields['date_creation_fiche'] = $pData['date_creation_fiche'];
        }
        if (isset($pData['date_maj_fiche'])) {
            $vAuthorizedFields['date_maj_fiche'] = $pData['date_maj_fiche'];
        }
        if (isset($pData['statut_fiche'])) {
            $vAuthorizedFields['statut_fiche'] = $pData['statut_fiche'];
        }
        if (isset($pData['url'])) {
            $vAuthorizedFields['url'] = $pData['url'];
        }
	
		return $vAuthorizedFields;
	}
	
    /** getDataFromPage.
     * @param array  $page            , content of page from sql
     * @param bool   $debug,          to throw exception in case of error
     * @param string $correspondance, to pass correspondance parameter directly to appendDisplayData
     *
     * @return array data formated
     */
    private function getDataFromPage($page, bool $semantic = false, bool $debug = false, string $correspondance = ''): array
    {
        $data = [];
        if (!empty($page['body'])) {
            $data = $this->decode($page['body']);

            $data = $this->removeUnknownFields($data['id_typeannonce'], $data);

            if ($debug) {
                if (empty($data['id_fiche'])) {
                    trigger_error('empty \'id_fiche\' in EntryManager::getDataFromPage in body of page \''
                        . $page['tag'] . '\'. Edit it to create id_fiche', E_USER_WARNING);
                }
                if (empty($page['tag'])) {
                    trigger_error('empty $page[\'tag\'] in EntryManager::getDataFromPage! ', E_USER_WARNING);
                }
            }

            // cas ou on ne trouve pas les valeurs id_fiche
            if (!isset($data['id_fiche'])) {
                $data['id_fiche'] = $page['tag'];
            }

            // TODO call this function only when necessary
            $this->appendDisplayData($data, $semantic, $correspondance, $page);
        } elseif ($debug) {
            trigger_error('empty \'body\'  in EntryManager::getDataFromPage for page \'' . ($page['tag'] ?? '!!empty tag!!') . '\'', E_USER_WARNING);
        }

        return $data;
    }

    /**
     * Return an array of fiches based on search parameters.
     *
     * @param array $params
     *
     * @return mixed
     */
    public function search($params = [], bool $filterOnReadACL = false, bool $useGuard = false): array
    {
        $requete = $this->searchManager->prepareSearchRequest($params, $filterOnReadACL);
        $searchResults = [];
        $results = $this->dbService->loadAll($requete);
        $debug = ($this->wiki->GetConfigValue('debug') == 'yes');
        foreach ($results as $page) {
            // save owner to reduce sql calls
            $this->pageManager->cacheOwner($page);
            // not possible to init the Guard in the constructor because of circular reference problem
            $filteredPage = (!$this->wiki->UserIsAdmin() && $useGuard)
                ? $this->wiki->services->get(Guard::class)->checkAcls($page, $page['tag'])
                : $page;
            $data = $this->getDataFromPage($filteredPage, false, $debug, $params['correspondance']);
            $searchResults[$data['id_fiche']] = $data;
        }

        return $searchResults;
    }

    /** format data as in sql.
     * @return string $formatedValue
     */
    private function convertToRawJSONStringForREGEXP(string $rawValue): string
    {
        $valueJSON = substr(json_encode($rawValue), 1, strlen(json_encode($rawValue)) - 2);
        $formattedValue = str_replace(['\\', '\''], ['\\\\', '\\\''], $valueJSON);

        return $this->dbService->escape($formattedValue);
    }

    /**
     * Validate the fiche's data.
     *
     * @param $data
     *
     * @throws Exception
     */
    public function validate($data, $pFlags = self::VALIDATE_FLAG_ALL)
    {
        if ($pFlags & self::VALIDATE_FLAG_ANTISPAM) {
            if (!isset($data['antispam']) or !$data['antispam'] == 1) {
                throw new Exception(_t('BAZ_PROTECTION_ANTISPAM'));
            }
        }

        // On teste le titre car ça peut bugguer sérieusement sans

        if ($pFlags & self::VALIDATE_FLAG_BF_TITRE) {
            if (!isset($data['bf_titre'])) {
                throw new Exception(_t('BAZ_FICHE_NON_SAUVEE_PAS_DE_TITRE'));
            }
        }

        if ($pFlags & self::VALIDATE_FLAG_ID_TYPEANNONCE) {
            // form metadata
            if (!isset($data['id_typeannonce'])) {
                throw new Exception(_t('BAZ_NO_FORMS_FOUND'));
            }
        }
    }

    /**
     * Create a new fiche.
     *
     * @param $formId
     * @param $data
     * @param false $semantic
     * @param null  $sourceUrl
     *
     * @return array
     *
     * @throws Exception
     */
    public function create($formId, $data, $semantic = false, $sourceUrl = null)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $data['id_typeannonce'] = "$formId"; // Must be a string

        if ($semantic) {
            $data = $this->semanticTransformer->convertFromSemanticData($formId, $data);
        }

        // We need to check antispam before if it is removed from data

        $this->validate($data, self::VALIDATE_FLAG_ANTISPAM);

		// not possible to init the formManager in the constructor because of circular reference problem
        $form = $this->wiki->services->get(FormManager::class)->getOne($data['id_typeannonce']);

		// replace the field values which are restricted at reading and writing with default values
        $data = $this->assignRestrictedFields($data, [], $form);

        // Let's format the data

        $data = $this->formatDataBeforeSave($data);
        
        // We need to check bf_titre and id_typeannonce once the data are formated

        $this->validate($data, self::VALIDATE_FLAG_BF_TITRE | self::VALIDATE_FLAG_ID_TYPEANNONCE);

        // on change provisoirement d'utilisateur
        if (isset($GLOBALS['utilisateur_wikini'])) {
            $olduser = $this->authController->getLoggedUser();
            $this->authController->logout();

            // On s'identifie de facon a attribuer la propriete de la fiche a
            // l'utilisateur qui vient d etre cree
            $user = $this->userManager->getOneByName($GLOBALS['utilisateur_wikini']);
            $this->authController->login($user);
        }

        $ignoreAcls = true;
        if ($this->params->has('bazarIgnoreAcls')) {
            $ignoreAcls = $this->params->get('bazarIgnoreAcls');
        }

        // get the sendmail and remove it before saving
        $sendmail = $this->removeSendmail($data);

        // on sauve les valeurs d'une fiche dans une PageWiki, retourne 0 si succès
        $saved = $this->pageManager->save(
            $data['id_fiche'],
            json_encode($data),
            '',
            $ignoreAcls, // Ignore les ACLs
            $data['date_maj_fiche']
        );

        // on cree un triple pour specifier que la page wiki creee est une fiche
        // bazar
        if ($saved == 0) {
            $this->tripleStore->create(
                $data['id_fiche'],
                TripleStore::TYPE_URI,
                self::TRIPLES_ENTRY_ID,
                '',
                ''
            );
        }

        if ($sourceUrl) {
            $this->tripleStore->create(
                $data['id_fiche'],
                TripleStore::SOURCE_URL_URI,
                $sourceUrl,
                '',
                ''
            );
        }

        // on remet l'utilisateur initial s'il y en avait un
        if (isset($GLOBALS['utilisateur_wikini']) && !empty($olduser)) {
            $this->authController->logout();
            $oldUserClass = $this->userManager->getOneByName($olduser['name']);
            if (!empty($oldUserClass)) {
                $this->authController->login($oldUserClass, $olduser['remember'] ?? 1);
            }
        }

        $this->cachedEntriestags[$data['id_fiche']] = true;

        // if sendmail has referenced email fields, send an email to their adresses
        $this->sendMailToNotifiedEmails($sendmail, $data, true);

        if ($this->params->get('BAZ_ENVOI_MAIL_ADMIN')) {
            // Envoi d'un mail aux administrateurs
            $this->mailer->notifyAdmins($data, true);
        }

        return $data;
    }

    /**
     * Update an entry with the provided data.
     *
     * @param $tag
     * @param $data
     * @param false $semantic
     * @param false $replace  If true, all the data will be provided (no merge with the previous data)
     *
     * @return array
     *
     * @throws Exception
     */
    public function update($tag, $data, $semantic = false, $replace = false)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$this->aclService->hasAccess('write', $tag)) {
            throw new Exception(_t('BAZ_ERROR_EDIT_UNAUTHORIZED'));
        }

        // replace id_fiche with $tag to prevent errors before getOne
        $data['id_fiche'] = $tag;
        // if there are some restricted fields, load the previous data by bypassing the rights
        $previousData = $this->getOne($data['id_fiche'], false, null, false, true);

        $data['id_typeannonce'] = $previousData['id_typeannonce'];

		// We need to check antispam before data are modified
        
        $this->validate($data, self::VALIDATE_FLAG_ANTISPAM);

        // not possible to init the formManager in the constructor because of circular reference problem
        $form = $this->wiki->services->get(FormManager::class)->getOne($data['id_typeannonce']);

		// replace the field values which are restricted at reading and writing
        $data = $this->assignRestrictedFields($data, $previousData, $form);

        if (!$replace) {
            // merge the field values which match to the actual form and which are not in $data
            $data = $this->mergeFields($previousData, $data, $form);
        }

        if ($semantic) {
            $data = $this->semanticTransformer->convertFromSemanticData($data['id_typeannonce'], $data);
        }

		// Let's get formatted values (it will format each values and take into account access right and defaut values)
		$data = $this->formatDataBeforeSave($data);       
        
        // Title can be automatic, we need to check it now. Check also id_typeannonce (necessary ?)
        
        $this->validate($data, self::VALIDATE_FLAG_BF_TITRE|self::VALIDATE_FLAG_ID_TYPEANNONCE);

//        $this->validate($data);

        // get the sendmail and remove it before saving
        $sendmail = $this->removeSendmail($data);
        // on sauve les valeurs d'une fiche dans une PageWiki, pour garder l'historique
        $this->pageManager->save($data['id_fiche'], json_encode($data), '');

        // if sendmail has referenced email fields, send an email to their adresses
        $this->sendMailToNotifiedEmails($sendmail, $data, false, $previousData);

        if ($this->params->get('BAZ_ENVOI_MAIL_ADMIN')) {
            // Envoi d'un mail aux administrateurs
            $this->mailer->notifyAdmins($data, false);
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
                    
                    $vDefaults[$propName] = $field->getDefault ();
                }
            }
        }

        if (!empty($restrictedFields)) {
            // get the value of the restricted fields in the previous data
            foreach ($restrictedFields as $propName) {
                if (isset($previousData[$propName])) {
                    $data[$propName] = $previousData[$propName];
                } 
                
                if (trim ($data[$propName]) == "" && trim ($vDefaults[$propName]) != "")
                {	 
                	$data[$propName] = $vDefaults[$propName];
                }
                
                if ($field->isRequired () && trim ($data[$propName]) == "")
               	{
                	throw new Exception(_t('BAZ_CHAMPS_REQUIS' . ":" . $propName ));
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
     * @throws Exception
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
     * @param $entryId
     * @param $accepted
     *
     * @throws Exception
     */
    public function publish($entryId, $accepted)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        // not possible to init the Guard in the constructor because of circular reference problem
        if ($this->wiki->services->get(Guard::class)->isAllowed('valider_fiche')) {
            if ($accepted) {
                $this->dbService->query('UPDATE' . $this->dbService->prefixTable('fiche') . 'SET bf_statut_fiche=1 WHERE bf_id_fiche="' . $this->dbService->escape($entryId) . '"');
            } else {
                $this->dbService->query('UPDATE' . $this->dbService->prefixTable('fiche') . 'SET bf_statut_fiche=2 WHERE bf_id_fiche="' . $this->dbService->escape($entryId) . '"');
            }
            //TODO envoie mail annonceur
        }
    }

    /**
     * Delete a fiche.
     *
     * @param $tag
     *
     * @throws Exception
     */
    public function delete($tag, bool $forceEvenIfNotOwner = false)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$forceEvenIfNotOwner && !$this->wiki->UserIsAdmin() && !$this->wiki->UserIsOwner($tag)) {
            throw new Exception(_t('DELETEPAGE_NOT_DELETED') . _t('DELETEPAGE_NOT_OWNER'));
        }

        $fiche = $this->getOne($tag, false, null, true, $forceEvenIfNotOwner);
        if (empty($fiche)) {
            throw new Exception("Not existing entry : $tag");
        }

        $this->pageManager->deleteOrphaned($tag);
        $this->tripleStore->delete($tag, TripleStore::TYPE_URI, null, '', '');
        $this->tripleStore->delete($tag, TripleStore::SOURCE_URL_URI, null, '', '');
        $this->wiki->LogAdministrativeAction(
            $this->authController->getLoggedUserName(),
            'Suppression de la page ->""' . $tag . '""'
        );

        unset($this->cachedEntriestags[$tag]);
    }

    /*
     * Convert body to JSON object
     */
    public function decode($body)
    {
        $data = json_decode($body, true);
        if (is_iterable($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = _convert($value, 'UTF-8');
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
     * @return array with extra calculated fields like id_fiche, and time, and handled fields with acls
     *
     * @throws Exception
     */
    public function formatDataBeforeSave($data): array
    {
        // Let's set the value of id_typeannonce

        $data['id_typeannonce'] = isset($data['id_typeannonce']) ? $data['id_typeannonce'] : $_REQUEST['id_typeannonce'];

        // not possible to init the formManager in the constructor because of circular reference problem
        $form = $this->wiki->services->get(FormManager::class)->getOne($data['id_typeannonce']);
        if (empty($form)) {
            throw new Exception('No form with id: ' . $data['id_typeannonce']);
        }

        // We first need to ensure default values for uneditable fields are set
        // so we can use it later to build the automatic title if necessary

        foreach ($form['prepared'] as $bazarField) {
            if ($bazarField instanceof BazarField &&
                !($bazarField instanceof TitleField) &&
                !($bazarField->requireIDFiche ()) // Some fields like ImageField and File Field need the id_fiche to be defined before to call formatValuesBeforeSave. So we will handle them later.
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

        // We can now build the field title if there is one

        if (is_array($form['prepared'])) {
            foreach ($form['prepared'] as $field) {
                if ($field instanceof TitleField) {
                    $data = array_merge($data, $field->formatValuesBeforeSave($data));
                }
            }
        }

        // Let's generate fiche id if necessary

        if (!isset($data['id_fiche'])) {
            // Generate the ID from the title
            if (empty($data['id_fiche'] = genere_nom_wiki($data['bf_titre']))) {
                throw new Exception('$data[\'id_fiche\'] can not be generated from $data[\'bf_titre\'] !');
            }
            // TODO see if we can remove this
            //$_POST['id_fiche'] = $data['id_fiche'];
        } elseif (empty($data['id_fiche'])) {
            throw new Exception('$data[\'id_fiche\'] is set but with empty value !');
        }

        // We can now handle fields like ImageField and File Field that require id_fiche in order to format their values 

        foreach ($form['prepared'] as $bazarField) {
            if ($bazarField->requireIDFiche()) {
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
        $result = $this->dbService->loadSingle('SELECT MIN(time) as firsttime FROM ' . $this->dbService->prefixTable('pages') . "WHERE tag='" . $data['id_fiche'] . "'");
        $data['date_creation_fiche'] = $data['date_creation_fiche'] ?? $result['firsttime'] ?? date('Y-m-d H:i:s', time());

        // Entry status
        if ($this->wiki->UserIsAdmin()) {
            $data['statut_fiche'] = '1';
        } else {
            $data['statut_fiche'] = $this->params->get('BAZ_ETAT_VALIDATION');
        }

        // Let's ensure $data['id_typeannonce'] is not empty
        if (empty($data['id_typeannonce'])) {
            throw new Exception('$data[\'id_typeannonce\'] is empty !');
        }

        // Let's ensure $data['id_fiche'] is not empty
        if (empty($data['id_fiche'])) {
            throw new Exception('$data[\'id_fiche\'] is empty !');
        }

        $data['date_maj_fiche'] = $data['date_maj_fiche'] ?? date('Y-m-d H:i:s', time());

        // on enleve les champs hidden pas necessaires a la fiche
        unset($data['valider']);
        unset($data['MAX_FILE_SIZE']);
        unset($data['antispam']);
        unset($data['mot_de_passe_wikini']);
        unset($data['mot_de_passe_repete_wikini']);
        unset($data['html_data']);
        unset($data['url']);
        unset($data['incomingurl']);

        // on nettoie le champ owner qui n'est pas sauvegardé (champ owner de la page)
        if (isset($data['owner'])) {
            unset($data['owner']);
        }

        // on encode en utf-8 pour reussir a encoder en json
        if (YW_CHARSET != 'UTF-8') {
            $data = array_map(function ($value) {
                return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
            }, $data);
        }

		$data = $this->removeUnknownFields ($data['id_typeannonce'], $data);

        return $data;
    }

    /**
     * Append data needed for display
     * TODO move this to a class dedicated to display.
     *
     * @param $fiche
     * @param bool   $semantic
     * @param string $correspondance
     * @param array  $page           , appendDisplayData is called in environement with access to $page
     *                               helping to get owner without asking a new Time to Page manager to get it
     *
     * @throws Exception
     */
    public function appendDisplayData(&$fiche, $semantic, $correspondance, array $page)
    {
        // user
        $fiche['user'] = $page['user'] ?? null;
        // owner
        $fiche['owner'] = $page['owner'] ?? null;

        // champs correspondants
        if (!empty($correspondance)) {
            try {
                $tabcorrespondances = $this->getMultipleParameters($correspondance, ',', '=');
                foreach ($tabcorrespondances as $key => $data) {
                    if (isset($key)) {
                        // not possible to init the Guard in the constructor because of circular reference problem
                        $fiche[$key] = $this->wiki->services->get(Guard::class)->isFieldDataAuthorizedForCorrespondance($page, $fiche, $data);
                    } else {
                        echo '<div class="alert alert-danger">' . _t('BAZ_CORRESPONDANCE_ERROR') . '</div>';
                    }
                }
            } catch (ParsingMultipleException $th) {
                echo '<div class="alert alert-danger">' . str_replace("\n", '<br/>', _t('BAZ_CORRESPONDANCE_ERROR2')) . '</div>';
            }
        }

        // HTML data
        $fiche['html_data'] = getHtmlDataAttributes($fiche);

        // Fiche URL
        if (!isset($fiche['url'])) {
            // could already be defined for entries from external json
            $fiche['url'] = $this->wiki->Href('', $fiche['id_fiche']);
        }

        // Données sémantiques
        if ($semantic) {
            // not possible to init the formManager in the constructor because of circular reference problem
            $form = $this->wiki->services->get(FormManager::class)->getOne($fiche['id_typeannonce']);
            $fiche['semantic'] = $this->semanticTransformer->convertToSemanticData($form, $fiche);
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
        } else {
            $params = explode($firstseparator, $param);
            $params = array_map('trim', $params);
            if (count($params) == 0) {
                throw new ParsingMultipleException('There is no parameter to parse !');
            } else {
                foreach ($params as $value) {
                    if (empty($value)) {
                        throw new ParsingMultipleException('One parameter should not be empty !');
                    } else {
                        $tab = explode($secondseparator, $value);
                        $tab = array_map('trim', $tab);
                        if (count($tab) > 1) {
                            $tabparam[$tab[0]] = $tab[1];
                        } else {
                            throw new ParsingMultipleException("One parameter does not contain '$secondseparator'!");
                        }
                    }
                }
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
     * @param mixed $formsIds
     *
     * @return array $forms
     */
    private function getFormsFromIds($formsIds): array
    {
        $formManager = $this->wiki->services->get(FormManager::class); // not load in contruct to prevent circular loading
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
        } else {
            return $formManager->getAll();
        }
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
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$this->wiki->UserIsAdmin()) {
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
        $requete = $this->prepareSearchRequest($params, false, $applyOnAllRevisions);

        $pages = $this->dbService->loadAll($requete);

        if (empty($pages)) {
            return [];
        }

        $entriesIds = [];
        foreach ($pages as $page) {
            $entry = $this->decode($page['body']);

            foreach ($attributesNames as $attributeName) {
                if ($mode === 'rename') {
                    foreach ($attributeName as $oldName => $newName) {
                        if (isset($entry[$oldName])) {
                            $entry[$newName] = $entry[$oldName];
                            unset($entry[$oldName]);
                            if (!empty($entry['id_fiche']) && !in_array($entry['id_fiche'], $entriesIds)) {
                                $entriesIds[] = $entry['id_fiche'];
                            }
                        }
                    }
                } else {
                    if (isset($entry[$attributeName])) {
                        unset($entry[$attributeName]);
                        if (!empty($entry['id_fiche']) && !in_array($entry['id_fiche'], $entriesIds)) {
                            $entriesIds[] = $entry['id_fiche'];
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
            $body = json_encode($entry);
            if ($applyOnAllRevisions) {
                $this->dbService->query('UPDATE' . $this->dbService->prefixTable('pages') . "SET body = '" . $this->dbService->escape(chop($body)) . "'" .
                    " WHERE id = '" . $this->dbService->escape($page['id']) . "';");
            } else {
                $this->pageManager->save($entry['id_fiche'], $body);
            }
        }

        return $entriesIds;
    }

    private function duplicate($sourceTag, $destinationTag): bool
    {
        $result = false;
        $this->wiki->LogAdministrativeAction($this->authController->getLoggedUserName(), 'Duplication de la fiche ""' . $sourceTag . '"" vers la fiche ""' . $destinationTag . '""');

        return $result;
    }
}
