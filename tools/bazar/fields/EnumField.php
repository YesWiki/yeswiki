<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Service\FicheManager;

abstract class EnumField extends BazarField
{
    protected $options;

    protected $listLabel;
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

        $this->propertyName = $values[self::FIELD_TYPE] . $values[self::FIELD_NAME] . $values[self::FIELD_LIST_LABEL];
    }

    public function loadOptionsFromList()
    {
        $this->options = baz_valeurs_liste($this->name);
        $this->options['id'] = $this->name;
    }

    public function loadOptionsFromEntries()
    {
        $ficheManager = $this->getService(FicheManager::class);

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

        $fiches = $ficheManager->search([
            'queries' => $tabquery,
            'formsIds' => $this->name,
            'keywords' => (!empty($this->keywords)) ? $this->keywords : ''
        ]);

        $this->options['titre_liste'] = $this->label;
        foreach ($fiches as $fiche) {
            $this->options['label'][$fiche['id_fiche']] = $fiche['bf_titre'];
        }
    }

    public function getOptions()
    {
        return  $this->options;
    }
}
