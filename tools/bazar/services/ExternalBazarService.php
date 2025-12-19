<?php

namespace YesWiki\Bazar\Service;

use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Exception\ExternalBazarServiceException;
use YesWiki\Bazar\Field\ExternalImageField;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Bazar\Service\BazarListService;
use YesWiki\Core\Service\ImportService;
use YesWiki\Wiki;

class ExternalBazarService
{
    public const FIELD_JSON_FORM_ADDR = 3; // replace FIELD_SIZE = 3;
    public const FIELD_ORIGINAL_TYPE = 4; // FIELD_MAX_CHARS = 4;

    private const MAX_CACHE_TIME = 864000; // 10 days ot to keep external data in local
    private const JSON_FORM_BASE_URL = '{pageTag}/json{firstSeparator}demand=forms&id={formId}';
    private const JSON_ENTRIES_OLD_BASE_URL = '{pageTag}/json{firstSeparator}demand=entries&id={formId}';
    private const CACHE_FILENAME_PREFIX = 'ExternalBazarServiceCache_';
    private const CACHE_FILENAME_DETAILS_PREFIX = 'Details_';
    private const CONVERT_FIELD_NAMES = [
        'checkbox' => 'externalcheckboxlistfield',
        'checkboxlistfield' => 'externalcheckboxlistfield',
        'checkboxfiche' => 'externalcheckboxentryfield',
        'checkboxentryfield' => 'externalcheckboxentryfield',
        'fichier' => 'externalfilefield',
        'filefield' => 'externalfilefield',
        'radio' => 'externalradiolistfield',
        'radiolistfield' => 'externalradiolistfield',
        'radiofiche' => 'externalradioentryfield',
        'radioentryfield' => 'externalradioentryfield',
        'liste' => 'externalselectlistfield',
        'selectlistfield' => 'externalselectlistfield',
        'listefiche' => 'externalselectentryfield',
        'selectentryfield' => 'externalselectentryfield',
        'listefiches' => 'externallinkedentryfield',
        'listefichesliees' => 'externallinkedentryfield',
        'linkedentryfield' => 'externallinkedentryfield',
        'tagsfield' => 'externaltagsfield',
        'tags' => 'externaltagsfield',
    ];
    private const CONVERT_FIELD_NAMES_FOR_IMAGES = [
        'image' => 'externalimagefield',
        'imagefield' => 'externalimagefield',
    ];

    private const UPDATING_SUFFIX = '_updating';

    protected $debug;
    protected $timeCacheToCheckChanges;
    protected $timeCacheToRefreshForms;
    protected $timeCacheToCheckDeletion;
    protected $timeDebug;
    protected $formManager;
    protected $entryManager;
    protected $importService;
    protected $params;
    protected $wiki;

    private $urlCache;
    private $alreadyRefreshedURLs;
    private $alreadyCheckingDeletionsURLs;

    public function __construct(
        Wiki $wiki,
        ParameterBagInterface $params,
        FormManager $formManager,
        EntryManager $entryManager,
        ImportService $importService
    ) {
        $this->wiki = $wiki;
        $this->params = $params;
        $this->formManager = $formManager;
        $this->importService = $importService;
        $this->entryManager = $entryManager;
        $this->debug = ($this->params->has('debug') && $this->params->get('debug') == 'yes');
        $externalBazarServiceParameters = $this->params->get('baz_external_service');
        $this->timeCacheToCheckChanges = (int)($externalBazarServiceParameters['cache_time_to_check_changes'] ?? 90); // seconds
        $this->timeCacheToCheckDeletion = (int)($externalBazarServiceParameters['cache_time_to_check_deletion'] ?? 86400); // seconds
        $this->timeCacheToRefreshForms = (int)($externalBazarServiceParameters['cache_time_to_refresh_forms'] ?? 7200); // seconds
        $this->timeDebug = (bool)($externalBazarServiceParameters['time_debug'] ?? false);

        $this->urlCache = null;
        $this->alreadyRefreshedURLs = [];
        $this->alreadyCheckingDeletionsURLs = [];
    }

	private function getRefreshValue ($pRefresh = false)
	{
		// to prevent DDOS attack refresh only for admins
	
		if ($pRefresh == null || !$pRefresh || !$this->wiki->UserIsAdmin())
			return false;
		else 
			return true;		
	}

