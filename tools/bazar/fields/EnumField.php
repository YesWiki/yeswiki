<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\ExternalBazarService;
use YesWiki\Bazar\Service\ListManager;

abstract class EnumField extends BazarField
{
    protected $options;

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

        $this->propertyName = $this->type . $this->name . $this->listLabel;
    }

    public function loadOptionsFromList()
    {
        if (!empty($this->name)) {
            $listValues = $this->getService(ListManager::class)->getOne($this->name);
            if (is_array($listValues)) {
                $this->options = $listValues['label'];
            }
        }
    }

    public function loadOptionsFromJson()
    {
        $json = $this->getService(ExternalBazarService::class)->getJSONCachedUrlContent($this->name);
        $this->options = array_map(function ($entry) {
            return $entry['bf_titre'];
        }, json_decode($json, true));
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
            'formsIds' => $this->name,
            'keywords' => (!empty($this->keywords)) ? $this->keywords : ''
        ]);

        foreach ($fiches as $fiche) {
            $this->options[$fiche['id_fiche']] = $fiche['bf_titre'];
        }
    }

    public function getOptions()
    {
        return  $this->options;
    }

    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'queries' => $this->queries,
                'options' => $this->getOptions(),
            ]
        );
    }
}
