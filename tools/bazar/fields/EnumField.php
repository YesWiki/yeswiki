<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\ExternalBazarService;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Wiki;

abstract class EnumField extends BazarField
{
    protected $options;
    protected $optionsUrls; // only for loadOptionsFromJson

    protected $listLabel; // Allows to differentiate two enums using the same list
    protected $keywords;
    protected $queries;

    protected const FIELD_LIST_LABEL = 6;
    protected const FIELD_KEYWORDS = 13;
    protected const FIELD_QUERIES = 15;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->listLabel = $values[self::FIELD_LIST_LABEL];
        $this->keywords = $values[self::FIELD_KEYWORDS];
        $this->queries = $values[self::FIELD_QUERIES];

        $this->options = [];
        $this->optionsUrls = [];

        $this->propertyName = $this->type . $this->name . $this->listLabel;
    }

    public function loadOptionsFromList()
    {
        if (!empty($this->getLinkedObjectName())) {
            $listValues = $this->getService(ListManager::class)->getOne($this->getLinkedObjectName());
            if (is_array($listValues)) {
                $this->options = $listValues['label'];
            }
        }
    }

    public function loadOptionsFromJson()
    {
        $params = $this->getService(ParameterBagInterface::class);
        $refreshCacheDuration = ($params->has('baz_external_service_time_cache_to_check_changes'))
            ? $params->get('baz_external_service_time_cache_to_check_changes')
            : 7200 ; // 2 hours by default
        $json = $this->getService(ExternalBazarService::class)->getJSONCachedUrlContent(
            $this->sanitizeUrlForEntries($this->getLinkedObjectName()),
            $refreshCacheDuration,
            isset($_GET['refresh']) && ($_GET['refresh'] === 'true') && $this->getService(Wiki::class)->UserIsAdmin(),
            'entries'
        );
        $entries = json_decode($json, true);
        $options = [];
        $this->optionsUrls = [];
        if (is_array($entries)) {
            foreach ($entries as $id => $entry) {
                if (!empty($entry['bf_titre'])) {
                    $options[$id] = $entry['bf_titre'];
                }
                if (!empty($entry['url'])) {
                    $this->optionsUrls[$id] = $entry['url'];
                }
            }
        }
        asort($options);
        $this->options = $options ;
    }

    protected function loadOptionsFromJSONForm($JSONAddress): array
    {
        $json = $this->getService(ExternalBazarService::class)->getJSONCachedUrlContent($JSONAddress, 9000000);
        // do not refresh less than 99 days because cache defined by ExternalBazarService
        $form = json_decode($json, true);
        if (isset($form[0]['prepared'])) {
            foreach ($form[0]['prepared'] as $field) {
                // be carefull it is an array here
                if (isset($field['propertyname']) && ($field['propertyname'] == $this->getPropertyName())) {
                    $this->options = $field['options'] ?? [];
                    return $this->options ;
                }
            }
        }
        $this->options = [];
        return $this->options ;
    }

    public function loadOptionsFromEntries()
    {
        $entryManager = $this->getService(EntryManager::class);

        $tabquery = [];
        if (!empty($this->queries)) {
            $tableau = array();
            $tab = explode('|', $this->queries);
            //découpe la requete autour des |
            foreach ($tab as $req) {
                $tabdecoup = explode('=', $req, 2);
                $tableau[$tabdecoup[0]] = isset($tabdecoup[1]) ? trim($tabdecoup[1]) : '';
            }
            $tabquery = array_merge($tabquery, $tableau);
        } else {
            $tabquery = '';
        }

        $fiches = $entryManager->search(
            [
                'queries' => $tabquery,
                'formsIds' => $this->getLinkedObjectName(),
                'keywords' => (!empty($this->keywords)) ? $this->keywords : ''
            ],
            true, // filter on read ACL
            true  // use Guard
        );

        $this->options = [];
        foreach ($fiches as $fiche) {
            $this->options[$fiche['id_fiche']] = $fiche['bf_titre'];
        }
        if (is_array($this->options)) {
            asort($this->options);
        }
    }

    /**
     * prepareJSON for RadioEntriField or SelectEntryField
     */
    protected function prepareJSONEntryField()
    {
        $this->propertyName = $this->type . removeAccents(preg_replace('/--+/u', '-', preg_replace('/[[:punct:]]/', '-', $this->name))) . $this->listLabel;
        $this->loadOptionsFromJson();
        if (preg_match('/^(.*\/\??)'// catch baseUrl
                .'(?:' // followed by
                .'\w*\/json&(?:.*)demand=entries(?:&.*)?' // json handler with demand = entries
                .'|api\/forms\/[0-9]*\/entries' // or api forms/{id}/entries
                .'|api\/entries\/[0-9]*' // or api entries/{id}
                .')/', $this->name, $matches)) {
            $this->baseUrl = $matches[1];
        } else {
            $this->baseUrl = $this->name ;
        }
        $this->options = null ;
    }

    public function getOptions()
    {
        return  $this->options;
    }

    protected function getEntriesOptions()
    {
        // load options only when needed but not at construct to prevent infinite loops
        if (is_null($this->options)) {
            if ($this->isDistantJson) {
                $this->loadOptionsFromJson();
            } else {
                $this->loadOptionsFromEntries();
            }
        }
        return  $this->options;
    }

    public function getName()
    {
        return $this->listLabel;
    }

    public function getLinkedObjectName()
    {
        return $this->name;
    }

    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'linkedObjectName' => $this->getLinkedObjectName(),
                'queries' => $this->queries,
                'options' => $this->getOptions(),
            ]
        );
    }

    
    /**
     * check existence of &fields=bf_titre,id_fiche,url in url when api
     * @param string $url
     * @return string $url
     */
    private function sanitizeUrlForEntries(string $url): string
    {
        // sanitize url
        $query = parse_url($url, PHP_URL_QUERY);
        if (!empty($query)) {
            $queries = explode('&', $query);
            if (substr($queries[0], 0, 3) === "api") {
                foreach ($queries as $key => $elem) {
                    $extraction = explode('=', $elem, 2);
                    if ($extraction[0] === 'fields') {
                        $fields = explode(',', $extraction[1]);
                        $fields = $fields + ['id_fiche','bf_titre','url'];
                        $queries[$key] = 'fields=' . implode(',', $fields);
                    }
                }
                if (empty($fields)) {
                    $queries[] = 'fields=id_fiche,bf_titre,url';
                }
                $newQuery = implode('&', $queries);
                $url = str_replace($query, $newQuery, $url);
            }
        }
        return $url;
    }
}