    /**
     * get a form from external wiki.
     */
    public function getForm (string $pURL, int $pFormId, $pRefresh = false, bool $pCheckUrl = true): ?array
    {	   
        $vRefresh = $this->getRefreshValue ($pRefresh);
       
        if ($pCheckUrl) {
            $pURL = $this->formatUrl($pURL);
        }
               
        $vURLDetails = $this->getURLDetails($pURL, $vRefresh ? 0 : $this->timeCacheToRefreshForms);

        if (empty($vURLDetails)) {
            if ($this->debug) {
                trigger_error(get_class($this) . '::getForm: ' . _t('BAZ_EXTERNAL_SERVICE_BAD_URL'));
            }

            return null;
        }

        $vJSON = $this->getJSONCachedUrlContent(
            $this->getFormUrl($vURLDetails, $pFormId),
            $this->timeCacheToRefreshForms,
            $vRefresh
        );

        $vForms = json_decode($vJSON, true);        

        if ($vForms) {
	        $vForm = $vForms[0];
	        $vForm ["_isExternal_"] = true;
            return $vForm;
        } elseif ($this->debug) {
            trigger_error(get_class($this) . '::getForm: ' . _t('BAZ_EXTERNAL_SERVICE_BAD_RECEIVED_FORM'));
        }

        return null;
    }
	
	// getFormsForBazarListe : DEPRECATED : use getExternalForms instead

	public function getFormsForBazarListe(array $pExternalIDs, bool $pRefresh = false): ?array
	{
		return getExternalForms ($pExternalIDs, $pRefresh); 
	}
	
    /**
     * get forms from external wiki.
     *
     * @param array $pExternalIDs // format 'url' => url, 'id' => *id, 'localFormId' => $id
     *
     * @return array forms
     */
     
    public function getExternalForms (array $pExternalIDs, $pRefresh = false): ?array
    {    	
        if ($this->debug && $this->timeDebug) {
            $diffTime = -hrtime(true);
        }
        
        $this->cleanOldCacheFiles();
        
        if ($this->debug && $this->timeDebug) {
            $diffTime += hrtime(true);
            trigger_error('Cleaning old cache files :' . $diffTime / 1E+6 . ' ms');
        }

        if ($this->debug && $this->timeDebug) {
            $diffTime = -hrtime(true);
        }
        
        if (!$this->checkExternalIDsFormat($pExternalIDs)) {
	        throw new ExternalBazarServiceException("Wrong format for external IDs");
            return [];
        }
       
        $vForms = [];
       
		$vGroupedExternalIDs = $this->groupIDsByURL ($pExternalIDs);

        foreach ($vGroupedExternalIDs as $vURL => $vIDs) {
			foreach ($vIDs as $vIDValues) {                		
                $vLocalIDCorrespondToEmptyForm = false;
                
                $vLocalFormId = $vIDValues['localFormId'];

                if ($vLocalFormId != "") {                                    
                    if ($vForm = $this->formManager->getOne($vLocalFormId)) {
	                    $vForm['_isExternal_'] = true;
                        $vForm['external_bn_id_nature'] = $vIDValues['id'];
                        $vForm['external_url'] = $vURL;
                        $vForms[] = $vForm;                        
                    } else {
                        $vLocalIDCorrespondToEmptyForm = true;
                    }
                }

                if ($vLocalFormId == "" || $vLocalIDCorrespondToEmptyForm) { 
                    if ($vForm = $this->getForm($vURL, $vIDValues['id'], $pRefresh, false)) {                        
                        $vLocalFormId = $vLocalIDCorrespondToEmptyForm ? $vLocalFormId : $this->findNewId();

                        $vForm = $this->prepareExtForm($vLocalFormId, $vURL, $vForm);
                        
        				if (!empty($vLocalFormId) && empty($this->formManager->getOne($vLocalFormId)) && !empty($vForm)) {        				
							$this->formManager->cacheForm ($vLocalFormId, $vForm);
							$vForms[] = $vForm;
						}
					}					
                }
            }
        }
        
        if ($this->debug && $this->timeDebug) {
            $diffTime += hrtime(true);
            trigger_error('Getting forms :' . $diffTime / 1E+6 . ' ms');
        }

        return $vForms;
    }

