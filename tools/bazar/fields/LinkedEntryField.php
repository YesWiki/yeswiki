<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Core\Service\Performer;

/**
 * @Field({"listefichesliees", "listefiches"})
 */
class LinkedEntryField extends BazarField
{
    protected $query;
    protected $otherParams;
    protected $limit;
    protected $template;
    protected $linkType;
    protected $linkLabel;

    protected const FIELD_QUERY = 2;
    protected const FIELD_OTHER_PARAMS = 3;
    protected const FIELD_LIMIT = 4;
    protected const FIELD_TEMPLATE = 5;
    protected const FIELD_LINKED_LABEL_OR_TYPE = 6;
    private const TYPE_LABEL = 'label' ;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->query = $values[self::FIELD_QUERY] ?? '';
        $this->otherParams = $values[self::FIELD_OTHER_PARAMS] ?? '';
        $this->limit = $values[self::FIELD_LIMIT] ?? '';
        $this->template = $values[self::FIELD_TEMPLATE] ?? '';
        $this->linkType = (!empty($values[self::FIELD_LINKED_LABEL_OR_TYPE]) && $values[self::FIELD_LINKED_LABEL_OR_TYPE] === 'checkbox')
            ? 'checkboxfiche' : ((!empty($values[self::FIELD_LINKED_LABEL_OR_TYPE])) ? self::TYPE_LABEL : 'listefiche') ;
        $this->linkedLabel = $values[self::FIELD_LINKED_LABEL_OR_TYPE] ?? null ;
    }

    protected function renderInput($entry)
    {
        // Display the linked entries only on update
        if (isset($entry['id_fiche'])) {
            return $this->getService(Performer::class)->run('wakka', 'formatter', ['text' => $this->getBazarListAction($entry)]);
        }
    }

    protected function renderStatic($entry)
    {
        return $this->getService(Performer::class)->run('wakka', 'formatter', ['text' => $this->getBazarListAction($entry)]);
    }

    private function getBazarListAction($entry)
    {
        $query = (!empty($this->query)) ? $this->query . '|' : '' ;
        $query .= (!empty($entry['id_typeannonce']) && !empty($entry['id_fiche']))
                    ? (($this->linkType == self::TYPE_LABEL) ? $this->linkedLabel : $this->linkType . $entry['id_typeannonce'])
                       . '=' . $entry['id_fiche']
                    :'';
        return '{{bazarliste id="' . $this->name . '" query="' . $query . '"'
            . ((!empty($this->limit)) ? ' nb="' . $this->limit .'"': '')
            . ((!empty(trim($this->template))) ? ' template="' . trim($this->template) . '" ' : '')
            . $this->otherParams . '}}';
    }
    
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'query' => $this->query,
                'limit' => $this->limit,
                'linkType' => $this->linkType,
                'linkedLabel' => $this->linkedLabel,
                'template' => $this->template,
                'BazarListeACtionStr' => $this->getBazarListAction(['id_fiche' => 'FACTICE TEST ID','id_typeannonce' => "1"]),
                'otherParams' => $this->otherParams,
            ]
        );
    }
}
