<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Wiki;

class ExternalBazarService
{
    protected $debug;
    protected $timeCacheForEntries ;
    protected $timeCacheForForms ;
    protected $formManager ;
    protected $entryManager ;
    protected $wiki ;

    
    protected $newFormId;
    protected $tmpForm ;

    public function __construct(
        Wiki $wiki,
        ParameterBagInterface $params,
        FormManager $formManager,
        EntryManager $entryManager
    ) {
        $this->wiki = $wiki;
        $this->params = $params;
        $this->formManager = $formManager;
        $this->entryManager = $entryManager;
        $this->debug = ($this->params->has('debug') && $this->params->get('debug') =='yes');
        $this->timeCacheForEntries = $this->params->has('baz_external_service_time_cache_for_entries')
            ? (int) $this->params->get('baz_external_service_time_cache_for_entries') : 60 ; // seconds
        $this->timeCacheForForms = $this->params->has('baz_external_service_time_cache_for_forms')
            ? (int) $this->params->get('baz_external_service_time_cache_for_forms') : 1200 ; // seconds

        
        $this->newFormId = null;
        $this->tmpForm = null;
    }

    /**
     * get a form from external wiki
     * @param string $url
     * @param int $formId
     * @param bool $refreshCache
     * @param bool $checkUrl
     * @return null|array
     */
    public function getForm(string $url, int $formId, bool $refreshCache = false, bool $checkUrl = true) : ?array
    {
        if ($checkUrl) {
            $url= $this->formatUrl($url);
        }

        $json = $this->getJSONCachedUrlContent($url.'?BazaR/json&demand=forms&id='.$formId, $refreshCache  ? 0 : $this->timeCacheForForms);
        $forms = json_decode($json, true);

        if ($forms) {
            return $forms[0];
        } elseif ($this->debug) {
            trigger_error("Erreur ExternalWikiService::getForm: contenu du formulaire mal formaté.");
            return null;
        }
    }

    // NOT USED
    /**
     * get all forms from external wiki
     * @param string $url
     * @param bool $refreshCache
     * @param bool $checkUrl
     * @return null|array
     */
    // public function getForms(string $url, bool $refreshCache = false, bool $checkUrl = true) : ?array
    // {
    //     if ($checkUrl) {
    //         $url= $this->formatUrl($url);
    //     }

    //     $json = $this->getJSONCachedUrlContent($url.'?BazaR/json&demand=forms', $refreshCache  ? 0 : $this->timeCacheForForms);
    //     $forms = json_decode($json, true);

    //     if ($forms) {
    //         return $forms;
    //     } elseif ($this->debug) {
    //         trigger_error("Erreur ExternalWikiService::getForms: contenu des formulaires mal formaté.");
    //         return null;
    //     }
    // }

    /**
     * get forms from external wiki
     * @param array $externalIds // format 'url' => url, 'id' => *id, 'localFormId' => $id
     * @param bool $refreshCache
     * @return array forms
     */
    public function getFormsForBazarListe(array $externalIds, bool $refreshCache = false) : ?array
    {
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
                        if ($form = $this->getForm($url, $values['id'], $refreshCache, false)) {
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
                'refeshCache' => false, // parameter to force refresh cache
            ],
            $params
        );

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
                $localEntries = array_values($this->entryManager->search([
                    'queries' => $params['queries'],
                    'formsIds' => [$localFormId]
                ]));
                array_push($entries, ...$localEntries);
            } else {
                $distantFormId = $form['external_bn_id_nature'];
                $json = $this->getJSONCachedUrlContent(
                    $url.'?api/forms/'.$distantFormId.'/entries', // .$querystring, // TODO use query in api
                    $params['refreshCache']  ? 0 : $this->timeCacheForEntries
                );
                $batchEntries = json_decode($json, true);
                // replace formId
                foreach ($batchEntries as $entry) {
                    // save external data with key 'external-data' because '-' is not used for name
                    $entry['external-data'] = [
                            // 'origin_id_typeannonce' => $entry['id_typeannonce'], // if needed in fields
                            'baseUrl' => $url,
                        ];
                    $entry['id_typeannonce'] =$localFormId;
                    $entries[] = $entry;
                }
            }
        }

        if (!empty($entries)) {
            return $entries;
        } elseif ($this->debug) {
            trigger_error("Erreur ExternalWikiService::getEntries: contenu des fiches mal formaté.");
            return null;
        }
    }

    public function formatUrl($url)
    {
        $matches = [];
        // cath part before wakka.php or first /? and add / else take all $url
        if (preg_match('/([^\?]*)(?:(?:\/wakka\.php|\/\?)[^\?]*)/', $url, $matches)) {
            $newUrl = $matches[1] . '/';
        } else {
            $newUrl = $url;
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
        $cache_file = $this->cacheUrl($url, $cache_life);
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
        $cache_file = $dir.'/'.removeAccents(preg_replace('/--+/u', '-', preg_replace('/[[:punct:]]/', '-', $url)));

        $filemtime = @filemtime($cache_file);  // returns FALSE if file does not exist
        if (!$filemtime or (time() - $filemtime >= $cache_life)) {
            file_put_contents($cache_file, file_get_contents($url));
        }
        return $cache_file;
    }

    /**
     * display external image
     * TODO move it to a controller
     * @param array $entry external entry
     * @param null|string $imageFileName
     * @return string|null html to display
     */
    public function displayExternalImage(array $entry, ?string $value):?string
    {
        return 'test';
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

        // parse external fields

        $form['prepared'] = $this->formManager->prepareData($form);

        return $form;
    }
}
