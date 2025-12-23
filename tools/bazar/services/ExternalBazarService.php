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

    private $aURLDetailsCache;
    private $aAlreadyRefreshedURLs;
    private $aAlreadyCheckingDeletionsURLs;

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

        $this->aURLDetailsCache = null;
        $this->aAlreadyRefreshedURLs = [];
        $this->aAlreadyCheckingDeletionsURLs = [];        
    }

	private function getRefreshValue ($pRefresh = false)
	{
		// to prevent DDOS attack refresh only for admins
	return true;
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

        $vForm = $this->getJSONFromURL(
            $this->getFormUrl($vURLDetails, $pFormId),
            $this->timeCacheToRefreshForms,
            $vRefresh
        );

        if (!empty($vForm)) {
	        $vForm ["_isExternal_"] = true;
            return $vForm;
        } elseif ($this->debug) {
            trigger_error(get_class($this) . '::getForm: ' . _t('BAZ_EXTERNAL_SERVICE_BAD_RECEIVED_FORM'));
        }

        return null;
    }
	
	// getFormsForBazarListe : DEPRECATED : use getForms instead

	public function getFormsForBazarListe(array $pExternalIDs, bool $pRefresh = false): ?array
	{
		return getForms ($pExternalIDs, $pRefresh); 
	}
	
	public function getExternalFormIDKey ($pExternalID)
	{	
		$vURL = $this->formatUrl ($pExternalID["url"]);
	
		return preg_replace ("/[^a-zA-Z0-9\-\s.]/", "_", $vURL) . '.' . $pExternalID["id"];
	}
	
	public function getAllForms ($pServer)
	{
		$vURLDetails = $this->getURLDetails ($pServer);
	
		$vURL = $this->getFormUrl ($vURLDetails, "");
	
		$vAll = $this->getJSONFromURL ($vURL);
		
		$vResult  = [];

		foreach ($vAll as $vForm) {
			$vKey = $this->getExternalFormIDKey ([ "url" => $vURL, "id" => $vForm ['bn_id_nature'] ] );
		    
		    $vResult[$vKey] = $vForm;
		}
		
		return $vResult;
	}
	
    /**
     * get forms (locals and externals) for external bazarlist handling.
     *
     * @param array $pExternalIDs // format 'url' => url, 'id' => *id, 'localFormId' => $id
     *
     * @return array forms
     */
     
    public function getForms ($pExternalIDs, $pRefresh = false): ?array
    {    	
    	if (is_string ($pExternalIDs)) return $this->getAllForms ($pExternalIDs);
    
        if ($this->debug && $this->timeDebug) {
            $diffTime = -hrtime(true);
        }
        
        $this->cleanOldCacheFiles($pRefresh);
        
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

        foreach ($vGroupedExternalIDs as $vURL => $vExternalIDs) {        
			foreach ($vExternalIDs as $vIDValues) {                		
                $vLocalIDCorrespondToEmptyForm = false;
                
                $vLocalFormID = $vIDValues['localFormId'];
                $vExternalFormID = $vIDValues['id'];

                if ($vLocalFormID != "") {                                    
                    if ($vLocalForm = $this->formManager->getOne($vLocalFormID)) {
                        $vForms[$vLocalFormID . ""] = $vLocalForm;
                    } else {
                        $vLocalIDCorrespondToEmptyForm = true;
                    }
                }

				if ($vLocalFormID == "" || $vLocalIDCorrespondToEmptyForm) {                 
					$vLocalFormID = $vLocalIDCorrespondToEmptyForm ? $vLocalFormID : $this->findNewID();
                }

				$vExternalFormIDKey = $this->getExternalFormIDKey ([ "url" => $vURL, "id" => $vIDValues['id'] ] );
			
				$vExternalForm = $this->getForm($vURL, $vExternalFormID, $pRefresh, false);
					
				if (empty ($vExternalForm))
				{
					throw new ExternalBazarServiceException("External form ID " . $vExternalFormID . " doesn't exist on server : " . $vURL);
				}
				else
				{					
					$vExternalForm = $this->prepareExtForm($vLocalFormID, $vURL, $vExternalForm);
					
					$this->formManager->cacheForm ($vLocalFormID, $vExternalForm);
					$this->formManager->cacheForm ($vExternalFormIDKey, $vExternalForm);					
				}

				if (!empty ($vExternalForm)) $vForms[$vExternalFormIDKey] = $vExternalForm;
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

		$vExternalForms = array_filter ($vForms, function ($vKey) {
			return ((intval($vKey) . "") !== $vKey . "");
		}, ARRAY_FILTER_USE_KEY);

		foreach ($vExternalForms as $vExternalForm)
		{				
			$vURL = $vExternalForm['external_url'];
			$vLocalFormID = $vExternalForm['bn_id_nature'];            
			$vExternalFormID = $vExternalForm['external_bn_id_nature'];
			$vExternalFormLabel = $vExternalForm['external_bn_label_nature'];
			$vExternalFormIDKey = $vExternalForm['external_form_key'];			

			$vURLDetails = $this->getURLDetails($vURL, $this->timeCacheToCheckChanges);

			if (empty($vURLDetails)) {
				if ($this->debug) {
					trigger_error(get_class($this) . '::getEntries: ' . _t('BAZ_EXTERNAL_SERVICE_BAD_URL'));
				}
			} else {
				$vBatchEntries = $this->getJSONFromURL(
					$this->getEntriesViaApiUrl($vURLDetails, $vExternalFormID, $vURLSearchParams),
					$this->timeCacheToCheckChanges,
					$pParams ['refresh'],
					'entries'
				);
			
				if (empty($vBatchEntries)) {        
					// check if old route is working
					$vBatchEntries = $this->getJSONFromURL(
						$this->getEntriesViaJsonHandlerUrl($vURLDetails, $vExternalFormID, $vURLSearchParams),
						$this->timeCacheToCheckChanges,
						$pParams ['refresh']
					);
					
					if (is_array($vBatchEntries)) {
						$vBatchEntries = array_map(function ($vEntry) {
						    return ['html_data' => ''] + $vEntry;
						}, $vBatchEntries);
					}
				}

				if (is_array($vBatchEntries)) {
				
					if (isset ($vBatchEntries["error"]))
					{
						throw new ExternalBazarServiceException("Error while getting external entries : " . $vBatchEntries["error"]);
						return [];
					}
				
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
  						        'externalFormID' => $vExternalFormID,
  						        'externalFormLabel' => $vExternalFormLabel,
						        'localFormID' => $vLocalFormID,
						        'formIDKey' => $vExternalFormIDKey
						    ];
						    $vEntry['url'] = $vURL . '?' . $vEntry['id_fiche'];
						    $vEntry['id_typeannonce'] = $vLocalFormID;
						    
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

	/**
     * Get content from url from cache in JSON removing notice message.
     *
     * @param string $pURL        : url to get with  cache
     * @param int    $pCacheTTL : duration of the cache in second
     * @param string $mode       'standard' or 'entries'
     *
     * @return string file content from cache
     */
    
    public function getJSONFromURL (string $pURL, int $pCacheTTL = 90, bool $pForceRefresh = false, $pMode = 'standard')
    {
    	try {   
		    if (in_array($pURL, $this->aAlreadyRefreshedURLs)) {
			    // to prevent too many refreshes
		        $vTestFileModificationDate = false;
		        $pForceRefresh = false; 
		    } else {
		        $this->aAlreadyRefreshedURLs[] = $pURL;
		        $vTestFileModificationDate = true;
		    }

		    $vJSON = $this->getCachedURLContent($pURL, $vTestFileModificationDate, $pCacheTTL, $pForceRefresh, $pMode);
		    $vJSON = $this->extractErrors($vJSON, $pURL);
		}
		catch (ExternalBazarServiceException $e){
			return '{ "error" : "ExternalBazarService exception (' . $e->getLine () . ') = ' . $e->getMessage () . '"}';
		}
		catch (\Exception $e){
			return '{ "error" : "Exception in ExternalBazarService (' . $e->getLine () . ') = ' . $e->getMessage () . '"}';
		}
        
        return json_decode($vJSON, true);
    }

    /**
     * Get URL content from cache.
     *
     * @param string  $pURL        : url to get with  cache
     * @param bool    $pTestFileModificationDate
     * @param int     $pCacheTTL : duration of the cache in second
     * @param bool    $pForceRefresh
     * @param string  $pMode       'standard' or 'entries'
     *
     * @return string file content from cache
     */
     
    private function getCachedURLContent(
        string $pURL,
        bool $pTestFileModificationDate,
        int $pCacheTTL = 90,
        bool $pForceRefresh = false,
        string $pMode = 'standard'
    ) {
        $pCacheTTL = min($pCacheTTL, self::MAX_CACHE_TIME);
        
        $vCacheFile = ($pMode === 'entries')
            ? $this->cacheURLForEntries ($pURL, $pTestFileModificationDate, $pCacheTTL, $pForceRefresh)
            : $this->cacheURL ($pURL, $pTestFileModificationDate, $pCacheTTL, $pForceRefresh);

		$vContent = file_get_contents($vCacheFile);

		if ($vContent === false) {
			throw new ExternalBazarServiceException("Error getting content from $pURL");
			return "";
		}

        return $vContent;
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
     
    private function cacheURL(
        string $url,
        bool $pTestFileModificationDate,
        int $cache_life = 90,
        bool $pForceRefresh = false,
        string $dir = 'cache'
    ) {
        if ($this->debug && $this->timeDebug) {
            $diffTime = -hrtime(true);
        }
        
        $vRefresh = $this->getRefreshValue ($pForceRefresh);
        
        $cache_file = $dir . '/' . self::CACHE_FILENAME_PREFIX . $this->sanitizeFileName($url);

        $filemtime = @filemtime($cache_file); // returns FALSE if file does not exist
        
        if ($vRefresh || !$filemtime || ($pTestFileModificationDate && (time() - $filemtime >= $cache_life))) {
            $this->cacheURLContent($url, '', $cache_file, $vRefresh);
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
     
    private function cacheURLForEntries(
        string $pURL,
        bool $pTestFileModificationDate,
        int $pCacheTTL = 90,
        bool $pForceRefresh = false,
        string $dir = 'cache'
    ) {                
        if ($this->debug && $this->timeDebug) {
            $diffTime = -hrtime(true);
        }
        
        $vRefresh = $this->getRefreshValue ($pForceRefresh);
        
        $pURL = $this->sanitizeUrlForEntries($pURL);
        $vCacheFile = $dir . '/' . self::CACHE_FILENAME_PREFIX . $this->sanitizeFileName($pURL);

		// If the URL content is not cached or if we want to refresh, let's load the URL content

        if (!file_exists($vCacheFile) || $vRefresh) {
            $this->cacheURLContent($pURL, '', $vCacheFile, $vRefresh);
            
            if ($this->debug && $this->timeDebug) {
                $diffTime += hrtime(true);
                trigger_error('Caching entries :' . $diffTime / 1E+6 . ' ms ; url : ' . $pURL);
            }
        } elseif ($pTestFileModificationDate) {
            $filemtime = @filemtime($vCacheFile);  // returns FALSE if file does not exist
            
            if (time() - $filemtime >= $this->timeCacheToCheckDeletion) {
                $this->checkForDeletion($pURL, $vCacheFile);
                
                $this->checkOnlyEntriesChanges($pURL, $vCacheFile, $vRefresh);
                
                if ($this->debug && $this->timeDebug) {
                    $diffTime += hrtime(true);
                    trigger_error('Caching entries with deletion :' . $diffTime / 1E+6 . ' ms ; url : ' . $pURL);
                }
            } elseif (time() - $filemtime >= $pCacheTTL) {
                // only check for changes
                $this->checkOnlyEntriesChanges($pURL, $vCacheFile, $vRefresh);
                
                if ($this->debug && $this->timeDebug) {
                    $diffTime += hrtime(true);
                    trigger_error('Caching entries with changes :' . $diffTime / 1E+6 . ' ms ; url : ' . $pURL);
                }
            }
        }

        return $vCacheFile;
    }

    /**
     * Method cacheURLContent
     * Cache given URL content or load it before to cache it
     * create a temp file to indicate to other php session that the file is updating.
     *
     * @param string $pURL
     * @param string $pContent used if url if empty
     * @param string $cache_file
     * @param bool $pForceRefresh
     * @param string $content 
     *
     */
     
    private function cacheURLContent(string $pURL, string $pContent, string $pCacheFile, bool $pForceRefresh = false)
    {        
        $vRefresh = $this->getRefreshValue ($pForceRefresh);
    
    	$vUpdatingFile = $pCacheFile . self::UPDATING_SUFFIX;
    
        $vUpdatingFileModificationTime = @filemtime($vUpdatingFile); // false if no file
        
        if (!$vUpdatingFileModificationTime || $vRefresh || (time() - $vUpdatingFileModificationTime >= 60)) { // after 60 seconds force creation            
            file_put_contents($vUpdatingFile, date('Y-m-d H:i:s'));
            
            if (!empty($pURL))
	            $vContent = $this->loadURLContent($pURL);
			else
	            $vContent = $pContent;

			file_put_contents($pCacheFile, $vContent);
            
            if (file_exists($vUpdatingFile)) {
                unlink($vUpdatingFile);
            }
        }
    }

    /**
     * Method loadURLContent
     *
     * @param string $pURL to load
     *
     * @return string URL content loaded
     *
     * @throws ExternalBazarServiceException
     */
     
    private function loadURLContent($pURL): string
    {
        $vDestPath = tempnam('cache', 'tmp_to_delete_');
        
        $vFile = fopen($vDestPath, 'wb');

        $vCurl = curl_init($pURL);       

        curl_setopt($vCurl, CURLOPT_FILE, $vFile);
        curl_setopt($vCurl, CURLOPT_HEADER, 0);
        curl_setopt($vCurl, CURLOPT_CONNECTTIMEOUT, 3); // connect timeout in seconds
        curl_setopt($vCurl, CURLOPT_TIMEOUT, 30); // total timeout in seconds

        curl_exec($vCurl);

        $vError = curl_errno($vCurl);

        curl_close($vCurl);        
        fclose($vFile);

        if (!$vError && file_exists($vDestPath)) {
            $vContent = file_get_contents($vDestPath);
        }
        else
			$vContent = "";

        unlink($vDestPath);
        
        if ($vError) {
            throw new ExternalBazarServiceException("Error getting content from $pURL ($vError)");
        }

        return $vContent;
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
            
            $this->cacheURLContent($url, '', $cache_file, $forceRefresh);
        } else {
            list($lastModificationDate, $entries) = $lastModificationDate;
            
            $newEntries = $this->getNewEntries($url, $lastModificationDate);
            
            if (!empty($newEntries) && is_array($newEntries)) {
                foreach ($newEntries as $key => $entry) {
                    $entries[$entry['id_fiche'] ?? $key] = $entry;
                }
                
                $this->cacheURLContent('', json_encode($entries), $cache_file, $forceRefresh);
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
        
        if (in_array($urlToCheckDeletion, $this->aAlreadyCheckingDeletionsURLs)) {
            return null;
        } else {
            $this->aAlreadyCheckingDeletionsURLs[] = $urlToCheckDeletion;
        }
        
        $vJSON = file_get_contents($cache_file);
        $vJSON = $this->extractErrors($vJSON, $cache_file);

        $entries = json_decode($vJSON, true);
        
        if (empty($entries) || !is_array($entries)) {
            $this->cacheURLContent($url, '', $cache_file, false);
                       
            if ($this->debug && $this->timeDebug) {
                $diffTime += hrtime(true);
                trigger_error('checking deletions (refreshing) :' . $diffTime / 1E+6 . ' ms ; url : ' . $url);
            }
        } else {
            $entriesList = json_decode($this->extractErrors($this->loadURLContent($urlToCheckDeletion), $urlToCheckDeletion), true);
            
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
            
            $this->cacheURLContent('', json_encode($entries), $cache_file, false);
            
            if ($this->debug && $this->timeDebug) {
                $diffTime += hrtime(true);
                trigger_error('Updating deletions :' . $diffTime / 1E+6 . ' ms ; url : ' . $url);
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
        $vJSON = file_get_contents($cache_file);
        $vJSON = $this->extractErrors($vJSON, $cache_file);
        
        $entries = json_decode($vJSON, true);

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
            $newEntries = json_decode($this->extractErrors($this->loadURLContent($sanitizedUrl), $sanitizedUrl), true);
            if ($this->debug && $this->timeDebug) {
                $diffTime += hrtime(true);
                trigger_error('Getting new entries :' . $diffTime / 1E+6 . ' ms ; url : ' . $url . ' ; sanitizedUrl : ' . $sanitizedUrl);
            }

            return (empty($newEntries) || !is_array($newEntries)) ? null : $newEntries;
        } catch (ExternalBazarServiceException $th) {
          	throw $th;
            return null;            
        }
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
        $form['external_bn_label_nature'] = $form['bn_label_nature'];
        $form['external_url'] = $url;
        $urlDetails = $this->getURLDetails($url, 999999); // no reset of cache because just done before
        $form['bn_id_nature'] = $localFormId;
        $form['external_form_key'] = $this->getExternalFormIDKey ([ "url" => $url, "id" => $form['external_bn_id_nature' ]]);
        
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
     
    private function cleanOldCacheFiles($pRefresh = false)
    {
        $vCacheFiles = glob('cache/' . self::CACHE_FILENAME_PREFIX . '*');
    
	    $vRefresh = $this->getRefreshValue ($pRefresh);
        
        foreach ($vCacheFiles as $vFilePath) {
            $vModificationTime = @filemtime($vFilePath);  // returns FALSE if file does not exist

            if ($vRefresh || !$vModificationTime or (time() - $vModificationTime >= self::MAX_CACHE_TIME)) {
                unlink($vFilePath);
            }
        }
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
            $queries = explode ("&", $query);
            
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

            $newQuery = implode('&', $queries);
            
            $url = str_replace($query, $newQuery, $url);
        }

        return $url;
    }

    /**
     * get rewrite mode, base url for this external url.
     * @param string $pURL
     * @param int    $pCacheTTL : duration of the cache in second
     * @param string $pDirectory : base dirname where save the cache
     *
     * @return array [$baseUrl,$rootPage,$rewriteModeEnabled]
     */
     
    private function getURLDetails(string $pURL, int $pCacheTTL = 120, string $pDirectory = 'cache'): array
    {   
        if (!isset($this->aURLDetailsCache[$pURL])) {
            $vCacheFile = $pDirectory . '/' . self::CACHE_FILENAME_PREFIX . self::CACHE_FILENAME_DETAILS_PREFIX . $this->sanitizeFileName($pURL);
            
            $vModificationTime = @filemtime($vCacheFile);  // returns FALSE if file does not exist

            if (!$vModificationTime or (time() - $vModificationTime >= $pCacheTTL)) {
                $vDetails = $this->importService->extractBaseUrlAndRootPage($pURL);
                file_put_contents($vCacheFile, json_encode($vDetails));
            } else {
                $vDetails = json_decode($this->extractErrors(file_get_contents($vCacheFile), $vCacheFile), true);
            }

            $this->aURLDetailsCache[$pURL] = $vDetails;
        }

        return $this->aURLDetailsCache[$pURL];
    }

    private function getFormUrl(array $urlDetails, $formId): string
    {
        return $urlDetails[0] . "/?api/forms/$formId";
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
     
    private function findNewID(): int
    {
    	$vNewID = $this->formManager->findNewId();
    
    	return $vNewID;
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
    
    private function extractErrors(string $pJSON, string $pFrom): string
    {
        // remove string before '{' because the aimed website's api can give warning messages
        $beginning = strpos($pJSON, '{');
        if ($beginning > 1) {
            $noticeMessage = substr($pJSON, 0, $beginning);
            $pJSON = substr($pJSON, $beginning);
            if ($this->debug && $this->wiki->UserIsAdmin()) {
                trigger_error($noticeMessage . ' from ' . $pFrom);
            }
        }

        return $pJSON;
    }
    
    // DEPRECATED : should use renamed getJSONFromURL
    
    public function getJSONCachedUrlContent(string $url, int $cache_life = 90, bool $pForceRefresh = false, $mode = 'standard')
    {         
    	return json_encode ($this->getJSONFromURL ($url, $cache_life, $pForceRefresh, $mode));
    }    
}