    /**
     * get Entries linked to forms.
     *
     * @param array $params
     *
     * @return array|null $entries
     */
    public function getEntries($pParams): array
    {    	    	
        if ($this->debug && $this->timeDebug) {
            $diffTime = -hrtime(true);
        }
    
	    $vSearchManager = $this->wiki->services->get(SearchManager::class);
	    $vBazarListService = $this->wiki->services->get(BazarListService::class);
    
        // Merge les paramètres passé avec des paramètres par défaut
        $pParams = array_merge(
            [
                'refresh' => false, // parameter to force refresh cache
            ],
            $pParams
        );

		$vIDs = $vBazarListService->getIDs ($pParams ["idtypeannonce"] ?? $pParams["id"] ?? '');

	    $vLocalIDs = $vIDs ["locals"];
    	$vExternalIDs = $vIDs ["externals"];

    	if (empty($pParams['forms']))
	   	{
    		$vForms = $vBazarListService->getForms ($vIDs, $pParams['refresh']??false);
    	}
		else
		{
	    	$vForms = $pParams['forms'];
	    }
    
        $vEntries = [];

		if (count ($vLocalIDs) > 0 || count ($vExternalIDs) == 0)
		{
			$vLocalEntries = array_values($vSearchManager->search(
                array_merge ($pParams, [ 
                	'formsIds' => $vLocalIDs
                ]),
                true, // filter on read ACL
                true  // use Guard
            ));
		
			array_push($vEntries, ...$vLocalEntries);
		}

		unset ($pParams ["id_typeannonce"]);
		unset ($pParams ["id"]);

		$vURLSearchParams = $vSearchManager->paramsToURLSearchParams ($pParams);

		foreach ($vExternalIDs as $vExternalID)
		{	
			$vForm = array_filter ($vForms, function ($vForm) use ($vExternalID) {		
				return	isset ($vForm["_isExternal_"]) && 
						$vForm["_isExternal_"] &&
						(strcmp ($vForm["external_url"], $this->formatUrl($vExternalID ["url"])) === 0) &&
						($vForm["external_bn_id_nature"] === $vExternalID ["id"]);
			});
			
			$vForm = array_values ($vForm)[0];

			$vURL = $vForm['external_url'];
			$vLocalFormId = $vForm['bn_id_nature'];            
			$vDistantFormId = $vForm['external_bn_id_nature'];

			$vURLDetails = $this->getURLDetails($vURL, $this->timeCacheToCheckChanges);

			if (empty($vURLDetails)) {
				if ($this->debug) {
					trigger_error(get_class($this) . '::getEntries: ' . _t('BAZ_EXTERNAL_SERVICE_BAD_URL'));
				}
			} else {                
				// Formattage des paramètres d'URL

				$json = $this->getJSONCachedUrlContent(
					$this->getEntriesViaApiUrl($vURLDetails, $vDistantFormId, $vURLSearchParams),
					$this->timeCacheToCheckChanges,
					$pParams ['refresh'],
					'entries'
				);

				$vBatchEntries = json_decode($json, true);
				
				if (empty($vBatchEntries)) {        
					// check if old route is working
					$json = $this->getJSONCachedUrlContent(
						$this->getEntriesViaJsonHandlerUrl($vURLDetails, $vDistantFormId, $vURLSearchParams),
						$this->timeCacheToCheckChanges,
						$pParams ['refresh']
					);
					$vBatchEntries = json_decode($json, true);
					if (is_array($vBatchEntries)) {
						$vBatchEntries = array_map(function ($vEntry) {
						    return ['html_data' => ''] + $vEntry;
						}, $vBatchEntries);
					}
				}

				if (is_array($vBatchEntries)) {
					// replace formId
					foreach ($vBatchEntries as $vEntry) {
					
						if (is_string ($vEntry))
						{
							throw new ExternalBazarServiceException("Entry should not be a string : " . $vEntry);
						}
						else
						{
						    $vEntry['-is-external-'] = "1"; 
						    // save external data with key 'external-data' because '-' is not used for name
						    $vEntry['external-data'] = [
						        'baseUrl' => $vURL,
  						        'externalFormID' => $vDistantFormId,
						        'localFormID' => $vLocalFormId
						    ];
						    $vEntry['url'] = $vURL . '?' . $vEntry['id_fiche'];
						    $vEntry['id_typeannonce'] = $vLocalFormId;
						    
						    $vEntries[] = $vEntry;
						}
					}
				}
			}
        }

        if ($this->debug && $this->timeDebug) {
            $diffTime += hrtime(true);
            trigger_error('Getting entries total time :' . $diffTime / 1E+6 . ' ms');
        }

        if (!empty($vEntries)) {
            return $vEntries;
        } elseif ($this->debug) {
            trigger_error(get_class($this) . '::getEntries: ' . _t('BAZ_EXTERNAL_SERVICE_BAD_RECEIVED_ENTRIES'));

            return [];
        }

        return [];
    }

