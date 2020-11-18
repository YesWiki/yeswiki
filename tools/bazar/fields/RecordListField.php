<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Service\FicheManager;

abstract class RecordListField extends BazarField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->recordId = $values[self::FIELD_TYPE] . $values[self::FIELD_ID] . $values[6];

        $tabquery = array();
        if (!empty($values[self::FIELD_QUERIES])) {
            $tableau = array();
            $tab = explode('|', $values[self::FIELD_QUERIES]);
            //découpe la requete autour des |
            foreach ($tab as $req) {
                $tabdecoup = explode('=', $req, 2);
                $tableau[$tabdecoup[0]] = isset($tabdecoup[1]) ? trim($tabdecoup[1]) : '';
            }
            $tabquery = array_merge($tabquery, $tableau);
        } else {
            $tabquery = '';
        }
        $hash = md5($values[self::FIELD_ID] . serialize($tabquery));
        if (!isset($result[$hash])) {
            $result[$hash] = $services->get(FicheManager::class)->search([
                'queries' => $tabquery,
                'formsIds' => $values[self::FIELD_ID],
                'keywords' => (!empty($values[self::FIELD_KEYWORDS])) ? $values[self::FIELD_KEYWORDS] : ''
            ]);
        }
        $this->values['titre_liste'] = $values[self::FIELD_LABEL];
        foreach ($result[$hash] as $values) {
            $this->values['label'][$values['id_fiche']] = $values['bf_titre'];
        }
    }
}
