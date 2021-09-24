<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\ImportService;
use YesWiki\Wiki;
use YesWiki\Bazar\Field\ExternalImageField;

class ExternalBazarService
{
    public const FIELD_JSON_FORM_ADDR = 3 ;// replace FIELD_SIZE = 3;
    public const FIELD_ORIGINAL_TYPE = 4 ;// FIELD_MAX_CHARS = 4;

    private const MAX_CACHE_TIME = 864000 ; // 10 days ot to keep external data in local
    private const JSON_FORM_BASE_URL = 'BazaR/json&demand=forms&id=';
    private const JSON_ENTRIES_OLD_BASE_URL = 'BazaR/json&demand=entries&id=';
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

    protected $debug;
    protected $timeCacheForEntries ;
    protected $timeCacheForForms ;
    protected $formManager ;
    protected $entryManager ;
    protected $importService ;
    protected $wiki ;

    
    protected $newFormId;
    protected $tmpForm ;
    private $urlCache;

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
        $this->debug = ($this->params->has('debug') && $this->params->get('debug') =='yes');
        $this->timeCacheForEntries = $this->params->has('baz_external_service_time_cache_for_entries')
            ? (int) $this->params->get('baz_external_service_time_cache_for_entries') : 60 ; // seconds
        $this->timeCacheForForms = $this->params->has('baz_external_service_time_cache_for_forms')
            ? (int) $this->params->get('baz_external_service_time_cache_for_forms') : 1200 ; // seconds

        
        $this->newFormId = null;
        $this->tmpForm = null;
        $this->urlCache = null;
    }

    /**
     * get a form from external wiki
     * @param string $url
     * @param int $formId
     * @param bool $refresh
     * @param bool $checkUrl
     * @return null|array
     */
    public function getForm(string $url, int $formId, bool $refresh = false, bool $checkUrl = true) : ?array
    {
        if ($checkUrl) {
            $url= $this->formatUrl($url);
        }
        $urlDetails = $this->getUrlDetails($url, $refresh  ? 0 : $this->timeCacheForForms);
        if (empty($urlDetails)) {
            if ($this->debug) {
                trigger_error(get_class($this)."::getForm: "._t('BAZ_EXTERNAL_SERVICE_BAD_URL'));
            }
            return null;
        }

        // to prevent DDOS attack refresh only for connected
        if (!$this->wiki->GetUser()) {
            $refresh = false;
        }

        $json = $this->getJSONCachedUrlContent($urlDetails[0].'/'.($urlDetails[2] ? '' : '?').self::JSON_FORM_BASE_URL.$formId, $refresh  ? 0 : $this->timeCacheForForms);
        $forms = json_decode($json, true);

        if ($forms) {
            return $forms[0];
        } elseif ($this->debug) {
            trigger_error(get_class($this)."::getForm: "._t('BAZ_EXTERNAL_SERVICE_BAD_RECEIVED_FORM'));
        }
        return null;
    }

    /**
     * get forms from external wiki
     * @param array $externalIds // format 'url' => url, 'id' => *id, 'localFormId' => $id
     * @param bool $refresh
     * @return array forms
     */
    public function getFormsForBazarListe(array $externalIds, bool $refresh = false) : ?array
    {
        $this->cleanOldCacheFiles();

        if (!$this->checkexternalIdsFormat($externalIds)) {
            // error
            return null;
        }
        $groupedExternalIds = $this->groupIdsByUrl($externalIds);

        $forms = [];
        foreach ($groupedExternalIds as $url => $ids) {
            // local form
            if (empty($url)) {
                foreach ($ids as $values) {
                    if ($form = $this->formManager->getOne($values['id'])) {
                        $forms[] = $form;
                    }
                }
            } else {
                foreach ($ids as $values) {
                    if (empty($values['localFormId'])) {
                        if ($form = $this->getForm($url, $values['id'], $refresh, false)) {
                            $localFormId = $this->findNewId();
                            $form = $this->prepareExtForm($localFormId, $url, $form);
                            // put in cache in FormManager
                            $this->tmpForm = $form;
                            $result = $this->formManager->putInCacheFromExternalBazarService($localFormId);
                            $this->tmpForm = null;
                            if ($result) {
                                $forms[] = $form;
                            }
                        }
                    } else {
                        $localFormId = $values['localFormId'];
                        if ($form = $this->formManager->getOne($localFormId)) {
                            $form['external_bn_id_nature'] = $values['id'];
                            $form['external_url'] = $url;
                            $forms[] = $form;
                        }
                    }
                }
            }
        }
        return $forms;
    }

    /**
     * get Entries linked to forms
     * @param array $params
     * @return array|null $entries
     */
    public function getEntries($params):?array
    {
        // Merge les paramètres passé avec des paramètres par défaut
        $params = array_merge(
            [
                'forms' => [], // forms
                'queries' => '', // Sélection par clé-valeur
                'refresh' => false, // parameter to force refresh cache
            ],
            $params
        );

        // to prevent DDOS attack refresh only for connected
        if (!$this->wiki->GetUser()) {
            $params['refresh'] = false;
        }

        if (empty($params['forms'])) {
            throw new \Exception("parameter forms should not be empty");
        }

        // Formattage des queries
        $querystring = '';
        if (is_array($params['queries'])) {
            foreach ($params['queries'] as $key => $value) {
                if (is_array($value)) {
                    $value = implode(',', $value);
                }
                $querystring .= $key.'='.$value.'|';
            }
            $querystring = !empty($querystring) ? '&query='.htmlspecialchars(substr($querystring, 0, -1)) : '';
        }

        $entries = [];
        foreach ($params['forms'] as $form) {
            $localFormId = $form['bn_id_nature'];
            $url = $form['external_url'] ?? null;
            // local
            if (empty($url)) {
                $localEntries = array_values($this->entryManager->search(
                    [
                        'queries' => $params['queries'],
                        'formsIds' => [$localFormId]
                    ],
                    true // filter on read ACL
                ));
                array_push($entries, ...$localEntries);
            } else {
                $distantFormId = $form['external_bn_id_nature'];
                
                $urlDetails = $this->getUrlDetails($url, $this->timeCacheForEntries);
                if (empty($urlDetails)) {
                    if ($this->debug) {
                        trigger_error(get_class($this)."::getEntries: "._t('BAZ_EXTERNAL_SERVICE_BAD_URL'));
                    }
                } else {
                    $json = $this->getJSONCachedUrlContent(
                        $urlDetails[0].'?api/forms/'.$distantFormId.'/entries'.$querystring,
                        $params['refresh']  ? 0 : $this->timeCacheForEntries
                    );
                    $batchEntries = json_decode($json, true);
                    if (empty($batchEntries)) {
                        // check if old route is working
                        $json = $this->getJSONCachedUrlContent(
                            $urlDetails[0].'/'.($urlDetails[2] ? '' : '?').self::JSON_ENTRIES_OLD_BASE_URL.$distantFormId.$querystring,
                            $params['refresh']  ? 0 : $this->timeCacheForEntries
                        );
                        $batchEntries = json_decode($json, true);
                        if (is_array($batchEntries)) {
                            $batchEntries = array_map(function ($entry) {
                                return ['html_data' => ''] + $entry;
                            }, $batchEntries);
                        }
                    }
                    if (is_array($batchEntries)) {
                        // replace formId
                        foreach ($batchEntries as $entry) {
                            // save external data with key 'external-data' because '-' is not used for name
                            $entry['external-data'] = [
                                    // 'origin_id_typeannonce' => $entry['id_typeannonce'], // if needed in fields
                                    'baseUrl' => $url,
                                ];
                            $entry['url'] = $url . '?' . $entry['id_fiche'];
                            $entry['id_typeannonce'] =$localFormId;
                            $entries[] = $entry;
                        }
                    }
                }
            }
        }

        if (!empty($entries)) {
            return $entries;
        } elseif ($this->debug) {
            trigger_error(get_class($this)."::getEntries: "._t('BAZ_EXTERNAL_SERVICE_BAD_RECEIVED_ENTRIES'));
            return null;
        }
        return [];
    }

    // TODO detect if external url has short url without '?'
    // by testing the base Url and checking the presence of '?'
    public function formatUrl($url)
    {
        $matches = [];
        // cath part before wakka.php or first /? and add / else take all $url
        if (preg_match('/([^\?]*)(?:(?:\/wakka\.php|\/\?)[^\?]*)/', $url, $matches)) {
            $newUrl = $matches[1] . '/';
        } else {
            $newUrl = $url;
        }
        // add / at end if needed
        if (substr($newUrl, -1) !== '/') {
            $newUrl = $newUrl . '/';
        }
        return $newUrl;
    }

    /**
     * Get content from url from cache
     *
     * @param string $url : url to get with  cache
     * @param int $cache_life : duration of the cahe in second
     * @return string file content from cache
     */
    private function getCachedUrlContent(string $url, int $cache_life = 60)
    {
        $cache_file = $this->cacheUrl($url, min($cache_life, self::MAX_CACHE_TIME));
        return file_get_contents($cache_file);
    }

    /**
     * Get content from url from cache in JSON removing notice message
     *
     * @param string $url : url to get with  cache
     * @param int $cache_life : duration of the cahe in second
     * @return string file content from cache
     */
    public function getJSONCachedUrlContent(string $url, int $cache_life = 60)
    {
        $json = $this->getCachedUrlContent($url, $cache_life);

        // remove string before '{' because the aimed website's api can give warning messages
        // TODO catch error warning in api before sending data
        $beginning = strpos($json, '{');
        if ($beginning > 1) {
            $noticeMessage = substr($json, 0, $beginning);
            $json = substr($json, $beginning);
            if ($this->debug) {
                trigger_error($noticeMessage.' from '.$url);
            }
        }

        return $json;
    }

    /**
     * put in cache result of url
     *
     * @param string $url : url to get with  cache
     * @param int $cache_life : duration of the cahe in second
     * @param string $dir : base dirname where save the cache
     * @return string location of cached file
     */
    public function cacheUrl(string $url, int $cache_life = 60, string $dir = 'cache')
    {
        $cache_file = $dir.'/'.self::CACHE_FILENAME_PREFIX.self::CACHE_FILENAME_DETAILS_PREFIX.$this->sanitizeFileName($url);

        $filemtime = @filemtime($cache_file);  // returns FALSE if file does not exist
        if (!$filemtime or (time() - $filemtime >= $cache_life)) {
            file_put_contents($cache_file, file_get_contents($url));
        }
        return $cache_file;
    }

    /**
     * sanitize file name
     * @param string $inputString
     * @return string $outputString
     */
    private function sanitizeFileName(string $inputString):string
    {
        return removeAccents(preg_replace('/--+/u', '-', preg_replace('/[[:punct:]]/', '-', $inputString)));
    }

    /**
     * check format of externalIds
     * @param array $externalIds
     * @return bool
     */
    private function checkexternalIdsFormat(array $externalIds): bool
    {
        return empty(array_filter($externalIds, function ($externalId) {
            return !isset($externalId['url']) || !isset($externalId['id']) || !isset($externalId['localFormId']);
        }));
    }

    /**
     * groups ids by url
     * @param array $externalIds
     * @return array
     */
    private function groupIdsByUrl(array $externalIds): array
    {
        // group ids by url
        $groupedExternalIds = [];
        foreach ($externalIds as $externalId) {
            if (!empty($externalId['url'])) {
                $url = $this->formatUrl($externalId['url']);
                $url = empty($url) ? $externalId['url'] : $url;
            } else {
                $url = '';
            }
            $groupedExternalIds[$url][] = [
                'id' => $externalId['id'],
                'localFormId' => $externalId['localFormId'],
            ];
        }

        return $groupedExternalIds ;
    }

    /**
     * get newFormId usinf FormManager at first call
     * @return int
     */
    private function findNewId():int
    {
        if (is_null($this->newFormId)) {
            $this->newFormId = $this->formManager->findNewId();
        } else {
            $this->newFormId = ($this->newFormId == 999) ? 10001 : $this->newFormId  +1;
        }
        return $this->newFormId ;
    }

    /**
     * get temp form (to give data to FormManager)
     * @return array
     */
    public function getTmpForm():array
    {
        return $this->tmpForm ;
    }

    /**
     * prepare external form
     * @param int $localFormId
     * @param string $url
     * @param array $form
     * @return array $form
     */
    private function prepareExtForm(int $localFormId, string $url, array $form):array
    {
        // update FormId
        $form['external_bn_id_nature'] = $form['bn_id_nature'];
        $form['external_url'] = $url;
        $form['bn_id_nature'] = $localFormId;

        // change fields type before prepareData
        foreach ($form['template'] as $index => $fieldTemplate) {
            if (isset(self::CONVERT_FIELD_NAMES[$fieldTemplate[0]])) {
                $form['template'][$index][self::FIELD_ORIGINAL_TYPE] = $fieldTemplate[0];
                $form['template'][$index][0] = self::CONVERT_FIELD_NAMES[$fieldTemplate[0]];
                $form['template'][$index][self::FIELD_JSON_FORM_ADDR] = $url.self::JSON_FORM_BASE_URL.$form['external_bn_id_nature'];
            } elseif (isset(self::CONVERT_FIELD_NAMES_FOR_IMAGES[$fieldTemplate[0]])) {
                $form['template'][$index][0] = self::CONVERT_FIELD_NAMES_FOR_IMAGES[$fieldTemplate[0]];
                $form['template'][$index][ExternalImageField::FIELD_JSON_FORM_ADDR] = $url.self::JSON_FORM_BASE_URL.$form['external_bn_id_nature'];
            }
            // add missing indexes
            if (count($form['template'][$index]) < 15) {
                for ($i=count($form['template'][$index]); $i < 16; $i++) {
                    $form['template'][$index][$i] = '';
                }
            }
        }

        // parse external fields

        $form['prepared'] = $this->formManager->prepareData($form);

        return $form;
    }

    /**
     * clean old cache files to prevent leak of data between sites
     *
     */
    private function cleanOldCacheFiles()
    {
        $cacheFiles = glob('cache/'.self::CACHE_FILENAME_PREFIX.'*');
        foreach ($cacheFiles as $filePath) {
            $filemtime = @filemtime($filePath);  // returns FALSE if file does not exist
            if (!$filemtime or (time() - $filemtime >= self::MAX_CACHE_TIME)) {
                unlink($filePath);
            }
        }
    }

    /**
     * get rewrite mode, base url for this external url
     * @param string $url
     * @param int $cache_life : duration of the cahe in second
     * @param string $dir : base dirname where save the cache
     * @return array [$baseUrl,$rootPage,$rewriteModeEnabled]
     */
    private function getUrlDetails(string $url, int $cache_life = 60, string $dir = 'cache'): array
    {
        if (!isset($this->urlCache[$url])) {
            $cache_file = $dir.'/'.self::CACHE_FILENAME_PREFIX.$this->sanitizeFileName($url);
            $filemtime = @filemtime($cache_file);  // returns FALSE if file does not exist

            if (!$filemtime or (time() - $filemtime >= $cache_life)) {
                $details = $this->importService->extractBaseUrlAndRootPage($url) ;
                file_put_contents($cache_file, json_encode($details));
            } else {
                $details = json_decode(file_get_contents($cache_file));
            }
            
            $this->urlCache[$url] = $details;
        }
        return $this->urlCache[$url];
    }
}