    public function formatUrl($url)
    {
        $urlDetails = $this->getURLDetails($url);
        
        $newUrl = empty($urlDetails) ? $url : $urlDetails[0];
        
        // add / at end if needed
        
        if (substr($newUrl, -1) !== '/') {
            $newUrl = $newUrl . '/';
        }

        return $newUrl;
    }

    /**
     * Get content from url from cache.
     *
     * @param string $url        : url to get with  cache
     * @param int    $cache_life : duration of the cache in second
     * @param string $mode       'standard' or 'entries'
     *
     * @return string file content from cache
     */
    private function getCachedUrlContent(
        string $url,
        bool $testFileModificationDate,
        int $cache_life = 90,
        bool $forceRefresh = false,
        string $mode = 'standard'
    ) {
        $cache_life = min($cache_life, self::MAX_CACHE_TIME);
        $cache_file = ($mode === 'entries')
            ? $this->cacheUrlForEntries($url, $testFileModificationDate, $cache_life, $forceRefresh)
            : $this->cacheUrl($url, $testFileModificationDate, $cache_life, $forceRefresh);

        return file_get_contents($cache_file);
    }

    /**
     * Get content from url from cache in JSON removing notice message.
     *
     * @param string $url        : url to get with  cache
     * @param int    $cache_life : duration of the cache in second
     * @param string $mode       'standard' or 'entries'
     *
     * @return string file content from cache
     */
    public function getJSONCachedUrlContent(string $url, int $cache_life = 90, bool $pForceRefresh = false, $mode = 'standard')
    {	   
        if (in_array($url, $this->alreadyRefreshedURLs)) {
            $testFileModificationDate = false;
            $pForceRefresh = false; // to prevent too many refreshes
        } else {
            $this->alreadyRefreshedURLs[] = $url;
            $testFileModificationDate = true;
        }
        $json = $this->getCachedUrlContent($url, $testFileModificationDate, $cache_life, $pForceRefresh, $mode);
        $json = $this->extractErrors($json, $url);
        
        return $json;
    }

    /**
     * put in cache result of url.
     *
     * @param string $url        : url to get with  cache
     * @param int    $cache_life : duration of the cache in second
     * @param string $dir        : base dirname where save the cache
     *
     * @return string location of cached file
     */
    public function cacheUrl(
        string $url,
        bool $testFileModificationDate,
        int $cache_life = 90,
        bool $pForceRefresh = false,
        string $dir = 'cache'
    ) {
        if ($this->debug && $this->timeDebug) {
            $diffTime = -hrtime(true);
        }
        
        // to prevent DDOS attack refresh only for admins
        
        $vRefresh = $this->getRefreshValue ($pForceRefresh);
        
        $cache_file = $dir . '/' . self::CACHE_FILENAME_PREFIX . $this->sanitizeFileName($url);

        $filemtime = @filemtime($cache_file); // returns FALSE if file does not exist
        if ($vRefresh || !$filemtime || ($testFileModificationDate && (time() - $filemtime >= $cache_life))) {
            $this->secureFilePutContents($url, '', $cache_file, $vRefresh);
            if ($this->debug && $this->timeDebug) {
                $diffTime += hrtime(true);
                trigger_error('Caching file :' . $diffTime / 1E+6 . ' ms ; url : ' . $url);
            }
        }

        return $cache_file;
    }

