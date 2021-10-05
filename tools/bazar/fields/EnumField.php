<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\ExternalBazarService;
use YesWiki\Bazar\Service\ListManager;

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
        $refreshCacheDuration = ($params->has('baz_enum_field_time_cache_for_json'))
            ? $params->get('baz_enum_field_time_cache_for_json')
            : 7200 ; // 2 hours by default
        $json = $this->getService(ExternalBazarService::class)->getJSONCachedUrlContent($this->getLinkedObjectName(), $refreshCacheDuration);
        $entries = json_decode($json, true);
        $options = [];
        $this->optionsUrls = [];
        foreach ($entries as $id => $entry) {
            if (!empty($entry['bf_titre'])) {
                $options[$id] = $entry['bf_titre'];
            }
            if (!empty($entry['url'])) {
                $this->optionsUrls[$id] = $entry['url'];
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

        $fiches = $entryManager->search([
            'queries' => $tabquery,
            'formsIds' => $this->getLinkedObjectName(),
            'keywords' => (!empty($this->keywords)) ? $this->keywords : ''
        ]);

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
}
