<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Service\FicheManager;

/**
 * List with Bazar entries as a source
 */
abstract class EnumEntryField extends EnumField
{
    protected $keywords;
    protected $queries;

    protected const FIELD_KEYWORDS = 13;
    protected const FIELD_QUERIES = 15;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->keywords = $values[self::FIELD_KEYWORDS];
        $this->queries = $values[self::FIELD_QUERIES];

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

        $fiches = $services->get(FicheManager::class)->search([
            'queries' => $tabquery,
            'formsIds' => $this->name,
            'keywords' => (!empty($this->keywords)) ? $this->keywords : ''
        ]);

        $this->options['titre_liste'] = $this->label;
        foreach ($fiches as $fiche) {
            $this->options['label'][$fiche['id_fiche']] = $fiche['bf_titre'];
        }
    }
}