    /**
     * refrech cache with only most recent entries.
     *
     * @param string $url        : url to get with  cache
     * @param int    $cache_life : duration of the cache in second
     * @param string $dir        : base dirname where save the cache
     *
     * @return string location of cached file
     */
    private function cacheUrlForEntries(
        string $url,
        bool $testFileModificationDate,
        int $cache_life = 90,
        bool $pForceRefresh = false,
        string $dir = 'cache'
    ) {
    
	    // to prevent DDOS attack refresh only for admins
        
        $vRefresh = $this->getRefreshValue ($pForceRefresh);
        
        if ($this->debug && $this->timeDebug) {
            $diffTime = -hrtime(true);
        }
        $url = $this->sanitizeUrlForEntries($url);
        $cache_file = $dir . '/' . self::CACHE_FILENAME_PREFIX . $this->sanitizeFileName($url);

        if (!file_exists($cache_file) || $vRefresh) {
            $this->secureFilePutContents($url, '', $cache_file, $vRefresh);
            if ($this->debug && $this->timeDebug) {
                $diffTime += hrtime(true);
                trigger_error('Caching entries :' . $diffTime / 1E+6 . ' ms ; url : ' . $url);
            }
        } elseif ($testFileModificationDate) {
            $filemtime = @filemtime($cache_file);  // returns FALSE if file does not exist
            if (time() - $filemtime >= $this->timeCacheToCheckDeletion) {
                $this->checkForDeletion($url, $cache_file);
                $this->checkOnlyEntriesChanges($url, $cache_file, $vRefresh);
                if ($this->debug && $this->timeDebug) {
                    $diffTime += hrtime(true);
                    trigger_error('Caching entries with deletion :' . $diffTime / 1E+6 . ' ms ; url : ' . $url);
                }
            } elseif (time() - $filemtime >= $cache_life) {
                // only check for changes
                $this->checkOnlyEntriesChanges($url, $cache_file, $vRefresh);
                if ($this->debug && $this->timeDebug) {
                    $diffTime += hrtime(true);
                    trigger_error('Caching entries :' . $diffTime / 1E+6 . ' ms ; url : ' . $url);
                }
            }
        }

        return $cache_file;
    }

    /**
     * check existence of &fields=date_maj_fiche in url for entries refresh.
     *
     * @param bool $addFields add fields=id_fiche,bf_titre,url
     *
     * @return string $url
     */
    private function sanitizeUrlForEntries(string $url, bool $addFields = false): string
    {
        // sanitize url
        $query = parse_url($url, PHP_URL_QUERY);
        if (!empty($query)) {
            parse_str($query, $queries);
            foreach ($queries as $key => $value) {
                if ($key === 'fields') {
                    $fields = empty($value) ? [] : (
                        !is_array($value)
                        ? (
                            is_scalar($value)
                            ? explode(',', $value)
                            : []
                        )
                        : $value
                    );
                    foreach (($addFields ? ['id_fiche', 'bf_titre', 'url', 'date_maj_fiche'] : ['date_maj_fiche']) as $fieldName) {
                        if (!in_array($fieldName, $fields)) {
                            $fields[] = $fieldName;
                        }
                    }
                    if (empty($fields)) {
                        unset($queries[$key]);
                    } else {
                        $queries[$key] = implode(',', $fields);
                    }
                }
            }
            if ($addFields && empty($fields)) {
                $queries['fields'] = 'id_fiche,bf_titre,url,date_maj_fiche';
            }
            array_walk($queries, function (&$item, $key) {
                $item = empty($item)
                    ? $key
                    : (
                        is_array($item)
                        ? $key . '=' . implode(',', $item)
                        : $key . '=' . $item
                    );
            });
            $newQuery = implode('&', $queries);
            $url = str_replace($query, $newQuery, $url);
        }

        return $url;
    }

    /**
     * sanitize file name.
     *
     * @return string $outputString
     */
    private function sanitizeFileName(string $inputString): string
    {
        return hash('sha256', $inputString);
    }

    /**
     * only check changes on external data and update cache file.
     */
    private function checkOnlyEntriesChanges(string $url, string $cache_file, bool $forceRefresh)
    {
        $lastModificationDate = $this->getLastModificationDateFromFile($cache_file);
        if (empty($lastModificationDate)) {
            if ($this->debug) {
                trigger_error($cache_file . " should contain 'date_maj_fiche' !", E_USER_WARNING);
            }
            $this->secureFilePutContents($url, '', $cache_file, $forceRefresh);
        } else {
            list($lastModificationDate, $entries) = $lastModificationDate;
            $newEntries = $this->getNewEntries($url, $lastModificationDate);
            if (!empty($newEntries) && is_array($newEntries)) {
                foreach ($newEntries as $key => $entry) {
                    $entries[$entry['id_fiche'] ?? $key] = $entry;
                }
                $this->secureFilePutContents('', json_encode($entries), $cache_file, $forceRefresh);
            }
        }
    }

    /**
     * check for deletions.
     */
    public function checkForDeletion(string $url, string $cache_file)
    {
        if ($this->debug && $this->timeDebug) {
            $diffTime = -hrtime(true);
        }

        $urlToCheckDeletion = $this->sanitizeUrlForEntries($url, true);
        if (in_array($urlToCheckDeletion, $this->alreadyCheckingDeletionsURLs)) {
            return null;
        } else {
            $this->alreadyCheckingDeletionsURLs[] = $urlToCheckDeletion;
        }
        $json = file_get_contents($cache_file);
        $json = $this->extractErrors($json, $cache_file);

        $entries = json_decode($json, true);
        if (empty($entries) || !is_array($entries)) {
            $this->secureFilePutContents($url, '', $cache_file, false);
            if ($this->debug && $this->timeDebug) {
                $diffTime += hrtime(true);
                trigger_error('checking deletions (refreshing) :' . $diffTime / 1E+6 . ' ms ; url : ' . $url);
            }
        } else {
            try {
                $entriesList = json_decode($this->extractErrors($this->securedFileGetContentFromUrl($urlToCheckDeletion), $urlToCheckDeletion), true);
                if ($this->debug && $this->timeDebug) {
                    $diffTime += hrtime(true);
                    trigger_error('checking deletions (only list) :' . $diffTime / 1E+6 . ' ms ; url : ' . $urlToCheckDeletion);
                    $diffTime = -hrtime(true);
                }
                foreach ($entries as $key => $entry) {
                    if (isset($entriesList) && isset($entry['id_fiche']) && !isset($entriesList[$entry['id_fiche']])) {
                        if ($this->debug && $this->wiki->UserIsAdmin()) {
                            trigger_error('Deleting ' . $entry['id_fiche'] . ' from ' . $cache_file);
                        }
                        unset($entries[$key]);
                    }
                }
                $this->secureFilePutContents('', json_encode($entries), $cache_file, false);
                if ($this->debug && $this->timeDebug) {
                    $diffTime += hrtime(true);
                    trigger_error('Updating deletions :' . $diffTime / 1E+6 . ' ms ; url : ' . $url);
                }
            } catch (ExternalBazarServiceException $th) {
            }
        }
    }

    /**
     * get last modification date from file.
     *
     * @return array|null [$lastModificationDate,$entries]
     */
    private function getLastModificationDateFromFile(string $cache_file): ?array
    {
        $json = file_get_contents($cache_file);
        $json = $this->extractErrors($json, $cache_file);
        $entries = json_decode($json, true);

        if (!empty($entries) && is_array($entries)) {
            $maxUpdatedDate = null;
            foreach ($entries as $entry) {
                if (
                    !empty($entry['date_maj_fiche'])
                    && (
                        is_null($maxUpdatedDate) ||
                        ($entry['date_maj_fiche'] > $maxUpdatedDate)
                    )
                ) {
                    $maxUpdatedDate = $entry['date_maj_fiche'];
                }
            }
            if (!empty($maxUpdatedDate)) {
                return [(new \DateTime($maxUpdatedDate))->add(new \DateInterval('PT1S'))->format('Y-m-d H:i:s'), $entries];
            }
        }

        return null;
    }

    /**
     * get only new entries.
     *
     * @return array|null $entries
     */
    private function getNewEntries(string $url, string $dateMin): ?array
    {
        if ($this->debug && $this->timeDebug) {
            $diffTime = -hrtime(true);
        }
        $sanitizedUrl = $url . (strpos($url, '?') === false ? '?' : '&') . 'dateMin=' . urlencode($dateMin);
        try {
            $newEntries = json_decode($this->extractErrors($this->securedFileGetContentFromUrl($sanitizedUrl), $sanitizedUrl), true);
            if ($this->debug && $this->timeDebug) {
                $diffTime += hrtime(true);
                trigger_error('Getting new entries :' . $diffTime / 1E+6 . ' ms ; url : ' . $url . ' ; sanitizedUrl : ' . $sanitizedUrl);
            }

            return (empty($newEntries) || !is_array($newEntries)) ? null : $newEntries;
        } catch (ExternalBazarServiceException $th) {
            return null;
        }
    }

    /**
     * check format of externalIds.
     */
    private function checkExternalIDsFormat(array $pExternalIDs): bool
    {
        return empty(array_filter($pExternalIDs, function ($pExternalId) {
            return !isset($pExternalId['url']) || !isset($pExternalId['id']) || !isset($pExternalId['localFormId']);
        }));
    }

    /**
     * groups ids by url.
     */
    private function groupIDsByURL (array $pExternalIDs): array
    {
        // group ids by url
        
        $vGroupedExternalIDs = [];
        
        foreach ($pExternalIDs as $vExternalId) {                     	
            if (!empty($vExternalId['url'])) {           
                $vURL = $this->formatUrl($vExternalId['url']);
                $vURL = empty($vURL) ? $vExternalId['url'] : $vURL;
                
                $vGroupedExternalIDs[$vURL][] = [
                	'id' => $vExternalId['id'],
	                'localFormId' => $vExternalId['localFormId'],
            	];
            }
            else {
				throw new ExternalBazarServiceException("URL should not be empty");
            }                        
        }

        return $vGroupedExternalIDs;
    }

    /**
     * get newFormId using FormManager
     */
    private function findNewId(): int
    {
    	return $this->formManager->findNewId();    
    }

    /**
     * prepare external form.
     *
     * @return array $form
     */
    private function prepareExtForm(int $localFormId, string $url, array $form): array
    {   
        // update FormId
        $form['_isExternal_'] = true;
        $form['external_bn_id_nature'] = $form['bn_id_nature'];
        $form['external_url'] = $url;
        $urlDetails = $this->getURLDetails($url, 999999); // no reset of cache because just done before
        $form['bn_id_nature'] = $localFormId;

        // change fields type before prepareData
        foreach ($form['template'] as $index => $fieldTemplate) {
            if (isset(self::CONVERT_FIELD_NAMES[$fieldTemplate[0]])) {
                $form['template'][$index][self::FIELD_ORIGINAL_TYPE] = $fieldTemplate[0];
                $form['template'][$index][0] = self::CONVERT_FIELD_NAMES[$fieldTemplate[0]];
                $form['template'][$index][self::FIELD_JSON_FORM_ADDR] = $this->getFormUrl($urlDetails, $form['external_bn_id_nature']);
            } elseif (isset(self::CONVERT_FIELD_NAMES_FOR_IMAGES[$fieldTemplate[0]])) {
                $form['template'][$index][0] = self::CONVERT_FIELD_NAMES_FOR_IMAGES[$fieldTemplate[0]];
                $form['template'][$index][ExternalImageField::FIELD_JSON_FORM_ADDR] = $this->getFormUrl($urlDetails, $form['external_bn_id_nature']);
            }
            // add missing indexes
            if (count($form['template'][$index]) < 15) {
                for ($i = count($form['template'][$index]); $i < 16; $i++) {
                    $form['template'][$index][$i] = '';
                }
            }
        }

        // parse external fields

        $form['prepared'] = $this->formManager->prepareData($form);

        return $form;
    }

    /**
     * clean old cache files to prevent leak of data between sites.
     */
    private function cleanOldCacheFiles()
    {
        $cacheFiles = glob('cache/' . self::CACHE_FILENAME_PREFIX . '*');
        
        foreach ($cacheFiles as $filePath) {
            $filemtime = @filemtime($filePath);  // returns FALSE if file does not exist
            if (!$filemtime or (time() - $filemtime >= self::MAX_CACHE_TIME)) {
                unlink($filePath);
            }
        }
    }

    /**
     * get rewrite mode, base url for this external url.
     *
     * @param int    $cache_life : duration of the cache in second
     * @param string $dir        : base dirname where save the cache
     *
     * @return array [$baseUrl,$rootPage,$rewriteModeEnabled]
     */
    private function getURLDetails(string $url, int $cache_life = 120, string $dir = 'cache'): array
    {   
        if (!isset($this->urlCache[$url])) {
            $cache_file = $dir . '/' . self::CACHE_FILENAME_PREFIX . self::CACHE_FILENAME_DETAILS_PREFIX . $this->sanitizeFileName($url);
            $filemtime = @filemtime($cache_file);  // returns FALSE if file does not exist

            if (!$filemtime or (time() - $filemtime >= $cache_life)) {
                $details = $this->importService->extractBaseUrlAndRootPage($url);
                file_put_contents($cache_file, json_encode($details));
            } else {
                $details = json_decode($this->extractErrors(file_get_contents($cache_file), $cache_file), true);
            }

            $this->urlCache[$url] = $details;
        }

        return $this->urlCache[$url];
    }

    /**
     * secure saving content in file
     * create a temp file to indicate to other php session that the file is updating.
     *
     * @param string $content used if url if empty
     */
    private function secureFilePutContents(string $url, string $content, string $cache_file, bool $pForceRefresh = false)
    {
	    // to prevent DDOS attack refresh only for admins
        
        $vRefresh = $this->getRefreshValue ($pForceRefresh);
    
        $tmpFilemtime = @filemtime($cache_file . self::UPDATING_SUFFIX); // false if no file
        if (!$tmpFilemtime || $vRefresh || (time() - $tmpFilemtime >= 60)) { // after 60 seconds force creation
            file_put_contents($cache_file . self::UPDATING_SUFFIX, date('Y-m-d H:i:s'));
            if (!empty($url)) {
                try {
                    file_put_contents($cache_file, $this->securedFileGetContentFromUrl($url));
                } catch (ExternalBazarServiceException $th) {
                }
            } else {
                file_put_contents($cache_file, $content);
            }
            if (file_exists($cache_file . self::UPDATING_SUFFIX)) {
                unlink($cache_file . self::UPDATING_SUFFIX);
            }
        }
    }

    /**
     * @param string $url
     *
     * @throws ExternalBazarServiceException
     */
    private function securedFileGetContentFromUrl($url): string
    {
        $destPath = tempnam('cache', 'tmp_to_delete_');
        $fp = fopen($destPath, 'wb');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // connect timeout in seconds
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // total timeout in seconds
        curl_exec($ch);
        $error = curl_errno($ch);
        curl_close($ch);
        fclose($fp);
        if (!$error && file_exists($destPath)) {
            $content = file_get_contents($destPath);
        }
        unlink($destPath);
        if ($error) {
            throw new ExternalBazarServiceException("Error getting content from $url");
        }

        return $content;
    }

    private function getFormUrl(array $urlDetails, $formId): string
    {
        return $urlDetails[0] . '/' . ($urlDetails[2] ? '' : '?') .
            str_replace(
                ['{pageTag}', '{firstSeparator}', '{formId}'],
                [$urlDetails[1], ($urlDetails[2] ? '?' : '&'), $formId],
                self::JSON_FORM_BASE_URL
            );
    }

    private function getEntriesViaApiUrl(array $urlDetails, $distantFormId, $querystring): string
    {
        return $urlDetails[0] . '/' . ($urlDetails[2] ? '' : '?') . 'api/forms/' . $distantFormId . '/entries' .
            (empty($querystring) ? '' : ($urlDetails[2] ? '?' : '&') . $querystring);
    }

    private function getEntriesViaJsonHandlerUrl(array $urlDetails, $distantFormId, $querystring): string
    {
        return $urlDetails[0] . '/' . ($urlDetails[2] ? '' : '?') . $urlDetails[1] .
            str_replace(
                ['{pageTag}', '{firstSeparator}', '{formId}'],
                [$urlDetails[1], ($urlDetails[2] ? '?' : '&'), $distantFormId],
                self::JSON_ENTRIES_OLD_BASE_URL
            ) .
            (empty($querystring) ? '' : ($urlDetails[2] ? '?' : '&') . $querystring);
    }

    private function extractErrors(string $json, string $from): string
    {
        // remove string before '{' because the aimed website's api can give warning messages
        $beginning = strpos($json, '{');
        if ($beginning > 1) {
            $noticeMessage = substr($json, 0, $beginning);
            $json = substr($json, $beginning);
            if ($this->debug && $this->wiki->UserIsAdmin()) {
                trigger_error($noticeMessage . ' from ' . $from);
            }
        }

        return $json;
    }
}
